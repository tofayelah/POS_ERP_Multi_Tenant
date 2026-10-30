<?php

namespace Tests\Unit;

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Accounting\Contracts\AccountingPostingServiceContract;
use App\Modules\Accounting\DTOs\JournalEntryDTO;
use App\Modules\Accounting\DTOs\JournalLineDTO;
use App\Modules\Accounting\Exceptions\PostingWithoutTransactionException;
use App\Modules\Accounting\Exceptions\UnbalancedJournalException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingPostingServiceTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected FiscalYear $fiscalYear;

    protected AccountingPeriod $period;

    protected ChartOfAccount $cashAccount;

    protected ChartOfAccount $salesAccount;

    protected User $user;

    protected AccountingPostingServiceContract $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Posting Service Tenant',
            'subdomain' => 'posting-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Retail Company',
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

        $this->period = AccountingPeriod::create([
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

        $this->cashAccount = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1010',
            'account_name' => 'Cash',
            'account_type' => 'asset',
        ]);

        $this->salesAccount = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '4010',
            'account_name' => 'Sales',
            'account_type' => 'revenue',
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Posting User',
            'email' => 'post-'.Str::random(6).'@example.com',
            'password' => 'password',
        ]);

        $this->service = app(AccountingPostingServiceContract::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_throws_exception_if_posting_without_caller_database_transaction(): void
    {
        $this->assertSame(0, DB::transactionLevel());

        $dto = new JournalEntryDTO(
            companyId: $this->company->id,
            periodId: $this->period->id,
            documentType: 'JV',
            postingDate: '2026-07-15',
            businessDate: '2026-07-15',
            referenceType: 'manual',
            referenceId: null,
            postedBy: $this->user->id,
            lines: [
                new JournalLineDTO(
                    accountId: $this->cashAccount->id,
                    debitAmount: Money::of('500.00'),
                    creditAmount: Money::zero(),
                ),
                new JournalLineDTO(
                    accountId: $this->salesAccount->id,
                    debitAmount: Money::zero(),
                    creditAmount: Money::of('500.00'),
                ),
            ]
        );

        $this->expectException(PostingWithoutTransactionException::class);
        $this->expectExceptionMessageMatches('/caller.*active.*transaction/i');

        $this->service->post($dto);
    }

    public function test_post_succeeds_within_caller_transaction(): void
    {
        $entry = DB::transaction(function () {
            $dto = new JournalEntryDTO(
                companyId: $this->company->id,
                periodId: $this->period->id,
                documentType: 'JV',
                postingDate: '2026-07-15',
                businessDate: '2026-07-15',
                referenceType: 'manual',
                referenceId: null,
                postedBy: $this->user->id,
                narration: 'Cash sale transaction',
                lines: [
                    new JournalLineDTO(
                        accountId: $this->cashAccount->id,
                        debitAmount: Money::of('1250.50'),
                        creditAmount: Money::zero(),
                        lineDescription: 'Debit cash',
                    ),
                    new JournalLineDTO(
                        accountId: $this->salesAccount->id,
                        debitAmount: Money::zero(),
                        creditAmount: Money::of('1250.50'),
                        lineDescription: 'Credit revenue',
                    ),
                ]
            );

            return $this->service->post($dto);
        });

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertTrue($entry->is_posted);
        $this->assertNotEmpty($entry->entry_number);
        $this->assertStringStartsWith('JV-', $entry->entry_number);

        // Verify lines persisted in DB
        $lines = JournalLine::where('journal_entry_id', $entry->id)->get();
        $this->assertCount(2, $lines);
        $this->assertEquals('1250.5000', $lines[0]->debit_amount);
        $this->assertEquals('1250.5000', $lines[1]->credit_amount);
    }

    public function test_caller_transaction_rollback_reverts_journal_and_lines(): void
    {
        $entryId = null;

        try {
            DB::transaction(function () use (&$entryId) {
                $dto = new JournalEntryDTO(
                    companyId: $this->company->id,
                    periodId: $this->period->id,
                    documentType: 'JV',
                    postingDate: '2026-07-15',
                    businessDate: '2026-07-15',
                    referenceType: 'manual',
                    referenceId: null,
                    postedBy: $this->user->id,
                    lines: [
                        new JournalLineDTO(
                            accountId: $this->cashAccount->id,
                            debitAmount: Money::of('300.00'),
                            creditAmount: Money::zero(),
                        ),
                        new JournalLineDTO(
                            accountId: $this->salesAccount->id,
                            debitAmount: Money::zero(),
                            creditAmount: Money::of('300.00'),
                        ),
                    ]
                );

                $entry = $this->service->post($dto);
                $entryId = $entry->id;

                // Simulate caller failure
                throw new \RuntimeException('Business document failure - force abort');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('Business document failure - force abort', $e->getMessage());
        }

        $this->assertNotNull($entryId);
        $this->assertNull(JournalEntry::find($entryId));
        $this->assertCount(0, JournalLine::where('journal_entry_id', $entryId)->get());
    }

    public function test_rejects_unbalanced_entry_before_db_write(): void
    {
        $this->expectException(UnbalancedJournalException::class);
        $this->expectExceptionMessageMatches('/unbalanced/i');

        DB::transaction(function () {
            $dto = new JournalEntryDTO(
                companyId: $this->company->id,
                periodId: $this->period->id,
                documentType: 'JV',
                postingDate: '2026-07-15',
                businessDate: '2026-07-15',
                referenceType: 'manual',
                referenceId: null,
                postedBy: $this->user->id,
                lines: [
                    new JournalLineDTO(
                        accountId: $this->cashAccount->id,
                        debitAmount: Money::of('100.00'),
                        creditAmount: Money::zero(),
                    ),
                    new JournalLineDTO(
                        accountId: $this->salesAccount->id,
                        debitAmount: Money::zero(),
                        creditAmount: Money::of('90.00'),
                    ),
                ]
            );

            $this->service->post($dto);
        });
    }

    public function test_rejects_entry_with_fewer_than_two_lines(): void
    {
        $this->expectException(UnbalancedJournalException::class);
        $this->expectExceptionMessageMatches('/at least 2 lines/i');

        DB::transaction(function () {
            $dto = new JournalEntryDTO(
                companyId: $this->company->id,
                periodId: $this->period->id,
                documentType: 'JV',
                postingDate: '2026-07-15',
                businessDate: '2026-07-15',
                referenceType: 'manual',
                referenceId: null,
                postedBy: $this->user->id,
                lines: [
                    new JournalLineDTO(
                        accountId: $this->cashAccount->id,
                        debitAmount: Money::of('100.00'),
                        creditAmount: Money::zero(),
                    ),
                ]
            );

            $this->service->post($dto);
        });
    }
}
