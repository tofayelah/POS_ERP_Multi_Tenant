<?php

namespace App\Modules\Sales\DTOs;

readonly class CreateQuotationLineDTO
{
    public function __construct(
        public string $productId,
        public string $variantId,
        public string $quantity,
        public string $unitPrice,
        public string $taxAmount = '0.0000',
        public ?string $notes = null,
    ) {}
}
