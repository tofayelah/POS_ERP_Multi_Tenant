<?php

namespace App\Modules\Accounting\Subledger\DTOs;

use App\Core\ValueObjects\Money;

readonly class CustomerCreditCheckResult
{
    public function __construct(
        public bool $isAllowed,
        public Money $creditLimit,
        public Money $currentDue,
        public Money $proposedAmount,
        public Money $projectedDue,
        public ?string $rejectionReason = null,
    ) {}
}
