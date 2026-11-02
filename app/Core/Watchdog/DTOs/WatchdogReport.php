<?php

namespace App\Core\Watchdog\DTOs;

class WatchdogReport
{
    /**
     * @param  array<string, TenantWatchdogResult>  $results
     */
    public function __construct(
        public array $results = [],
    ) {}

    public function addTenantResult(TenantWatchdogResult $result): void
    {
        $this->results[$result->tenantId] = $result;
    }

    public function hasAnyBreach(): bool
    {
        foreach ($this->results as $result) {
            if ($result->hasBreaches()) {
                return true;
            }
        }

        return false;
    }

    public function totalBreaches(): int
    {
        $total = 0;
        foreach ($this->results as $result) {
            $total += $result->breachCount();
        }

        return $total;
    }
}
