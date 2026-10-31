<?php

namespace App\Modules\Inventory\DTOs;

readonly class StockMovementDTO
{
    public function __construct(
        public string $companyId,
        public string $warehouseId,
        public ?string $stockLocationId,
        public string $productVariantId,
        public string $movementType,
        public string $quantity,
        public string $unitCost,
        public string $referenceType,
        public string $referenceId,
        public ?string $batchNumber = null,
        public ?string $expiryDate = null,
        public ?string $movementDate = null,
    ) {}
}
