<?php

namespace App\Modules\Tax\DTOs;

use App\Core\ValueObjects\Money;

readonly class CashRoundingResult
{
    public function __construct(
        public Money $roundedCash,
        public Money $adjustment,
    ) {}
}
