<?php

namespace App\Modules\Procurement\DTOs;

readonly class CreateSupplierQuotationLineDTO
{
    public function __construct(
        public string $productId,
        public string $variantId,
        public string $requestedQuantity,
        public string $quotedUnitPrice,
        public string $discountAmount = '0.0000',
        public string $taxRate = '0.0000',
        public int $leadTimeDays = 0,
        public bool $isSelected = false,
    ) {}
}
