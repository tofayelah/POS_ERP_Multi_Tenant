<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
use App\Models\BankStatement;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Accounting\Subledger\Contracts\BankReconciliationContract;
use App\Modules\Accounting\Subledger\DTOs\ImportBankStatementDTO;
use App\Modules\Accounting\Subledger\DTOs\ImportStatementLineDTO;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected User $user;

    protected FiscalYear $fiscalYear;

    protected AccountingPeriod $period;

    protected ChartOfAccount $bankAccount;

    protected Customer $customer;

    protected Supplier $supplier;

    protected BankReconciliationContract $reconService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Bank Recon Tenant',
            'subdomain' => 'brecon-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Recon Enterprise Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Corporate Branch',
            'code' => 'CORP-01',
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Recon Specialist',
            'email' => 'recon-'.Str::random(6).'@example.com',
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

        $this->bankAccount = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1020',
            'account_name' => 'Eastern Bank Corporate Account',
            'account_type' => 'asset',
        ]);

        $this->customer = Customer::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Dhaka Wholesale Ltd',
            'phone' => '+8801711002233',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'National Papers Ltd',
            'code' => 'SUP-PAPER',
            'created_by' => $this->user->id,
            'is_active' => true,
        ]);

        $this->reconService = app(BankReconciliationContract::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_bank_statement_import_and_auto_reconciliation_against_payments(): void
    {
        $today = Carbon::today()->toDateString();

        // 1. Create 3 Inbound Customer Payments (Deposits)
        $p1 = Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'payment_number' => 'CR-2026-001',
            'payment_type' => 'inbound',
            'payment_method' => 'bank',
            'party_type' => 'customer',
            'party_id' => $this->customer->id,
            'payment_date' => $today,
            'amount' => '15000.0000',
            'reference' => 'EFT-001',
            'status' => 'posted',
            'created_by' => $this->user->id,
        ]);

        $p2 = Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'payment_number' => 'CR-2026-002',
            'payment_type' => 'inbound',
            'payment_method' => 'bank',
            'party_type' => 'customer',
            'party_id' => $this->customer->id,
            'payment_date' => $today,
            'amount' => '25000.0000',
            'reference' => 'EFT-002',
            'status' => 'posted',
            'created_by' => $this->user->id,
        ]);

        $p3 = Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'payment_number' => 'CR-2026-003',
            'payment_type' => 'inbound',
            'payment_method' => 'bank',
            'party_type' => 'customer',
            'party_id' => $this->customer->id,
            'payment_date' => $today,
            'amount' => '10000.0000',
            'reference' => 'EFT-003',
            'status' => 'posted',
            'created_by' => $this->user->id,
        ]);

        // 2. Create 2 Outbound Supplier Payments (Withdrawals)
        $p4 = Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'payment_number' => 'PV-2026-001',
            'payment_type' => 'outbound',
            'payment_method' => 'bank',
            'party_type' => 'supplier',
            'party_id' => $this->supplier->id,
            'payment_date' => $today,
            'amount' => '20000.0000',
            'reference' => 'CHQ-501',
            'status' => 'posted',
            'created_by' => $this->user->id,
        ]);

        $p5 = Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'payment_number' => 'PV-2026-002',
            'payment_type' => 'outbound',
            'payment_method' => 'bank',
            'party_type' => 'supplier',
            'party_id' => $this->supplier->id,
            'payment_date' => $today,
            'amount' => '30000.0000',
            'reference' => 'CHQ-502',
            'status' => 'posted',
            'created_by' => $this->user->id,
        ]);

        // 3. Import Bank Statement with 6 lines (5 matching payments, 1 unmatched bank charge)
        $importDTO = new ImportBankStatementDTO(
            companyId: $this->company->id,
            chartOfAccountId: $this->bankAccount->id,
            statementDate: $today,
            openingBalance: '100000.0000',
            closingBalance: '99750.0000',
            lines: [
                new ImportStatementLineDTO(
                    lineDate: $today,
                    description: 'EFT Deposit Dhaka Wholesale',
                    referenceNumber: 'EFT-001',
                    depositAmount: '15000.0000',
                    withdrawalAmount: '0.0000',
                    balance: '115000.0000'
                ),
                new ImportStatementLineDTO(
                    lineDate: $today,
                    description: 'EFT Deposit Dhaka Wholesale',
                    referenceNumber: 'EFT-002',
                    depositAmount: '25000.0000',
                    withdrawalAmount: '0.0000',
                    balance: '140000.0000'
                ),
                new ImportStatementLineDTO(
                    lineDate: $today,
                    description: 'EFT Deposit Dhaka Wholesale',
                    referenceNumber: 'EFT-003',
                    depositAmount: '10000.0000',
                    withdrawalAmount: '0.0000',
                    balance: '150000.0000'
                ),
                new ImportStatementLineDTO(
                    lineDate: $today,
                    description: 'Cheque Payment National Papers',
                    referenceNumber: 'CHQ-501',
                    depositAmount: '0.0000',
                    withdrawalAmount: '20000.0000',
                    balance: '130000.0000'
                ),
                new ImportStatementLineDTO(
                    lineDate: $today,
                    description: 'Cheque Payment National Papers',
                    referenceNumber: 'CHQ-502',
                    depositAmount: '0.0000',
                    withdrawalAmount: '30000.0000',
                    balance: '100000.0000'
                ),
                new ImportStatementLineDTO(
                    lineDate: $today,
                    description: 'Monthly Maintenance Fee',
                    referenceNumber: 'CHARGE-NOV',
                    depositAmount: '0.0000',
                    withdrawalAmount: '250.0000',
                    balance: '99750.0000'
                ),
            ]
        );

        $statement = $this->reconService->importStatement($importDTO);

        $this->assertInstanceOf(BankStatement::class, $statement);
        $this->assertCount(6, $statement->lines);
        $this->assertEquals('pending', $statement->status);

        // 4. Run Auto-Match engine
        $matchedCount = $this->reconService->autoMatchStatement($statement->id);
        $this->assertEquals(5, $matchedCount);

        // Assert 5 matched lines
        $statement->refresh();
        $reconciledLines = $statement->lines()->where('is_reconciled', true)->get();
        $this->assertCount(5, $reconciledLines);

        $unreconciledLines = $statement->lines()->where('is_reconciled', false)->get();
        $this->assertCount(1, $unreconciledLines);
        $this->assertEquals('CHARGE-NOV', $unreconciledLines->first()->reference_number);

        // Assert payments updated to 'reconciled'
        $this->assertEquals('reconciled', $p1->fresh()->status);
        $this->assertEquals('reconciled', $p2->fresh()->status);
        $this->assertEquals('reconciled', $p3->fresh()->status);
        $this->assertEquals('reconciled', $p4->fresh()->status);
        $this->assertEquals('reconciled', $p5->fresh()->status);

        // 5. Generate Reconciliation Summary
        $summary = $this->reconService->getReconciliationSummary($statement->id);
        $this->assertEquals('99750.0000', (string) $summary->statementClosingBalance->getBigDecimal());
        $this->assertEquals('50000.0000', (string) $summary->clearedDepositsTotal->getBigDecimal());
        $this->assertEquals('50000.0000', (string) $summary->clearedWithdrawalsTotal->getBigDecimal());
        $this->assertEquals(1, $summary->unmatchedStatementLinesCount);
        $this->assertEquals('250.0000', (string) $summary->unmatchedStatementLinesTotal->getBigDecimal());
        $this->assertFalse($summary->isFullyReconciled);
    }

    public function test_manual_reconciliation_matching_and_unmatching(): void
    {
        $today = Carbon::today()->toDateString();

        $payment = Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'payment_number' => 'CR-MANUAL-001',
            'payment_type' => 'inbound',
            'payment_method' => 'bank',
            'party_type' => 'customer',
            'party_id' => $this->customer->id,
            'payment_date' => $today,
            'amount' => '45000.0000',
            'reference' => 'MANUAL-REF-99',
            'status' => 'posted',
            'created_by' => $this->user->id,
        ]);

        $importDTO = new ImportBankStatementDTO(
            companyId: $this->company->id,
            chartOfAccountId: $this->bankAccount->id,
            statementDate: $today,
            openingBalance: '50000.0000',
            closingBalance: '95000.0000',
            lines: [
                new ImportStatementLineDTO(
                    lineDate: $today,
                    description: 'Manual deposit transfer',
                    referenceNumber: 'MANUAL-REF-99',
                    depositAmount: '45000.0000',
                    withdrawalAmount: '0.0000',
                    balance: '95000.0000'
                ),
            ]
        );

        $statement = $this->reconService->importStatement($importDTO);
        $line = $statement->lines->first();

        // 1. Manual Match
        $matchedLine = $this->reconService->matchLine($line->id, $payment->id);
        $this->assertTrue($matchedLine->is_reconciled);
        $this->assertEquals($payment->id, $matchedLine->matched_payment_id);
        $this->assertEquals('reconciled', $payment->fresh()->status);

        // Statement should be marked reconciled since all lines are matched
        $this->assertEquals('reconciled', $statement->fresh()->status);

        // 2. Manual Unmatch
        $unmatchedLine = $this->reconService->unmatchLine($line->id);
        $this->assertFalse($unmatchedLine->is_reconciled);
        $this->assertNull($unmatchedLine->matched_payment_id);
        $this->assertEquals('posted', $payment->fresh()->status);
        $this->assertEquals('pending', $statement->fresh()->status);
    }
}
