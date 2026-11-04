<?php

namespace App\Modules\Pos\DTOs;

readonly class SyncSaleItemDTO
{
    public function __construct(
        public string $variantId,
        public string $quantity,
        public string $unitPrice,
        public ?string $taxCodeId = null,
        public ?string $flatTaxRate = null,
        public string $discountAmount = '0.0000',
    ) {}
}
