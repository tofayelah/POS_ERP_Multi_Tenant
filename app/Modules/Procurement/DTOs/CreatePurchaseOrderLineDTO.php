<?php

namespace App\Modules\Procurement\DTOs;

readonly class CreatePurchaseOrderLineDTO
{
    public function __construct(
        public string $productId,
        public string $variantId,
        public string $orderedQuantity,
        public string $unitPrice,
        public string $taxAmount = '0.0000',
    ) {}
}
