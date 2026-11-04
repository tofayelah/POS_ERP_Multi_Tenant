<?php

namespace App\Modules\Procurement\DTOs;

readonly class CreateGoodsReceiptDTO
{
    /**
     * @param  array<int, CreateGoodsReceiptLineDTO>  $lines
     */
    public function __construct(
        public string $companyId,
        public string $branchId,
        public string $warehouseId,
        public string $supplierId,
        public ?string $purchaseOrderId,
        public string $receiptDate,
        public string $createdBy,
        public ?string $challanNumber = null,
        public array $lines = [],
    ) {}
}
