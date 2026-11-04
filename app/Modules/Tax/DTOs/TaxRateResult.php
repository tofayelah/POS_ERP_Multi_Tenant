<?php

namespace App\Modules\Tax\DTOs;

readonly class TaxRateResult
{
    public function __construct(
        public string $taxCodeId,
        public string $vatRatePercent,
        public string $supplementaryDutyPercent,
        public string $effectiveFrom,
        public ?string $effectiveTo = null,
    ) {}
}
