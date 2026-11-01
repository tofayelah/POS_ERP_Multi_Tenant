<?php

namespace App\Modules\Sales\DTOs;

readonly class CreateSalesOrderDTO
{
    /**
     * @param  array<int, CreateSalesOrderLineDTO>  $lines
     */
    public function __construct(
        public string $companyId,
        public string $branchId,
        public string $customerId,
        public ?string $quotationId,
        public string $orderDate,
        public ?string $expectedDeliveryDate,
        public string $createdBy,
        public array $lines,
    ) {}
}
