<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\NumberSeries;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingTenantIsolationTest extends TestCase
{
    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Company $companyA;

    protected Company $companyB;

    protected User $userA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Accounting Tenant A',
            'subdomain' => 'acct-a-'.Str::random(6),
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Accounting Tenant B',
            'subdomain' => 'acct-b-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenantA);

        $this->companyA = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Company A',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->userA = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Accountant A',
            'email' => 'acct-a-'.Str::random(6).'@example.com',
            'password' => 'password',
        ]);

        TenantContext::set($this->tenantB);

        $this->companyB = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'legal_name' => 'Company B',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_tenant_b_cannot_see_tenant_a_accounting_records(): void
    {
        // 1. Create accounting records as Tenant A
        TenantContext::set($this->tenantA);

        $fy = FiscalYear::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'title' => 'FY 2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);

        $period = AccountingPeriod::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'fiscal_year_id' => $fy->id,
            'period_number' => 1,
            'period_name' => 'July 2026',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'open',
        ]);

        $account = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'account_code' => '1010',
            'account_name' => 'Cash',
            'account_type' => 'asset',
        ]);

        $series = NumberSeries::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => null,
            'document_type' => 'JV',
            'prefix' => 'JV',
            'fiscal_year' => '2026-2027',
            'current_number' => 1,
            'padding' => 6,
        ]);

        $entry = JournalEntry::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'period_id' => $period->id,
            'entry_number' => 'JV-2026-2027-000001',
            'posting_date' => '2026-07-15',
            'business_date' => '2026-07-15',
            'reference_type' => 'manual',
            'is_posted' => false,
            'posted_by' => $this->userA->id,
        ]);

        $line = JournalLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'debit_amount' => '100.0000',
            'credit_amount' => '0.0000',
            'base_debit_amount' => '100.0000',
            'base_credit_amount' => '0.0000',
        ]);

        // 2. Switch to Tenant B
        TenantContext::set($this->tenantB);

        $this->assertNull(FiscalYear::find($fy->id));
        $this->assertNull(AccountingPeriod::find($period->id));
        $this->assertNull(ChartOfAccount::find($account->id));
        $this->assertNull(NumberSeries::find($series->id));
        $this->assertNull(JournalEntry::find($entry->id));
        $this->assertNull(JournalLine::find($line->id));

        $this->assertSame(0, FiscalYear::where('id', $fy->id)->count());
        $this->assertSame(0, AccountingPeriod::where('id', $period->id)->count());
        $this->assertSame(0, ChartOfAccount::where('id', $account->id)->count());
        $this->assertSame(0, NumberSeries::where('id', $series->id)->count());
        $this->assertSame(0, JournalEntry::where('id', $entry->id)->count());
        $this->assertSame(0, JournalLine::where('id', $line->id)->count());
    }

    public function test_tenant_b_cannot_update_or_delete_tenant_a_accounting_records(): void
    {
        TenantContext::set($this->tenantA);

        $account = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'account_code' => '1020',
            'account_name' => 'Bank A',
            'account_type' => 'asset',
        ]);

        // Switch to Tenant B
        TenantContext::set($this->tenantB);

        $affected = DB::table('chart_of_accounts')
            ->where('id', $account->id)
            ->update(['account_name' => 'Compromised Name']);

        $this->assertSame(0, $affected);

        $deleted = DB::table('chart_of_accounts')
            ->where('id', $account->id)
            ->delete();

        $this->assertSame(0, $deleted);

        // Verify still intact in Tenant A
        TenantContext::set($this->tenantA);
        $this->assertSame('Bank A', $account->fresh()->account_name);
    }

    public function test_tenant_b_cannot_insert_records_for_tenant_a(): void
    {
        TenantContext::set($this->tenantB);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(row-level security policy|rls|violates)/i');

        // Tenant B attempting to insert with Tenant A's tenant_id
        DB::table('chart_of_accounts')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id, // Forbidden!
            'company_id' => $this->companyB->id,
            'account_code' => '9999',
            'account_name' => 'Cross-Tenant Injection',
            'account_type' => 'expense',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
