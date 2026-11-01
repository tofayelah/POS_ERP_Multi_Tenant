<?php

namespace App\Modules\Accounting\Subledger\DTOs;

use App\Core\ValueObjects\Money;

readonly class BankReconciliationSummaryDTO
{
    public function __construct(
        public string $statementId,
        public Money $statementClosingBalance,
        public Money $glBookBalance,
        public Money $clearedDepositsTotal,
        public Money $clearedWithdrawalsTotal,
        public int $unmatchedStatementLinesCount,
        public Money $unmatchedStatementLinesTotal,
        public int $unmatchedPaymentsCount,
        public Money $unmatchedPaymentsTotal,
        public bool $isFullyReconciled,
    ) {}
}
