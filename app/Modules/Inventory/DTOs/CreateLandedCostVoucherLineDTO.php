<?php

namespace App\Modules\Inventory\DTOs;

readonly class CreateLandedCostVoucherLineDTO
{
    public function __construct(
        public string $expenseType,
        public string $description,
        public string $amount,
        public string $taxAmount = '0.0000',
    ) {}
}
