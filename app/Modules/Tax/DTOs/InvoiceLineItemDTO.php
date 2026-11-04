<?php

namespace App\Modules\Tax\DTOs;

use App\Core\ValueObjects\Money;
use App\Models\ProductVariant;
use App\Models\TaxCode;

readonly class InvoiceLineItemDTO
{
    public function __construct(
        public ProductVariant|string $variant,
        public string $itemDescription,
        public string $quantity,
        public Money|string $unitPrice,
        public TaxCode|string|null $taxCode = null,
        public Money|string $discount = '0',
        public ?string $uom = 'Pcs',
        public ?string $batchNumber = null,
        public ?string $vatRatePercent = null,
        public ?string $supplementaryDutyPercent = null,
        public ?string $cogsAmount = null,
    ) {}
}
