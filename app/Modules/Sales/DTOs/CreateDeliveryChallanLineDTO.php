<?php

namespace App\Modules\Sales\DTOs;

readonly class CreateDeliveryChallanLineDTO
{
    public function __construct(
        public ?string $salesOrderLineId,
        public string $productId,
        public string $variantId,
        public string $quantity,
        public ?string $batchNumber = null,
        public ?string $notes = null,
    ) {}
}
