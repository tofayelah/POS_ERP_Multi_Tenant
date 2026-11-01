<?php

namespace App\Modules\Tax\DTOs;

readonly class ReturnLineItemDTO
{
    public function __construct(
        public string $salesInvoiceLineId,
        public string $quantity,
        public ?string $reason = null,
    ) {}
}
