<?php

namespace App\Modules\Sales\DTOs;

readonly class CreateSalesOrderLineDTO
{
    public function __construct(
        public ?string $quotationLineId,
        public string $productId,
        public string $variantId,
        public string $orderedQuantity,
        public string $unitPrice,
        public string $taxAmount = '0.0000',
    ) {}
}
