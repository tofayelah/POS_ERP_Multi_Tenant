<?php

namespace App\Modules\Tax\DTOs;

use App\Core\ValueObjects\Money;

readonly class BasketTaxResult
{
    /**
     * @param  array<int, TaxLineResult>  $lines
     */
    public function __construct(
        public Money $gross,
        public Money $net,
        public Money $discount,
        public Money $supplementaryDuty,
        public Money $vat,
        public Money $totalPayable,
        public array $lines = [],
    ) {}
}
