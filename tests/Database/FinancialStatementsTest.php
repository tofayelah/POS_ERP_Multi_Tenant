<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\SalesInvoice;
use App\Models\SubledgerOpenItem;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Accounting\Contracts\AccountingPostingServiceContract;
use App\Modules\Accounting\Contracts\FinancialReportContract;
use App\Modules\Accounting\DTOs\JournalEntryDTO;
use App\Modules\Accounting\DTOs\JournalLineDTO;
use App\Modules\Accounting\Subledger\Contracts\SubledgerAgingContract;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialStatementsTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected User $user;

    protected FiscalYear $fiscalYear;

    protected AccountingPeriod $period;

    protected SubledgerAgingContract $agingService;

    protected FinancialReportContract $reportService;

    protected AccountingPostingServiceContract $postingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Financial Reporting Tenant',
            'subdomain' => 'fin-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Premier Holdings Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Gulshan Corporate Office',
            'code' => 'GUL-01',
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Chief Accountant',
            'email' => 'ca-'.Str::random(6).'@example.com',
            'password' => 'password',
        ]);

        $currentYear = Carbon::now()->year;
        $this->fiscalYear = FiscalYear::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'title' => "FY {$currentYear}-".($currentYear + 1),
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->addYear()->endOfMonth()->toDateString(),
        ]);

        $this->period = AccountingPeriod::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'period_number' => Carbon::now()->month,
            'period_name' => Carbon::now()->format('F Y'),
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->endOfMonth()->toDateString(),
            'status' => 'open',
        ]);

        // Standard Chart of Accounts
        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1010',
            'account_name' => 'Cash in Hand',
            'account_type' => 'asset',
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1020',
            'account_name' => 'Bank Current Account',
            'account_type' => 'asset',
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1030',
            'account_name' => 'Accounts Receivable',
            'account_type' => 'asset',
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '2010',
            'account_name' => 'Accounts Payable',
            'account_type' => 'liability',
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '2040',
            'account_name' => 'VDS Payable',
            'account_type' => 'liability',
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '3010',
            'account_name' => 'Share Capital',
            'account_type' => 'equity',
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '4010',
            'account_name' => 'Sales Revenue',
            'account_type' => 'revenue',
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '5010',
            'account_name' => 'Cost of Goods Sold',
            'account_type' => 'expense',
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '6010',
            'account_name' => 'Administrative Salaries',
            'account_type' => 'expense',
        ]);

        $this->agingService = app(SubledgerAgingContract::class);
        $this->reportService = app(FinancialReportContract::class);
        $this->postingService = app(AccountingPostingServiceContract::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_receivables_aging_buckets_match_open_items_and_reconcile_to_gl(): void
    {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Aging Test Customer',
            'phone' => '+8801755667788',
            'credit_limit' => '1000000.0000',
            'is_active' => true,
        ]);

        $today = Carbon::today();

        // 5 Invoices with distinct aging:
        // 1. Due today -> 0 days overdue (0-30 bucket) ৳10,000
        // 2. Due 45 days ago -> (31-60 bucket) ৳20,000
        // 3. Due 75 days ago -> (61-90 bucket) ৳30,000
        // 4. Due 105 days ago -> (91-120 bucket) ৳40,000
        // 5. Due 150 days ago -> (120+ bucket) ৳50,000
        $invoicesData = [
            ['days_ago' => 0, 'amount' => '10000.0000'],
            ['days_ago' => 45, 'amount' => '20000.0000'],
            ['days_ago' => 75, 'amount' => '30000.0000'],
            ['days_ago' => 105, 'amount' => '40000.0000'],
            ['days_ago' => 150, 'amount' => '50000.0000'],
        ];

        $arAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '1030')->firstOrFail();
        $revenueAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '4010')->firstOrFail();

        foreach ($invoicesData as $idx => $inv) {
            $dueDate = $today->copy()->subDays($inv['days_ago'])->toDateString();
            $invId = (string) Str::uuid();

            SalesInvoice::create([
                'id' => $invId,
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'branch_id' => $this->branch->id,
                'customer_id' => $customer->id,
                'invoice_number' => 'INV-AGE-'.($idx + 1),
                'invoice_date' => $dueDate,
                'business_date' => $dueDate,
                'subtotal' => $inv['amount'],
                'discount_amount' => '0.0000',
                'supplementary_duty_amount' => '0.0000',
                'tax_amount' => '0.0000',
                'total_payable' => $inv['amount'],
                'payment_status' => 'unpaid',
                'status' => 'issued',
            ]);

            SubledgerOpenItem::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'party_type' => 'customer',
                'party_id' => $customer->id,
                'source_type' => 'sales_invoice',
                'source_id' => $invId,
                'document_number' => 'INV-AGE-'.($idx + 1),
                'document_date' => $dueDate,
                'due_date' => $dueDate,
                'direction' => 'debit',
                'gl_control_account_code' => '1030',
                'original_amount' => $inv['amount'],
                'allocated_amount' => '0.0000',
                'remaining_amount' => $inv['amount'],
                'status' => 'open',
            ]);

            DB::transaction(function () use ($today, $invId, $idx, $inv, $arAccount, $revenueAccount) {
                $this->postingService->post(new JournalEntryDTO(
                    companyId: $this->company->id,
                    periodId: $this->period->id,
                    documentType: 'INV',
                    postingDate: $today->toDateString(),
                    businessDate: $today->toDateString(),
                    referenceType: 'sales_invoice',
                    referenceId: $invId,
                    postedBy: $this->user->id,
                    branchId: $this->branch->id,
                    narration: 'Invoice #'.($idx + 1),
                    lines: [
                        new JournalLineDTO(
                            accountId: $arAccount->id,
                            debitAmount: Money::of($inv['amount']),
                            creditAmount: Money::zero('BDT'),
                            branchId: $this->branch->id,
                        ),
                        new JournalLineDTO(
                            accountId: $revenueAccount->id,
                            debitAmount: Money::zero('BDT'),
                            creditAmount: Money::of($inv['amount']),
                            branchId: $this->branch->id,
                        ),
                    ]
                ));
            });
        }

        // Run Aging Report
        $agingReport = $this->agingService->getReceivablesAging($this->company->id, $today->toDateString());

        $this->assertEquals('10000.0000', (string) $agingReport->summary->days0To30->getBigDecimal());
        $this->assertEquals('20000.0000', (string) $agingReport->summary->days31To60->getBigDecimal());
        $this->assertEquals('30000.0000', (string) $agingReport->summary->days61To90->getBigDecimal());
        $this->assertEquals('40000.0000', (string) $agingReport->summary->days91To120->getBigDecimal());
        $this->assertEquals('50000.0000', (string) $agingReport->summary->daysOver120->getBigDecimal());
        $this->assertEquals('150000.0000', (string) $agingReport->summary->total->getBigDecimal());

        // Assert customer details match
        $this->assertCount(1, $agingReport->details);
        $customerAging = $agingReport->details[0];
        $this->assertEquals($customer->id, $customerAging->partyId);
        $this->assertEquals('150000.0000', (string) $customerAging->buckets->total->getBigDecimal());
    }

    public function test_trial_balance_debits_strictly_equal_credits(): void
    {
        $today = Carbon::today()->toDateString();
        $bankAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '1020')->firstOrFail();
        $equityAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '3010')->firstOrFail();

        // Capital Injection: Debit Bank (1020) ৳500,000, Credit Share Capital (3010) ৳500,000
        DB::transaction(function () use ($today, $bankAccount, $equityAccount) {
            $this->postingService->post(new JournalEntryDTO(
                companyId: $this->company->id,
                periodId: $this->period->id,
                documentType: 'JV',
                postingDate: $today,
                businessDate: $today,
                referenceType: 'capital_injection',
                referenceId: null,
                postedBy: $this->user->id,
                branchId: $this->branch->id,
                narration: 'Initial Paid up capital',
                lines: [
                    new JournalLineDTO(
                        accountId: $bankAccount->id,
                        debitAmount: Money::of('500000.0000'),
                        creditAmount: Money::zero('BDT'),
                        branchId: $this->branch->id,
                    ),
                    new JournalLineDTO(
                        accountId: $equityAccount->id,
                        debitAmount: Money::zero('BDT'),
                        creditAmount: Money::of('500000.0000'),
                        branchId: $this->branch->id,
                    ),
                ]
            ));
        });

        $trialBalance = $this->reportService->getTrialBalance($this->company->id, $this->period->id);

        $this->assertTrue($trialBalance->isBalanced);
        $this->assertTrue($trialBalance->totalDebit->equals($trialBalance->totalCredit));
        $this->assertEquals('500000.0000', (string) $trialBalance->totalDebit->getBigDecimal());
        $this->assertEquals('500000.0000', (string) $trialBalance->totalCredit->getBigDecimal());
    }

    public function test_profit_and_loss_and_balance_sheet_reports(): void
    {
        $today = Carbon::today()->toDateString();
        $bankAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '1020')->firstOrFail();
        $arAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '1030')->firstOrFail();
        $apAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '2010')->firstOrFail();
        $vdsAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '2040')->firstOrFail();
        $equityAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '3010')->firstOrFail();
        $revenueAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '4010')->firstOrFail();
        $cogsAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '5010')->firstOrFail();
        $salaryAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '6010')->firstOrFail();

        // 1. Initial Share Capital: Debit Bank ৳35,000 / Credit Equity ৳35,000
        DB::transaction(function () use ($today, $bankAccount, $equityAccount) {
            $this->postingService->post(new JournalEntryDTO(
                companyId: $this->company->id,
                periodId: $this->period->id,
                documentType: 'JV',
                postingDate: $today,
                businessDate: $today,
                referenceType: 'capital',
                referenceId: null,
                postedBy: $this->user->id,
                branchId: $this->branch->id,
                lines: [
                    new JournalLineDTO(accountId: $bankAccount->id, debitAmount: Money::of('35000.0000'), creditAmount: Money::zero('BDT')),
                    new JournalLineDTO(accountId: $equityAccount->id, debitAmount: Money::zero('BDT'), creditAmount: Money::of('35000.0000')),
                ]
            ));
        });

        // 2. Sales Revenue: Debit AR ৳30,000, Debit Bank ৳70,000 / Credit Revenue ৳100,000
        DB::transaction(function () use ($today, $arAccount, $bankAccount, $revenueAccount) {
            $this->postingService->post(new JournalEntryDTO(
                companyId: $this->company->id,
                periodId: $this->period->id,
                documentType: 'INV',
                postingDate: $today,
                businessDate: $today,
                referenceType: 'sales',
                referenceId: null,
                postedBy: $this->user->id,
                branchId: $this->branch->id,
                lines: [
                    new JournalLineDTO(accountId: $arAccount->id, debitAmount: Money::of('30000.0000'), creditAmount: Money::zero('BDT')),
                    new JournalLineDTO(accountId: $bankAccount->id, debitAmount: Money::of('70000.0000'), creditAmount: Money::zero('BDT')),
                    new JournalLineDTO(accountId: $revenueAccount->id, debitAmount: Money::zero('BDT'), creditAmount: Money::of('100000.0000')),
                ]
            ));
        });

        // 3. Purchase / COGS: Debit COGS ৳60,000 / Credit AP ৳20,000, Credit Bank ৳35,000, Credit VDS Payable ৳5,000
        DB::transaction(function () use ($today, $cogsAccount, $apAccount, $bankAccount, $vdsAccount) {
            $this->postingService->post(new JournalEntryDTO(
                companyId: $this->company->id,
                periodId: $this->period->id,
                documentType: 'BILL',
                postingDate: $today,
                businessDate: $today,
                referenceType: 'cogs',
                referenceId: null,
                postedBy: $this->user->id,
                branchId: $this->branch->id,
                lines: [
                    new JournalLineDTO(accountId: $cogsAccount->id, debitAmount: Money::of('60000.0000'), creditAmount: Money::zero('BDT')),
                    new JournalLineDTO(accountId: $apAccount->id, debitAmount: Money::zero('BDT'), creditAmount: Money::of('20000.0000')),
                    new JournalLineDTO(accountId: $bankAccount->id, debitAmount: Money::zero('BDT'), creditAmount: Money::of('35000.0000')),
                    new JournalLineDTO(accountId: $vdsAccount->id, debitAmount: Money::zero('BDT'), creditAmount: Money::of('5000.0000')),
                ]
            ));
        });

        // 4. Operating Expense: Debit Admin Salaries ৳15,000 / Credit Bank ৳15,000
        DB::transaction(function () use ($today, $salaryAccount, $bankAccount) {
            $this->postingService->post(new JournalEntryDTO(
                companyId: $this->company->id,
                periodId: $this->period->id,
                documentType: 'PV',
                postingDate: $today,
                businessDate: $today,
                referenceType: 'expense',
                referenceId: null,
                postedBy: $this->user->id,
                branchId: $this->branch->id,
                lines: [
                    new JournalLineDTO(accountId: $salaryAccount->id, debitAmount: Money::of('15000.0000'), creditAmount: Money::zero('BDT')),
                    new JournalLineDTO(accountId: $bankAccount->id, debitAmount: Money::zero('BDT'), creditAmount: Money::of('15000.0000')),
                ]
            ));
        });

        // 5. Test Profit & Loss Statement
        $pnl = $this->reportService->getProfitAndLoss($this->company->id, $this->period->id);
        $this->assertEquals('100000.0000', (string) $pnl->totalRevenue->getBigDecimal());
        $this->assertEquals('60000.0000', (string) $pnl->totalCogs->getBigDecimal());
        $this->assertEquals('40000.0000', (string) $pnl->grossProfit->getBigDecimal());
        $this->assertEquals('15000.0000', (string) $pnl->totalExpenses->getBigDecimal());
        $this->assertEquals('25000.0000', (string) $pnl->netIncome->getBigDecimal());

        // 6. Test Balance Sheet
        // Assets: Bank (35k + 70k - 35k - 15k = 55k) + AR (30k) = 85,000
        // Liabilities: AP (20k) + VDS Payable (5k) = 25,000
        // Equity: Share Capital (35k) + Net Income (25k) = 60,000
        // Liabilities + Equity = 25,000 + 60,000 = 85,000!
        $bs = $this->reportService->getBalanceSheet($this->company->id, $this->period->id);
        $this->assertEquals('85000.0000', (string) $bs->totalAssets->getBigDecimal());
        $this->assertEquals('25000.0000', (string) $bs->totalLiabilities->getBigDecimal());
        $this->assertEquals('35000.0000', (string) $bs->totalEquity->getBigDecimal());
        $this->assertEquals('25000.0000', (string) $bs->currentPeriodNetIncome->getBigDecimal());
        $this->assertEquals('85000.0000', (string) $bs->totalLiabilitiesAndEquity->getBigDecimal());
        $this->assertTrue($bs->isBalanced);
    }
}
