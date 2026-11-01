<?php

namespace App\Modules\Accounting\Subledger\Services;

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\ChartOfAccount;
use App\Modules\Accounting\Subledger\Contracts\SubledgerReconciliationContract;
use App\Modules\Accounting\Subledger\DTOs\SubledgerReconciliationResult;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubledgerReconciliationService implements SubledgerReconciliationContract
{
    /**
     * Reconcile Accounts Receivable (AR) Subledger against GL Control Account 1030.
     */
    public function reconcileReceivables(string $companyId): SubledgerReconciliationResult
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new InvalidArgumentException('Cannot reconcile without active TenantContext.');
        }

        // 1. Calculate Subledger Open Balance for Customers (Net Debits - Credits)
        $debitSum = DB::table('subledger_open_items')
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('party_type', 'customer')
            ->where('direction', 'debit')
            ->whereIn('status', ['open', 'partially_allocated'])
            ->sum('remaining_amount');

        $creditSum = DB::table('subledger_open_items')
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('party_type', 'customer')
            ->where('direction', 'credit')
            ->whereIn('status', ['open', 'partially_allocated'])
            ->sum('remaining_amount');

        $subledgerTotal = Money::of((string) $debitSum)->subtract(Money::of((string) $creditSum));

        // 2. Calculate GL Control Account 1030 Balance (Debits - Credits)
        /** @var ChartOfAccount|null $arAccount */
        $arAccount = ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('account_code', '1030')
            ->first();

        if (! $arAccount) {
            throw new InvalidArgumentException("AR Control Account [1030] not found for company [{$companyId}].");
        }

        $glDebit = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.tenant_id', $tenantId)
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.is_posted', true)
            ->where('journal_lines.account_id', $arAccount->id)
            ->sum('journal_lines.debit_amount');

        $glCredit = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.tenant_id', $tenantId)
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.is_posted', true)
            ->where('journal_lines.account_id', $arAccount->id)
            ->sum('journal_lines.credit_amount');

        $glTotal = Money::of((string) $glDebit)->subtract(Money::of((string) $glCredit));

        // 3. Compute variance
        $variance = $subledgerTotal->subtract($glTotal);
        $isReconciled = $variance->isZero();

        return new SubledgerReconciliationResult(
            controlAccountCode: '1030',
            partyType: 'customer',
            subledgerTotal: $subledgerTotal,
            glControlTotal: $glTotal,
            variance: $variance,
            isReconciled: $isReconciled
        );
    }

    /**
     * Reconcile Accounts Payable (AP) Subledger against GL Control Account 2010.
     */
    public function reconcilePayables(string $companyId): SubledgerReconciliationResult
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new InvalidArgumentException('Cannot reconcile without active TenantContext.');
        }

        // 1. Calculate Subledger Open Balance for Suppliers (Net Credits - Debits)
        $creditSum = DB::table('subledger_open_items')
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('party_type', 'supplier')
            ->where('direction', 'credit')
            ->whereIn('status', ['open', 'partially_allocated'])
            ->sum('remaining_amount');

        $debitSum = DB::table('subledger_open_items')
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('party_type', 'supplier')
            ->where('direction', 'debit')
            ->whereIn('status', ['open', 'partially_allocated'])
            ->sum('remaining_amount');

        $subledgerTotal = Money::of((string) $creditSum)->subtract(Money::of((string) $debitSum));

        // 2. Calculate GL Control Account 2010 Balance (Credits - Debits for Liabilities)
        /** @var ChartOfAccount|null $apAccount */
        $apAccount = ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('account_code', '2010')
            ->first();

        if (! $apAccount) {
            throw new InvalidArgumentException("AP Control Account [2010] not found for company [{$companyId}].");
        }

        $glCredit = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.tenant_id', $tenantId)
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.is_posted', true)
            ->where('journal_lines.account_id', $apAccount->id)
            ->sum('journal_lines.credit_amount');

        $glDebit = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.tenant_id', $tenantId)
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.is_posted', true)
            ->where('journal_lines.account_id', $apAccount->id)
            ->sum('journal_lines.debit_amount');

        $glTotal = Money::of((string) $glCredit)->subtract(Money::of((string) $glDebit));

        // 3. Compute variance
        $variance = $subledgerTotal->subtract($glTotal);
        $isReconciled = $variance->isZero();

        return new SubledgerReconciliationResult(
            controlAccountCode: '2010',
            partyType: 'supplier',
            subledgerTotal: $subledgerTotal,
            glControlTotal: $glTotal,
            variance: $variance,
            isReconciled: $isReconciled
        );
    }
}
