<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class JournalImmutabilityTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected FiscalYear $fiscalYear;

    protected AccountingPeriod $openPeriod;

    protected AccountingPeriod $closedPeriod;

    protected ChartOfAccount $cashAccount;

    protected ChartOfAccount $revenueAccount;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Journal Immutability Tenant',
            'subdomain' => 'journal-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Acme Retail',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->fiscalYear = FiscalYear::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'title' => 'FY 2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);

        $this->openPeriod = AccountingPeriod::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'period_number' => 1,
            'period_name' => 'July 2026',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'open',
        ]);

        $this->closedPeriod = AccountingPeriod::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'period_number' => 2,
            'period_name' => 'August 2026',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'closed',
        ]);

        $this->cashAccount = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1010',
            'account_name' => 'Cash in Hand',
            'account_type' => 'asset',
        ]);

        $this->revenueAccount = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '4010',
            'account_name' => 'Sales Revenue',
            'account_type' => 'revenue',
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Accountant User',
            'email' => 'accountant-'.Str::random(6).'@example.com',
            'password' => 'password',
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_trigger1_direct_insert_in_posted_state_fails(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(Financial Integrity Violation|draft state|is_posted = FALSE)/i');

        DB::table('journal_entries')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->openPeriod->id,
            'entry_number' => 'JV-2026-2027-000001',
            'posting_date' => '2026-07-15',
            'business_date' => '2026-07-15',
            'reference_type' => 'manual',
            'is_posted' => true, // Attempt direct insert as posted
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_g1_balanced_post_succeeds(): void
    {
        $entryId = (string) Str::uuid();

        // 1. Insert in draft state
        DB::table('journal_entries')->insert([
            'id' => $entryId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->openPeriod->id,
            'entry_number' => 'JV-2026-2027-000002',
            'posting_date' => '2026-07-15',
            'business_date' => '2026-07-15',
            'reference_type' => 'manual',
            'is_posted' => false,
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Insert 2 balanced lines
        DB::table('journal_lines')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $entryId,
                'account_id' => $this->cashAccount->id,
                'debit_amount' => 1000.0000,
                'credit_amount' => 0.0000,
                'base_debit_amount' => 1000.0000,
                'base_credit_amount' => 0.0000,
                'created_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $entryId,
                'account_id' => $this->revenueAccount->id,
                'debit_amount' => 0.0000,
                'credit_amount' => 1000.0000,
                'base_debit_amount' => 0.0000,
                'base_credit_amount' => 1000.0000,
                'created_at' => now(),
            ],
        ]);

        // 3. Post entry: draft -> posted
        DB::table('journal_entries')
            ->where('id', $entryId)
            ->update(['is_posted' => true]);

        $posted = DB::table('journal_entries')->where('id', $entryId)->first();
        $this->assertTrue((bool) $posted->is_posted);
    }

    public function test_g2_adding_modifying_or_deleting_line_after_posting_fails(): void
    {
        $entryId = (string) Str::uuid();

        DB::table('journal_entries')->insert([
            'id' => $entryId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->openPeriod->id,
            'entry_number' => 'JV-2026-2027-000003',
            'posting_date' => '2026-07-15',
            'business_date' => '2026-07-15',
            'reference_type' => 'manual',
            'is_posted' => false,
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $line1Id = (string) Str::uuid();
        DB::table('journal_lines')->insert([
            [
                'id' => $line1Id,
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $entryId,
                'account_id' => $this->cashAccount->id,
                'debit_amount' => 500.0000,
                'credit_amount' => 0.0000,
                'base_debit_amount' => 500.0000,
                'base_credit_amount' => 0.0000,
                'created_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $entryId,
                'account_id' => $this->revenueAccount->id,
                'debit_amount' => 0.0000,
                'credit_amount' => 500.0000,
                'base_debit_amount' => 0.0000,
                'base_credit_amount' => 500.0000,
                'created_at' => now(),
            ],
        ]);

        DB::table('journal_entries')->where('id', $entryId)->update(['is_posted' => true]);

        // 1. Attempt adding a line after posting
        try {
            DB::table('journal_lines')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $entryId,
                'account_id' => $this->cashAccount->id,
                'debit_amount' => 100.0000,
                'credit_amount' => 0.0000,
                'base_debit_amount' => 100.0000,
                'base_credit_amount' => 0.0000,
                'created_at' => now(),
            ]);
            $this->fail('Expected exception when adding line to posted journal');
        } catch (QueryException $e) {
            $this->assertMatchesRegularExpression('/(Cannot INSERT lines on an already posted Journal Entry|Audit Integrity Violation)/i', $e->getMessage());
        }

        // 2. Attempt updating a line on posted journal
        try {
            DB::table('journal_lines')
                ->where('id', $line1Id)
                ->update(['debit_amount' => 600.0000]);
            $this->fail('Expected exception when updating line on posted journal');
        } catch (QueryException $e) {
            $this->assertMatchesRegularExpression('/(Cannot UPDATE lines on an already posted Journal Entry|Audit Integrity Violation)/i', $e->getMessage());
        }

        // 3. Attempt deleting a line on posted journal
        try {
            DB::table('journal_lines')
                ->where('id', $line1Id)
                ->delete();
            $this->fail('Expected exception when deleting line on posted journal');
        } catch (QueryException $e) {
            $this->assertMatchesRegularExpression('/(Cannot DELETE lines on an already posted Journal Entry|Audit Integrity Violation)/i', $e->getMessage());
        }

        // 4. Attempt deleting the posted journal header
        try {
            DB::table('journal_entries')
                ->where('id', $entryId)
                ->delete();
            $this->fail('Expected exception when deleting posted journal entry');
        } catch (QueryException $e) {
            $this->assertMatchesRegularExpression('/(cannot be deleted. Issue a reversing entry|Audit Integrity Violation)/i', $e->getMessage());
        }
    }

    public function test_g3_posting_into_closed_period_fails(): void
    {
        $entryId = (string) Str::uuid();

        DB::table('journal_entries')->insert([
            'id' => $entryId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->closedPeriod->id, // Closed period
            'entry_number' => 'JV-2026-2027-000004',
            'posting_date' => '2026-08-15',
            'business_date' => '2026-08-15',
            'reference_type' => 'manual',
            'is_posted' => false,
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_lines')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $entryId,
                'account_id' => $this->cashAccount->id,
                'debit_amount' => 500.0000,
                'credit_amount' => 0.0000,
                'base_debit_amount' => 500.0000,
                'base_credit_amount' => 0.0000,
                'created_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $entryId,
                'account_id' => $this->revenueAccount->id,
                'debit_amount' => 0.0000,
                'credit_amount' => 500.0000,
                'base_debit_amount' => 0.0000,
                'base_credit_amount' => 500.0000,
                'created_at' => now(),
            ],
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(period that is not open|Financial Integrity Violation)/i');

        DB::table('journal_entries')->where('id', $entryId)->update(['is_posted' => true]);
    }

    public function test_g4_reversing_a_draft_fails(): void
    {
        $draftTargetId = (string) Str::uuid();

        // Target entry in draft state
        DB::table('journal_entries')->insert([
            'id' => $draftTargetId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->openPeriod->id,
            'entry_number' => 'JV-2026-2027-000005',
            'posting_date' => '2026-07-15',
            'business_date' => '2026-07-15',
            'reference_type' => 'manual',
            'is_posted' => false,
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reversalId = (string) Str::uuid();
        DB::table('journal_entries')->insert([
            'id' => $reversalId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->openPeriod->id,
            'entry_number' => 'REV-2026-2027-000001',
            'posting_date' => '2026-07-16',
            'business_date' => '2026-07-16',
            'reference_type' => 'reversal',
            'is_posted' => false,
            'is_reversal' => true,
            'reversed_entry_id' => $draftTargetId,
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_lines')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $reversalId,
                'account_id' => $this->revenueAccount->id,
                'debit_amount' => 500.0000,
                'credit_amount' => 0.0000,
                'base_debit_amount' => 500.0000,
                'base_credit_amount' => 0.0000,
                'created_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $reversalId,
                'account_id' => $this->cashAccount->id,
                'debit_amount' => 0.0000,
                'credit_amount' => 500.0000,
                'base_debit_amount' => 0.0000,
                'base_credit_amount' => 500.0000,
                'created_at' => now(),
            ],
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(Target of reversal entry % is not posted|Financial Integrity Violation)/i');

        DB::table('journal_entries')->where('id', $reversalId)->update(['is_posted' => true]);
    }

    public function test_g5_posting_unbalanced_entry_fails(): void
    {
        $entryId = (string) Str::uuid();

        DB::table('journal_entries')->insert([
            'id' => $entryId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->openPeriod->id,
            'entry_number' => 'JV-2026-2027-000006',
            'posting_date' => '2026-07-15',
            'business_date' => '2026-07-15',
            'reference_type' => 'manual',
            'is_posted' => false,
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Unbalanced: Debit 1000, Credit 800
        DB::table('journal_lines')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $entryId,
                'account_id' => $this->cashAccount->id,
                'debit_amount' => 1000.0000,
                'credit_amount' => 0.0000,
                'base_debit_amount' => 1000.0000,
                'base_credit_amount' => 0.0000,
                'created_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $entryId,
                'account_id' => $this->revenueAccount->id,
                'debit_amount' => 0.0000,
                'credit_amount' => 800.0000,
                'base_debit_amount' => 0.0000,
                'base_credit_amount' => 800.0000,
                'created_at' => now(),
            ],
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(is unbalanced|Debits .* != Credits|Financial Integrity Violation)/i');

        DB::table('journal_entries')->where('id', $entryId)->update(['is_posted' => true]);
    }

    public function test_posting_date_outside_period_bounds_fails(): void
    {
        $entryId = (string) Str::uuid();

        // Open period is July 1 to July 31, 2026. Posting date is August 5.
        DB::table('journal_entries')->insert([
            'id' => $entryId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->openPeriod->id,
            'entry_number' => 'JV-2026-2027-000007',
            'posting_date' => '2026-08-05', // Outside July period
            'business_date' => '2026-08-05',
            'reference_type' => 'manual',
            'is_posted' => false,
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_lines')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $entryId,
                'account_id' => $this->cashAccount->id,
                'debit_amount' => 500.0000,
                'credit_amount' => 0.0000,
                'base_debit_amount' => 500.0000,
                'base_credit_amount' => 0.0000,
                'created_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $entryId,
                'account_id' => $this->revenueAccount->id,
                'debit_amount' => 0.0000,
                'credit_amount' => 500.0000,
                'base_debit_amount' => 0.0000,
                'base_credit_amount' => 500.0000,
                'created_at' => now(),
            ],
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(Posting date .* is outside period bounds|Financial Integrity Violation)/i');

        DB::table('journal_entries')->where('id', $entryId)->update(['is_posted' => true]);
    }

    public function test_reversal_without_reversed_entry_id_fails_new_21(): void
    {
        $reversalId = (string) Str::uuid();

        DB::table('journal_entries')->insert([
            'id' => $reversalId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->openPeriod->id,
            'entry_number' => 'REV-2026-2027-000010',
            'posting_date' => '2026-07-16',
            'business_date' => '2026-07-16',
            'reference_type' => 'reversal',
            'is_posted' => false,
            'is_reversal' => true,
            'reversed_entry_id' => null, // Missing target!
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_lines')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $reversalId,
                'account_id' => $this->revenueAccount->id,
                'debit_amount' => 500.0000,
                'credit_amount' => 0.0000,
                'base_debit_amount' => 500.0000,
                'base_credit_amount' => 0.0000,
                'created_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $reversalId,
                'account_id' => $this->cashAccount->id,
                'debit_amount' => 0.0000,
                'credit_amount' => 500.0000,
                'base_debit_amount' => 0.0000,
                'base_credit_amount' => 500.0000,
                'created_at' => now(),
            ],
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(Reversal entry must specify reversed_entry_id|Financial Integrity Violation)/i');

        DB::table('journal_entries')->where('id', $reversalId)->update(['is_posted' => true]);
    }

    public function test_reversal_of_another_company_entry_fails_new_21_new_22(): void
    {
        // 1. Create Company B with its own period and posted entry
        $companyB = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Company B',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $fyB = FiscalYear::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $companyB->id,
            'title' => 'FY B 2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);

        $periodB = AccountingPeriod::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $companyB->id,
            'fiscal_year_id' => $fyB->id,
            'period_number' => 1,
            'period_name' => 'July 2026 B',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'open',
        ]);

        $acctB1 = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $companyB->id,
            'account_code' => '1010',
            'account_name' => 'Cash B',
            'account_type' => 'asset',
        ]);

        $acctB2 = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $companyB->id,
            'account_code' => '4010',
            'account_name' => 'Sales B',
            'account_type' => 'revenue',
        ]);

        $entryBId = (string) Str::uuid();
        DB::table('journal_entries')->insert([
            'id' => $entryBId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $companyB->id,
            'period_id' => $periodB->id,
            'entry_number' => 'JV-B-000001',
            'posting_date' => '2026-07-15',
            'business_date' => '2026-07-15',
            'reference_type' => 'manual',
            'is_posted' => false,
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_lines')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $companyB->id,
                'journal_entry_id' => $entryBId,
                'account_id' => $acctB1->id,
                'debit_amount' => 500.0000,
                'credit_amount' => 0.0000,
                'base_debit_amount' => 500.0000,
                'base_credit_amount' => 0.0000,
                'created_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $companyB->id,
                'journal_entry_id' => $entryBId,
                'account_id' => $acctB2->id,
                'debit_amount' => 0.0000,
                'credit_amount' => 500.0000,
                'base_debit_amount' => 0.0000,
                'base_credit_amount' => 500.0000,
                'created_at' => now(),
            ],
        ]);

        DB::table('journal_entries')->where('id', $entryBId)->update(['is_posted' => true]);

        // 2. Company A attempts to create a reversal entry referencing Company B's entry
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(foreign key|fk_journal_reversal|Cannot reverse an entry belonging to a different company|violates foreign key)/i');

        DB::table('journal_entries')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id, // Company A
            'period_id' => $this->openPeriod->id,
            'entry_number' => 'REV-A-000001',
            'posting_date' => '2026-07-16',
            'business_date' => '2026-07-16',
            'reference_type' => 'reversal',
            'is_posted' => false,
            'is_reversal' => true,
            'reversed_entry_id' => $entryBId, // Cross-company target!
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_unique_active_reversal_index_rejects_second_reversal_of_same_entry_new_21(): void
    {
        // 1. Post original entry
        $origId = (string) Str::uuid();
        DB::table('journal_entries')->insert([
            'id' => $origId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->openPeriod->id,
            'entry_number' => 'JV-2026-2027-ORIG',
            'posting_date' => '2026-07-15',
            'business_date' => '2026-07-15',
            'reference_type' => 'manual',
            'is_posted' => false,
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_lines')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $origId,
                'account_id' => $this->cashAccount->id,
                'debit_amount' => 500.0000,
                'credit_amount' => 0.0000,
                'base_debit_amount' => 500.0000,
                'base_credit_amount' => 0.0000,
                'created_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $origId,
                'account_id' => $this->revenueAccount->id,
                'debit_amount' => 0.0000,
                'credit_amount' => 500.0000,
                'base_debit_amount' => 0.0000,
                'base_credit_amount' => 500.0000,
                'created_at' => now(),
            ],
        ]);

        DB::table('journal_entries')->where('id', $origId)->update(['is_posted' => true]);

        // 2. First reversal entry succeeds
        $rev1Id = (string) Str::uuid();
        DB::table('journal_entries')->insert([
            'id' => $rev1Id,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->openPeriod->id,
            'entry_number' => 'REV-2026-2027-FIRST',
            'posting_date' => '2026-07-16',
            'business_date' => '2026-07-16',
            'reference_type' => 'reversal',
            'is_posted' => false,
            'is_reversal' => true,
            'reversed_entry_id' => $origId,
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Second reversal entry targeting the same entry is rejected by uq_unique_active_reversal
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(uq_unique_active_reversal|unique constraint|duplicate key)/i');

        DB::table('journal_entries')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->openPeriod->id,
            'entry_number' => 'REV-2026-2027-SECOND',
            'posting_date' => '2026-07-16',
            'business_date' => '2026-07-16',
            'reference_type' => 'reversal',
            'is_posted' => false,
            'is_reversal' => true,
            'reversed_entry_id' => $origId, // Same target!
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_valid_reversal_succeeds_new_21(): void
    {
        // 1. Post original entry
        $origId = (string) Str::uuid();
        DB::table('journal_entries')->insert([
            'id' => $origId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->openPeriod->id,
            'entry_number' => 'JV-ORIG-VALID',
            'posting_date' => '2026-07-15',
            'business_date' => '2026-07-15',
            'reference_type' => 'manual',
            'is_posted' => false,
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_lines')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $origId,
                'account_id' => $this->cashAccount->id,
                'debit_amount' => 750.0000,
                'credit_amount' => 0.0000,
                'base_debit_amount' => 750.0000,
                'base_credit_amount' => 0.0000,
                'created_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $origId,
                'account_id' => $this->revenueAccount->id,
                'debit_amount' => 0.0000,
                'credit_amount' => 750.0000,
                'base_debit_amount' => 0.0000,
                'base_credit_amount' => 750.0000,
                'created_at' => now(),
            ],
        ]);

        DB::table('journal_entries')->where('id', $origId)->update(['is_posted' => true]);

        // 2. Reversal entry with inverted debit/credit lines
        $reversalId = (string) Str::uuid();
        DB::table('journal_entries')->insert([
            'id' => $reversalId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->openPeriod->id,
            'entry_number' => 'REV-VALID-001',
            'posting_date' => '2026-07-16',
            'business_date' => '2026-07-16',
            'reference_type' => 'reversal',
            'is_posted' => false,
            'is_reversal' => true,
            'reversed_entry_id' => $origId,
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_lines')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $reversalId,
                'account_id' => $this->revenueAccount->id,
                'debit_amount' => 750.0000,
                'credit_amount' => 0.0000,
                'base_debit_amount' => 750.0000,
                'base_credit_amount' => 0.0000,
                'created_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $reversalId,
                'account_id' => $this->cashAccount->id,
                'debit_amount' => 0.0000,
                'credit_amount' => 750.0000,
                'base_debit_amount' => 0.0000,
                'base_credit_amount' => 750.0000,
                'created_at' => now(),
            ],
        ]);

        DB::table('journal_entries')->where('id', $reversalId)->update(['is_posted' => true]);

        $postedReversal = DB::table('journal_entries')->where('id', $reversalId)->first();
        $this->assertTrue((bool) $postedReversal->is_posted);
        $this->assertTrue((bool) $postedReversal->is_reversal);
        $this->assertSame($origId, $postedReversal->reversed_entry_id);
    }

    public function test_updating_posted_journal_entry_header_fails_new_02_new_03(): void
    {
        $entryId = (string) Str::uuid();

        DB::table('journal_entries')->insert([
            'id' => $entryId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->openPeriod->id,
            'entry_number' => 'JV-IMMUTABLE-HEADER',
            'posting_date' => '2026-07-15',
            'business_date' => '2026-07-15',
            'reference_type' => 'manual',
            'narration' => 'Original Narration',
            'is_posted' => false,
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_lines')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $entryId,
                'account_id' => $this->cashAccount->id,
                'debit_amount' => 500.0000,
                'credit_amount' => 0.0000,
                'base_debit_amount' => 500.0000,
                'base_credit_amount' => 0.0000,
                'created_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $entryId,
                'account_id' => $this->revenueAccount->id,
                'debit_amount' => 0.0000,
                'credit_amount' => 500.0000,
                'base_debit_amount' => 0.0000,
                'base_credit_amount' => 500.0000,
                'created_at' => now(),
            ],
        ]);

        DB::table('journal_entries')->where('id', $entryId)->update(['is_posted' => true]);

        // Attempting to modify header on posted entry
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(Audit Integrity Violation: Posted Journal Entry .* is immutable and cannot be updated)/i');

        DB::table('journal_entries')->where('id', $entryId)->update(['narration' => 'Tampered Narration']);
    }

    public function test_updating_journal_line_entry_id_or_company_id_fails_new_02_new_13(): void
    {
        $entry1Id = (string) Str::uuid();
        $entry2Id = (string) Str::uuid();

        DB::table('journal_entries')->insert([
            [
                'id' => $entry1Id,
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'period_id' => $this->openPeriod->id,
                'entry_number' => 'JV-LINE-MOVE-1',
                'posting_date' => '2026-07-15',
                'business_date' => '2026-07-15',
                'reference_type' => 'manual',
                'is_posted' => false,
                'posted_by' => $this->user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $entry2Id,
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'period_id' => $this->openPeriod->id,
                'entry_number' => 'JV-LINE-MOVE-2',
                'posting_date' => '2026-07-15',
                'business_date' => '2026-07-15',
                'reference_type' => 'manual',
                'is_posted' => false,
                'posted_by' => $this->user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $lineId = (string) Str::uuid();
        DB::table('journal_lines')->insert([
            'id' => $lineId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'journal_entry_id' => $entry1Id,
            'account_id' => $this->cashAccount->id,
            'debit_amount' => 500.0000,
            'credit_amount' => 0.0000,
            'base_debit_amount' => 500.0000,
            'base_credit_amount' => 0.0000,
            'created_at' => now(),
        ]);

        // 1. Moving line to another journal entry is rejected by trg_journal_lines_immutability
        try {
            DB::table('journal_lines')->where('id', $lineId)->update(['journal_entry_id' => $entry2Id]);
            $this->fail('Expected exception when reassigning journal_entry_id on a line');
        } catch (QueryException $e) {
            $this->assertMatchesRegularExpression('/(Cannot reassign journal_entry_id on a journal line|Financial Integrity Violation)/i', $e->getMessage());
        }

        // 2. Changing company_id on a journal line is rejected by trg_journal_lines_immutability
        try {
            DB::table('journal_lines')->where('id', $lineId)->update(['company_id' => (string) Str::uuid()]);
            $this->fail('Expected exception when changing company_id on a line');
        } catch (QueryException $e) {
            $this->assertMatchesRegularExpression('/(Cannot change company_id on a journal line|Financial Integrity Violation)/i', $e->getMessage());
        }
    }

    public function test_posting_into_soft_closed_period_fails_acc_03(): void
    {
        $softClosedPeriod = AccountingPeriod::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'period_number' => 3,
            'period_name' => 'September 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'soft-closed',
        ]);

        $entryId = (string) Str::uuid();
        DB::table('journal_entries')->insert([
            'id' => $entryId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $softClosedPeriod->id,
            'entry_number' => 'JV-SOFT-CLOSED-01',
            'posting_date' => '2026-09-15',
            'business_date' => '2026-09-15',
            'reference_type' => 'manual',
            'is_posted' => false,
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_lines')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $entryId,
                'account_id' => $this->cashAccount->id,
                'debit_amount' => 500.0000,
                'credit_amount' => 0.0000,
                'base_debit_amount' => 500.0000,
                'base_credit_amount' => 0.0000,
                'created_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $entryId,
                'account_id' => $this->revenueAccount->id,
                'debit_amount' => 0.0000,
                'credit_amount' => 500.0000,
                'base_debit_amount' => 0.0000,
                'base_credit_amount' => 500.0000,
                'created_at' => now(),
            ],
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(Cannot post to an accounting period that is not open|Financial Integrity Violation)/i');

        DB::table('journal_entries')->where('id', $entryId)->update(['is_posted' => true]);
    }

    public function test_posting_entry_with_fewer_than_two_lines_fails_new_02(): void
    {
        $entryId = (string) Str::uuid();

        DB::table('journal_entries')->insert([
            'id' => $entryId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->openPeriod->id,
            'entry_number' => 'JV-ONE-LINE-FAIL',
            'posting_date' => '2026-07-15',
            'business_date' => '2026-07-15',
            'reference_type' => 'manual',
            'is_posted' => false,
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Only 1 line
        DB::table('journal_lines')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'journal_entry_id' => $entryId,
            'account_id' => $this->cashAccount->id,
            'debit_amount' => 500.0000,
            'credit_amount' => 0.0000,
            'base_debit_amount' => 500.0000,
            'base_credit_amount' => 0.0000,
            'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(must contain at least 2 lines to post|Financial Integrity Violation)/i');

        DB::table('journal_entries')->where('id', $entryId)->update(['is_posted' => true]);
    }

    public function test_journal_line_check_constraint_rejects_zero_or_both_debit_and_credit(): void
    {
        $entryId = (string) Str::uuid();

        DB::table('journal_entries')->insert([
            'id' => $entryId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->openPeriod->id,
            'entry_number' => 'JV-CHECK-CONSTRAINT',
            'posting_date' => '2026-07-15',
            'business_date' => '2026-07-15',
            'reference_type' => 'manual',
            'is_posted' => false,
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Both debit and credit = 0 is rejected by chk_debit_or_credit
        try {
            DB::table('journal_lines')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $entryId,
                'account_id' => $this->cashAccount->id,
                'debit_amount' => 0.0000,
                'credit_amount' => 0.0000,
                'created_at' => now(),
            ]);
            $this->fail('Expected exception for debit=0 and credit=0');
        } catch (QueryException $e) {
            $this->assertMatchesRegularExpression('/(chk_debit_or_credit|check constraint)/i', $e->getMessage());
        }

        // 2. Both debit > 0 and credit > 0 is rejected by chk_debit_or_credit
        try {
            DB::table('journal_lines')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'journal_entry_id' => $entryId,
                'account_id' => $this->cashAccount->id,
                'debit_amount' => 100.0000,
                'credit_amount' => 100.0000,
                'created_at' => now(),
            ]);
            $this->fail('Expected exception for debit > 0 and credit > 0');
        } catch (QueryException $e) {
            $this->assertMatchesRegularExpression('/(chk_debit_or_credit|check constraint)/i', $e->getMessage());
        }
    }

    public function test_draft_entry_and_lines_can_be_updated_and_deleted_allowed_path(): void
    {
        $entryId = (string) Str::uuid();

        DB::table('journal_entries')->insert([
            'id' => $entryId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->openPeriod->id,
            'entry_number' => 'JV-DRAFT-ALLOWED',
            'posting_date' => '2026-07-15',
            'business_date' => '2026-07-15',
            'reference_type' => 'manual',
            'narration' => 'Initial Draft',
            'is_posted' => false,
            'posted_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lineId = (string) Str::uuid();
        DB::table('journal_lines')->insert([
            'id' => $lineId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'journal_entry_id' => $entryId,
            'account_id' => $this->cashAccount->id,
            'debit_amount' => 300.0000,
            'credit_amount' => 0.0000,
            'base_debit_amount' => 300.0000,
            'base_credit_amount' => 0.0000,
            'created_at' => now(),
        ]);

        // Updating draft narration succeeds
        DB::table('journal_entries')->where('id', $entryId)->update(['narration' => 'Revised Draft']);
        $entry = DB::table('journal_entries')->where('id', $entryId)->first();
        $this->assertSame('Revised Draft', $entry->narration);

        // Updating line amount on draft succeeds
        DB::table('journal_lines')->where('id', $lineId)->update(['debit_amount' => 400.0000, 'base_debit_amount' => 400.0000]);
        $line = DB::table('journal_lines')->where('id', $lineId)->first();
        $this->assertEquals('400.0000', $line->debit_amount);

        // Deleting line on draft succeeds
        $deletedLine = DB::table('journal_lines')->where('id', $lineId)->delete();
        $this->assertSame(1, $deletedLine);

        // Deleting draft entry succeeds
        $deletedEntry = DB::table('journal_entries')->where('id', $entryId)->delete();
        $this->assertSame(1, $deletedEntry);
    }
}
