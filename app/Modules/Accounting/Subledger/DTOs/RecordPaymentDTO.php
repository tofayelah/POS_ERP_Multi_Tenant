<?php

namespace App\Modules\Accounting\Subledger\DTOs;

use App\Core\ValueObjects\Money;
use Carbon\CarbonInterface;

readonly class RecordPaymentDTO
{
    /**
     * @param  array<int, AllocationItemDTO>  $allocations
     * @param  array<string, string>  $accountMap
     */
    public function __construct(
        public string $companyId,
        public string $branchId,
        public string $paymentType,
        public string $paymentMethod,
        public string $partyType,
        public string $partyId,
        public CarbonInterface|string $paymentDate,
        public Money|string $amount,
        public ?string $reference = null,
        public ?string $postedBy = null,
        public array $allocations = [],
        public array $accountMap = [],
    ) {}
}
