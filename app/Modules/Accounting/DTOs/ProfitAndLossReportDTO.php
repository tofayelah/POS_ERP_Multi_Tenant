<?php

namespace App\Modules\Accounting\DTOs;

use App\Core\ValueObjects\Money;

readonly class ProfitAndLossReportDTO
{
    /**
     * @param  array<int, array{code: string, name: string, amount: Money}>  $revenueAccounts
     * @param  array<int, array{code: string, name: string, amount: Money}>  $expenseAccounts
     */
    public function __construct(
        public string $companyId,
        public ?string $periodId,
        public Money $totalRevenue,
        public Money $totalCogs,
        public Money $grossProfit,
        public Money $totalExpenses,
        public Money $netIncome,
        public array $revenueAccounts = [],
        public array $expenseAccounts = [],
    ) {}
}
