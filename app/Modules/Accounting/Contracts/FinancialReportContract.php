<?php

namespace App\Modules\Accounting\Contracts;

use App\Modules\Accounting\DTOs\BalanceSheetReportDTO;
use App\Modules\Accounting\DTOs\ProfitAndLossReportDTO;
use App\Modules\Accounting\DTOs\TrialBalanceReportDTO;

interface FinancialReportContract
{
    /**
     * Compute Trial Balance ensuring sum of all debits equals sum of all credits.
     */
    public function getTrialBalance(string $companyId, ?string $periodId = null): TrialBalanceReportDTO;

    /**
     * Compute Profit and Loss (Income Statement) for a company and optional period.
     */
    public function getProfitAndLoss(string $companyId, ?string $periodId = null): ProfitAndLossReportDTO;

    /**
     * Compute Balance Sheet (Financial Position) ensuring Assets = Liabilities + Equity + Net Income.
     */
    public function getBalanceSheet(string $companyId, ?string $periodId = null): BalanceSheetReportDTO;
}
