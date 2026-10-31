<?php

namespace App\Modules\Inventory\DTOs;

readonly class StockTransferItemDTO
{
    public function __construct(
        public string $productVariantId,
        public string $quantity,
        public string $unitCost,
        public ?string $stockLocationId = null,
        public ?string $destStockLocationId = null,
        public ?string $batchNumber = null,
        public ?string $notes = null,
    ) {}
}
