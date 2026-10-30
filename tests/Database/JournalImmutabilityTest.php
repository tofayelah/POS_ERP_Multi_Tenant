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
            'password' => bcrypt('password'),
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
}
