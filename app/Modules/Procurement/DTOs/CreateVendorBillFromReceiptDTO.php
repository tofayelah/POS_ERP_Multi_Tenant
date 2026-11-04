<?php

namespace App\Modules\Procurement\DTOs;

readonly class CreateVendorBillFromReceiptDTO
{
    public function __construct(
        public string $companyId,
        public string $branchId,
        public string $goodsReceiptId,
        public string $billDate,
        public string $dueDate,
        public string $createdBy,
        public ?string $periodId = null,
    ) {}
}
