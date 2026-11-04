<?php

namespace App\Modules\Procurement\DTOs;

readonly class CreateGoodsReceiptLineDTO
{
    public function __construct(
        public ?string $purchaseOrderLineId,
        public string $productId,
        public string $variantId,
        public string $receivedQuantity,
        public string $unitCost,
        public ?string $batchNumber = null,
        public ?string $expiryDate = null,
    ) {}
}
