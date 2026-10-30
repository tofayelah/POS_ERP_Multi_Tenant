<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingCompanyConsistencyTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $companyA;

    protected Company $companyB;

    protected FiscalYear $fyA;

    protected FiscalYear $fyB;

    protected AccountingPeriod $periodA;

    protected AccountingPeriod $periodB;

    protected ChartOfAccount $accountA;

    protected ChartOfAccount $accountB;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Consistency Test Tenant',
            'subdomain' => 'consistency-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->companyA = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Company A',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->companyB = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Company B',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->fyA = FiscalYear::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'title' => 'FY A 2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);

        $this->fyB = FiscalYear::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'title' => 'FY B 2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);

        $this->periodA = AccountingPeriod::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'fiscal_year_id' => $this->fyA->id,
            'period_number' => 1,
            'period_name' => 'July 2026 A',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'open',
        ]);

        $this->periodB = AccountingPeriod::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'fiscal_year_id' => $this->fyB->id,
            'period_number' => 1,
            'period_name' => 'July 2026 B',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'open',
        ]);

        $this->accountA = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'account_code' => '1010',
            'account_name' => 'Cash A',
            'account_type' => 'asset',
        ]);

        $this->accountB = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'account_code' => '1010',
            'account_name' => 'Cash B',
            'account_type' => 'asset',
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Accountant',
            'email' => 'acct-'.Str::random(6).'@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_journal_line_cannot_reference_account_from_another_company_new_22(): void
    {
        $entryAId = (string) Str::uuid();

        DB::table('journal_entries')->insert([
            'id' => $entryAId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'period_id' => $this->periodA->id,
            'entry_number' => 'JV-2026-2027-000001',
            'posting_date' => '2026-07-15',
            'business_date' => '2026-07-15',
            'reference_type' => 'manual',
            'is_posted' => false,
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(foreign key|fk_lines_account|violates foreign key)/i');

        // Line in Company A attempting to reference Account in Company B
        DB::table('journal_lines')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'journal_entry_id' => $entryAId,
            'account_id' => $this->accountB->id, // Mismatched company account!
            'debit_amount' => 500.0000,
            'credit_amount' => 0.0000,
            'base_debit_amount' => 500.0000,
            'base_credit_amount' => 0.0000,
            'created_at' => now(),
        ]);
    }

    public function test_journal_entry_cannot_reference_period_from_another_company(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(foreign key|fk_journal_period|violates foreign key)/i');

        // Entry in Company A attempting to reference Period in Company B
        DB::table('journal_entries')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'period_id' => $this->periodB->id, // Mismatched company period!
            'entry_number' => 'JV-2026-2027-000002',
            'posting_date' => '2026-07-15',
            'business_date' => '2026-07-15',
            'reference_type' => 'manual',
            'is_posted' => false,
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
