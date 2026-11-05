<?php

namespace App\Modules\Inventory\DTOs;

readonly class CreateLandedCostVoucherDTO
{
    /**
     * @param  array<int, string>  $goodsReceiptIds
     * @param  array<int, CreateLandedCostVoucherLineDTO>  $lines
     */
    public function __construct(
        public string $companyId,
        public string $branchId,
        public string $voucherDate,
        public string $allocationMethod,
        public string $currency = 'BDT',
        public string $exchangeRate = '1.000000',
        public ?string $notes = null,
        public array $goodsReceiptIds = [],
        public array $lines = [],
        public ?string $createdBy = null,
        public ?string $voucherNumber = null,
    ) {}
}
