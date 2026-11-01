<?php

namespace App\Modules\Accounting\DTOs;

use App\Core\ValueObjects\Money;

readonly class TrialBalanceAccountDTO
{
    public function __construct(
        public string $accountId,
        public string $accountCode,
        public string $accountName,
        public string $accountType,
        public Money $debitTotal,
        public Money $creditTotal,
        public Money $netDebit,
        public Money $netCredit,
    ) {}
}
