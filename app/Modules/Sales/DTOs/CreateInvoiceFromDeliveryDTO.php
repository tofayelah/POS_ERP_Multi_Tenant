<?php

namespace App\Modules\Sales\DTOs;

readonly class CreateInvoiceFromDeliveryDTO
{
    public function __construct(
        public string $companyId,
        public string $branchId,
        public string $deliveryChallanId,
        public string $invoiceDate,
        public string $createdBy,
        public ?string $periodId = null,
        public ?string $dueDate = null,
    ) {}
}
