<?php

namespace App\Modules\Accounting\Subledger\DTOs;

use Carbon\CarbonInterface;

readonly class SupplierVdsWithholdingDTO
{
    public function __construct(
        public string $companyId,
        public string $branchId,
        public string $supplierId,
        public string $supplierBillId,
        public string $certificateNumber,
        public CarbonInterface|string $issueDate,
        public string $taxPeriod,
        public string $totalBillAmount,
        public string $totalVatAmount,
        public string $vdsAmount,
        public string $netPaymentAmount,
        public string $paymentMethod,
        public ?string $reference,
        public string $createdBy,
    ) {}
}
