<?php

namespace App\Modules\Procurement\DTOs;

readonly class CreatePurchaseOrderDTO
{
    /**
     * @param  array<int, CreatePurchaseOrderLineDTO>  $lines
     */
    public function __construct(
        public string $companyId,
        public string $branchId,
        public string $supplierId,
        public string $orderDate,
        public ?string $expectedDeliveryDate,
        public string $createdBy,
        public array $lines,
    ) {}
}
