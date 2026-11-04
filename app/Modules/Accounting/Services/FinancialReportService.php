<?php

namespace App\Modules\Accounting\Services;

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\ChartOfAccount;
use App\Modules\Accounting\Contracts\FinancialReportContract;
use App\Modules\Accounting\DTOs\BalanceSheetReportDTO;
use App\Modules\Accounting\DTOs\ProfitAndLossReportDTO;
use App\Modules\Accounting\DTOs\TrialBalanceAccountDTO;
use App\Modules\Accounting\DTOs\TrialBalanceReportDTO;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FinancialReportService implements FinancialReportContract
{
    /**
     * Compute Trial Balance ensuring sum of all debits equals sum of all credits.
     */
    public function getTrialBalance(string $companyId, ?string $periodId = null): TrialBalanceReportDTO
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new InvalidArgumentException('Cannot compute trial balance without active TenantContext.');
        }

        $accounts = ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->orderBy('account_code')
            ->get();

        $accountDTOs = [];
        $totalDebit = Money::zero('BDT');
        $totalCredit = Money::zero('BDT');

        foreach ($accounts as $account) {
            $query = DB::table('journal_lines')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entries.tenant_id', $tenantId)
                ->where('journal_entries.company_id', $companyId)
                ->where('journal_entries.is_posted', true)
                ->where('journal_lines.account_id', $account->id);

            if ($periodId) {
                $query->where('journal_entries.period_id', $periodId);
            }

            $debitRaw = $query->sum('journal_lines.debit_amount');
            $creditRaw = $query->sum('journal_lines.credit_amount');

            $debitMoney = Money::of((string) $debitRaw);
            $creditMoney = Money::of((string) $creditRaw);

            $totalDebit = $totalDebit->add($debitMoney);
            $totalCredit = $totalCredit->add($creditMoney);

            $netDebit = Money::zero('BDT');
            $netCredit = Money::zero('BDT');

            if (in_array($account->account_type, ['asset', 'expense'], true)) {
                if ($debitMoney->greaterThanOrEqual($creditMoney)) {
                    $netDebit = $debitMoney->subtract($creditMoney);
                } else {
                    $netCredit = $creditMoney->subtract($debitMoney);
                }
            } else {
                if ($creditMoney->greaterThanOrEqual($debitMoney)) {
                    $netCredit = $creditMoney->subtract($debitMoney);
                } else {
                    $netDebit = $debitMoney->subtract($creditMoney);
                }
            }

            $accountDTOs[] = new TrialBalanceAccountDTO(
                accountId: $account->id,
                accountCode: $account->account_code,
                accountName: $account->account_name,
                accountType: $account->account_type,
                debitTotal: $debitMoney,
                creditTotal: $creditMoney,
                netDebit: $netDebit,
                netCredit: $netCredit
            );
        }

        $isBalanced = $totalDebit->equals($totalCredit);

        return new TrialBalanceReportDTO(
            companyId: $companyId,
            periodId: $periodId,
            accounts: $accountDTOs,
            totalDebit: $totalDebit,
            totalCredit: $totalCredit,
            isBalanced: $isBalanced
        );
    }

    /**
     * Compute Profit and Loss (Income Statement) for a company and optional period.
     */
    public function getProfitAndLoss(string $companyId, ?string $periodId = null): ProfitAndLossReportDTO
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new InvalidArgumentException('Cannot compute P&L without active TenantContext.');
        }

        $accounts = ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->whereIn('account_type', ['revenue', 'expense'])
            ->orderBy('account_code')
            ->get();

        $totalRevenue = Money::zero('BDT');
        $totalCogs = Money::zero('BDT');
        $totalExpenses = Money::zero('BDT');

        $revenueAccounts = [];
        $expenseAccounts = [];

        foreach ($accounts as $account) {
            $query = DB::table('journal_lines')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entries.tenant_id', $tenantId)
                ->where('journal_entries.company_id', $companyId)
                ->where('journal_entries.is_posted', true)
                ->where('journal_lines.account_id', $account->id);

            if ($periodId) {
                $query->where('journal_entries.period_id', $periodId);
            }

            $debitRaw = $query->sum('journal_lines.debit_amount');
            $creditRaw = $query->sum('journal_lines.credit_amount');

            $debitMoney = Money::of((string) $debitRaw);
            $creditMoney = Money::of((string) $creditRaw);

            if ($account->account_type === 'revenue') {
                // Revenue net balance is Credit - Debit
                $netRevenue = $creditMoney->subtract($debitMoney);
                $totalRevenue = $totalRevenue->add($netRevenue);
                $revenueAccounts[] = [
                    'code' => $account->account_code,
                    'name' => $account->account_name,
                    'amount' => $netRevenue,
                ];
            } else {
                // Expense net balance is Debit - Credit
                $netExpense = $debitMoney->subtract($creditMoney);
                if (str_starts_with($account->account_code, '5')) {
                    // Cost of Goods Sold (5000s)
                    $totalCogs = $totalCogs->add($netExpense);
                } else {
                    // Operating & Administrative Expenses (6000s+)
                    $totalExpenses = $totalExpenses->add($netExpense);
                }

                $expenseAccounts[] = [
                    'code' => $account->account_code,
                    'name' => $account->account_name,
                    'amount' => $netExpense,
                ];
            }
        }

        $grossProfit = $totalRevenue->subtract($totalCogs);
        $netIncome = $grossProfit->subtract($totalExpenses);

        return new ProfitAndLossReportDTO(
            companyId: $companyId,
            periodId: $periodId,
            totalRevenue: $totalRevenue,
            totalCogs: $totalCogs,
            grossProfit: $grossProfit,
            totalExpenses: $totalExpenses,
            netIncome: $netIncome,
            revenueAccounts: $revenueAccounts,
            expenseAccounts: $expenseAccounts
        );
    }

    /**
     * Compute Balance Sheet (Financial Position) ensuring Assets = Liabilities + Equity + Net Income.
     */
    public function getBalanceSheet(string $companyId, ?string $periodId = null): BalanceSheetReportDTO
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new InvalidArgumentException('Cannot compute balance sheet without active TenantContext.');
        }

        $accounts = ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->whereIn('account_type', ['asset', 'liability', 'equity'])
            ->orderBy('account_code')
            ->get();

        $totalAssets = Money::zero('BDT');
        $totalLiabilities = Money::zero('BDT');
        $totalEquity = Money::zero('BDT');

        $assetAccounts = [];
        $liabilityAccounts = [];
        $equityAccounts = [];

        foreach ($accounts as $account) {
            $query = DB::table('journal_lines')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entries.tenant_id', $tenantId)
                ->where('journal_entries.company_id', $companyId)
                ->where('journal_entries.is_posted', true)
                ->where('journal_lines.account_id', $account->id);

            if ($periodId) {
                $query->where('journal_entries.period_id', $periodId);
            }

            $debitRaw = $query->sum('journal_lines.debit_amount');
            $creditRaw = $query->sum('journal_lines.credit_amount');

            $debitMoney = Money::of((string) $debitRaw);
            $creditMoney = Money::of((string) $creditRaw);

            if ($account->account_type === 'asset') {
                $netAsset = $debitMoney->subtract($creditMoney);
                $totalAssets = $totalAssets->add($netAsset);
                $assetAccounts[] = [
                    'code' => $account->account_code,
                    'name' => $account->account_name,
                    'amount' => $netAsset,
                ];
            } elseif ($account->account_type === 'liability') {
                $netLiability = $creditMoney->subtract($debitMoney);
                $totalLiabilities = $totalLiabilities->add($netLiability);
                $liabilityAccounts[] = [
                    'code' => $account->account_code,
                    'name' => $account->account_name,
                    'amount' => $netLiability,
                ];
            } elseif ($account->account_type === 'equity') {
                $netEquity = $creditMoney->subtract($debitMoney);
                $totalEquity = $totalEquity->add($netEquity);
                $equityAccounts[] = [
                    'code' => $account->account_code,
                    'name' => $account->account_name,
                    'amount' => $netEquity,
                ];
            }
        }

        // Current period net income from P&L
        $pnl = $this->getProfitAndLoss($companyId, $periodId);
        $currentPeriodNetIncome = $pnl->netIncome;

        $totalLiabilitiesAndEquity = $totalLiabilities->add($totalEquity)->add($currentPeriodNetIncome);
        $isBalanced = $totalAssets->equals($totalLiabilitiesAndEquity);

        return new BalanceSheetReportDTO(
            companyId: $companyId,
            periodId: $periodId,
            totalAssets: $totalAssets,
            totalLiabilities: $totalLiabilities,
            totalEquity: $totalEquity,
            currentPeriodNetIncome: $currentPeriodNetIncome,
            totalLiabilitiesAndEquity: $totalLiabilitiesAndEquity,
            isBalanced: $isBalanced,
            assetAccounts: $assetAccounts,
            liabilityAccounts: $liabilityAccounts,
            equityAccounts: $equityAccounts
        );
    }
}
