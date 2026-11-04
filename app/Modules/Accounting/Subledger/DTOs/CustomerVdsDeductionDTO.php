<?php

namespace App\Modules\Accounting\Subledger\DTOs;

use Carbon\CarbonInterface;

readonly class CustomerVdsDeductionDTO
{
    public function __construct(
        public string $companyId,
        public string $branchId,
        public string $customerId,
        public string $salesInvoiceId,
        public string $certificateNumber,
        public string $withholdingEntityName,
        public string $withholdingEntityBin,
        public CarbonInterface|string $issueDate,
        public string $taxPeriod,
        public string $totalInvoiceAmount,
        public string $totalVatAmount,
        public string $vdsAmount,
        public string $netPaymentAmount,
        public string $paymentMethod,
        public ?string $reference,
        public string $createdBy,
    ) {}
}
