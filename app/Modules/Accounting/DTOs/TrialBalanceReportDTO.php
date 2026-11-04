<?php

namespace App\Modules\Accounting\DTOs;

use App\Core\ValueObjects\Money;

readonly class TrialBalanceReportDTO
{
    /**
     * @param  array<int, TrialBalanceAccountDTO>  $accounts
     */
    public function __construct(
        public string $companyId,
        public ?string $periodId,
        public array $accounts,
        public Money $totalDebit,
        public Money $totalCredit,
        public bool $isBalanced,
    ) {}
}
