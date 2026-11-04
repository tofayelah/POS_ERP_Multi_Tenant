<?php

namespace App\Modules\Tax\DTOs;

use App\Core\ValueObjects\Money;

readonly class TaxLineResult
{
    public function __construct(
        public Money $gross,
        public Money $net,
        public Money $supplementaryDuty,
        public Money $vat,
        public Money $total,
        public Money $discount,
        public string $vatRatePercent,
        public string $supplementaryDutyPercent,
        public string $pricingBasis,
    ) {}
}
