<?php

namespace App\Modules\Inventory\DTOs;

readonly class InventoryReconciliationDTO
{
    public function __construct(
        public string $companyId,
        public string $stockValuation,
        public string $glBalance,
        public string $variance,
        public bool $isReconciled,
        public string $asOf,
    ) {}
}
