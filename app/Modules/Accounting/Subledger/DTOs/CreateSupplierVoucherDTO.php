<?php

namespace App\Modules\Accounting\Subledger\DTOs;

use App\Core\ValueObjects\Money;
use Carbon\CarbonInterface;

readonly class CreateSupplierVoucherDTO
{
    /**
     * @param  array<int, AllocationItemDTO>  $allocations
     * @param  array<string, string>  $accountMap
     */
    public function __construct(
        public string $companyId,
        public string $branchId,
        public string $supplierId,
        public string $paymentMethod,
        public Money|string $amount,
        public CarbonInterface|string $paymentDate,
        public string $createdBy,
        public ?string $reference = null,
        public array $allocations = [],
        public array $accountMap = [],
    ) {}
}
