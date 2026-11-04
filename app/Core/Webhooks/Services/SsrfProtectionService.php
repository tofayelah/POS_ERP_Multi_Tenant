<?php

namespace App\Core\Webhooks\Services;

use App\Core\Outbox\Exceptions\SsrfBlockedException;

class SsrfProtectionService
{
    /**
     * Blocked IPv4 CIDR ranges:
     * - 0.0.0.0/8 (Current network)
     * - 10.0.0.0/8 (Private)
     * - 100.64.0.0/10 (Shared Address Space / CGNAT)
     * - 127.0.0.0/8 (Loopback)
     * - 169.254.0.0/16 (Link Local / Cloud Metadata)
     * - 172.16.0.0/12 (Private)
     * - 192.0.0.0/24 (IETF Protocol Assignments)
     * - 192.0.2.0/24 (TEST-NET-1)
     * - 192.168.0.0/16 (Private)
     * - 198.18.0.0/15 (Network Interconnect Benchmark Testing)
     * - 198.51.100.0/24 (TEST-NET-2)
     * - 203.0.113.0/24 (TEST-NET-3)
     * - 224.0.0.0/4 (Multicast)
     * - 240.0.0.0/4 (Reserved / Future Use)
     * - 255.255.255.255/32 (Broadcast)
     */
    protected const BLOCKED_IPV4_CIDRS = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '255.255.255.255/32',
    ];

    /**
     * Blocked IPv6 CIDR ranges:
     * - ::1/128 (Loopback)
     * - ::/128 (Unspecified)
     * - ::ffff:0:0/96 (IPv4-mapped IPv6)
     * - 64:ff9b::/96 (IPv4/IPv6 translation)
     * - 100::/64 (Discard-Only Address Block)
     * - 2001:db8::/32 (Documentation)
     * - fc00::/7 (Unique Local Address)
     * - fe80::/10 (Link-Local)
     * - ff00::/8 (Multicast)
     */
    protected const BLOCKED_IPV6_CIDRS = [
        '::1/128',
        '::/128',
        '::ffff:0:0/96',
        '64:ff9b::/96',
        '100::/64',
        '2001:db8::/32',
        'fc00::/7',
        'fe80::/10',
        'ff00::/8',
    ];

    /**
     * Asserts that a target webhook URL does not resolve to private, loopback, or cloud metadata endpoints.
     *
     * @return array<int, string> Resolved safe IP addresses
     *
     * @throws SsrfBlockedException
     */
    public function assertSafeDestinationUrl(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new SsrfBlockedException("Malformed URL: {$url}");
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new SsrfBlockedException("Invalid protocol '{$scheme}'. Only HTTP and HTTPS are permitted for webhooks.");
        }

        $host = $parts['host'];
        // Strip IPv6 brackets if present e.g. [::1]
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        // Resolve host to IPs
        $resolvedIps = $this->resolveHostToIps($host);

        if (empty($resolvedIps)) {
            throw new SsrfBlockedException("Unable to resolve host: {$host}");
        }

        foreach ($resolvedIps as $ip) {
            if ($this->isIpBlocked($ip)) {
                throw new SsrfBlockedException("SSRF Blocked: Destination host '{$host}' resolves to restricted IP: {$ip}");
            }
        }

        return $resolvedIps;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveHostToIps(string $host): array
    {
        // If already an IP
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];

        // IPv4 resolution
        $ipv4Records = @dns_get_record($host, DNS_A);
        if (is_array($ipv4Records)) {
            foreach ($ipv4Records as $record) {
                if (isset($record['ip']) && filter_var($record['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $ips[] = $record['ip'];
                }
            }
        }

        // Fallback for IPv4 if dns_get_record returned nothing
        if (empty($ips)) {
            $hostByNames = @gethostbynamel($host);
            if (is_array($hostByNames)) {
                $ips = array_merge($ips, $hostByNames);
            }
        }

        // IPv6 resolution
        $ipv6Records = @dns_get_record($host, DNS_AAAA);
        if (is_array($ipv6Records)) {
            foreach ($ipv6Records as $record) {
                if (isset($record['ipv6']) && filter_var($record['ipv6'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        // If localhost string, explicitly map to 127.0.0.1
        if (strtolower($host) === 'localhost' && empty($ips)) {
            $ips[] = '127.0.0.1';
        }

        return array_values(array_unique($ips));
    }

    protected function isIpBlocked(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            foreach (self::BLOCKED_IPV4_CIDRS as $cidr) {
                if ($this->ipv4MatchCidr($ip, $cidr)) {
                    return true;
                }
            }

            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            foreach (self::BLOCKED_IPV6_CIDRS as $cidr) {
                if ($this->ipv6MatchCidr($ip, $cidr)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    protected function ipv4MatchCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);
        $maskInt = (int) $mask;

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        if ($maskInt === 0) {
            return true;
        }

        $netmask = ~((1 << (32 - $maskInt)) - 1);

        return ($ipLong & $netmask) === ($subnetLong & $netmask);
    }

    protected function ipv6MatchCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);
        $maskInt = (int) $mask;

        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        $fullBytes = intdiv($maskInt, 8);
        $remainingBits = $maskInt % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits > 0) {
            $byteIp = ord($ipBin[$fullBytes]);
            $byteSubnet = ord($subnetBin[$fullBytes]);
            $maskByte = ~(0xFF >> $remainingBits) & 0xFF;

            if (($byteIp & $maskByte) !== ($byteSubnet & $maskByte)) {
                return false;
            }
        }

        return true;
    }
}
