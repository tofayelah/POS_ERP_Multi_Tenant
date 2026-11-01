<?php

namespace App\Modules\Sales\DTOs;

readonly class CreateDeliveryChallanDTO
{
    /**
     * @param  array<int, CreateDeliveryChallanLineDTO>  $lines
     */
    public function __construct(
        public string $companyId,
        public string $branchId,
        public string $warehouseId,
        public string $customerId,
        public ?string $salesOrderId,
        public string $challanDate,
        public string $createdBy,
        public ?string $vehicleNumber = null,
        public ?string $driverName = null,
        public ?string $driverPhone = null,
        public array $lines = [],
    ) {}
}
