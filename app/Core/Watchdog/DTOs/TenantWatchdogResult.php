<?php

namespace App\Core\Watchdog\DTOs;

class TenantWatchdogResult
{
    /**
     * @param  array<int, array<string, mixed>>  $unbalancedJournals
     * @param  array<int, array<string, mixed>>  $stockDiscrepancies
     * @param  array<int, array<string, mixed>>  $orphanNegativeStocks
     */
    public function __construct(
        public string $tenantId,
        public string $tenantName,
        public array $unbalancedJournals = [],
        public array $stockDiscrepancies = [],
        public array $orphanNegativeStocks = [],
    ) {}

    public function hasBreaches(): bool
    {
        return ! empty($this->unbalancedJournals)
            || ! empty($this->stockDiscrepancies)
            || ! empty($this->orphanNegativeStocks);
    }

    public function breachCount(): int
    {
        return count($this->unbalancedJournals)
            + count($this->stockDiscrepancies)
            + count($this->orphanNegativeStocks);
    }
}
