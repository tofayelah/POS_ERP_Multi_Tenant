<?php

namespace App\Modules\Inventory\DTOs;

readonly class LandedCostAllocationDTO
{
    public function __construct(
        public string $productVariantId,
        public string $warehouseId,
        public string $allocatedAmount,
        public ?string $stockLocationId = null,
        public ?string $batchNumber = null,
    ) {}
}
