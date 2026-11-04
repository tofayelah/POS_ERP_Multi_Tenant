<?php

namespace App\Core\Webhooks\Services;

use App\Core\Outbox\Exceptions\SsrfBlockedException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WebhookDispatcher
{
    public function __construct(
        protected SsrfProtectionService $ssrfProtection
    ) {}

    /**
     * Builds standard HMAC-SHA256 authenticated webhook headers with a timestamp.
     *
     * @return array<string, string>
     */
    public function buildHeaders(string $payload, string $secret, ?int $timestamp = null): array
    {
        $timestamp = $timestamp ?? time();
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return [
            'Content-Type' => 'application/json',
            'X-Timestamp' => (string) $timestamp,
            'X-Signature' => $signature,
        ];
    }

    /**
     * Verifies an incoming webhook payload against HMAC-SHA256 signature and 300s replay window.
     */
    public function verifySignature(
        string $payload,
        string $signatureHeader,
        string $timestampHeader,
        string $secret,
        int $toleranceSeconds = 300
    ): bool {
        $timestamp = (int) $timestampHeader;
        $currentTime = time();

        // 1. Replay prevention: Reject if timestamp is older or newer than allowed window
        if (abs($currentTime - $timestamp) > $toleranceSeconds) {
            return false;
        }

        // 2. Validate signature format
        if (! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expectedHash = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $providedHash = substr($signatureHeader, 7);

        return hash_equals($expectedHash, $providedHash);
    }

    /**
     * Dispatches an HTTP POST webhook safely with SSRF protection and HMAC headers.
     *
     * @param  array<string, string>  $extraHeaders
     *
     * @throws SsrfBlockedException|RuntimeException
     */
    public function dispatch(
        string $url,
        string $payload,
        string $secret,
        array $extraHeaders = [],
        int $timeoutSeconds = 5
    ): bool {
        // Assert destination is safe from SSRF attacks before network call
        $this->ssrfProtection->assertSafeDestinationUrl($url);

        $headers = array_merge(
            $this->buildHeaders($payload, $secret),
            $extraHeaders
        );

        $response = Http::withHeaders($headers)
            ->timeout($timeoutSeconds)
            ->withBody($payload, 'application/json')
            ->post($url);

        if (! $response->successful()) {
            throw new RuntimeException("Webhook endpoint returned error HTTP {$response->status()}: {$response->body()}");
        }

        return true;
    }
}
