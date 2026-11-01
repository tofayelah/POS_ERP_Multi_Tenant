<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\SubledgerOpenItem;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Accounting\Subledger\Contracts\SupplierPaymentVoucherContract;
use App\Modules\Accounting\Subledger\DTOs\AllocationItemDTO;
use App\Modules\Accounting\Subledger\DTOs\CreateSupplierVoucherDTO;
use App\Modules\Accounting\Subledger\Exceptions\SegregationOfDutiesException;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierPaymentSodTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected User $userA; // Purchasing clerk

    protected User $userB; // Finance manager / Tenant owner

    protected User $userC; // Master data admin

    protected FiscalYear $fiscalYear;

    protected AccountingPeriod $period;

    protected Supplier $supplier;

    protected SupplierBill $bill;

    protected SubledgerOpenItem $openItem;

    protected SupplierPaymentVoucherContract $voucherService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'SoD Test Tenant',
            'subdomain' => 'sod-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'SoD Trading Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Motijheel Branch',
            'code' => 'MOT-01',
        ]);

        $this->userA = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Purchasing Clerk (User A)',
            'email' => 'clerk-'.Str::random(6).'@example.com',
            'password' => 'password',
        ]);

        $this->userB = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Finance Manager (User B)',
            'email' => 'manager-'.Str::random(6).'@example.com',
            'password' => 'password',
        ]);

        $this->userC = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Master Data Admin (User C)',
            'email' => 'admin-'.Str::random(6).'@example.com',
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

        // Standard GL Accounts
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
            'account_code' => '2010',
            'account_name' => 'Accounts Payable (Creditors)',
            'account_type' => 'liability',
        ]);

        // Supplier created by User C
        $this->supplier = Supplier::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Bengal Imports Corp',
            'code' => 'SUP-BENGAL',
            'phone' => '+8801999888777',
            'supplier_bin' => '9876543210987',
            'credit_period_days' => 30,
            'created_by' => $this->userC->id,
            'is_active' => true,
        ]);

        // Supplier Bill for ৳50,000 created by User A
        $this->bill = SupplierBill::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'BILL-2026-0042',
            'bill_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '50000.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '50000.0000',
            'status' => 'approved',
            'created_by' => $this->userA->id,
        ]);

        // Open item for AP (credit)
        $this->openItem = SubledgerOpenItem::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'party_type' => 'supplier',
            'party_id' => $this->supplier->id,
            'source_type' => 'supplier_bill',
            'source_id' => $this->bill->id,
            'document_number' => $this->bill->bill_number,
            'document_date' => $this->bill->bill_date,
            'due_date' => $this->bill->due_date,
            'direction' => 'credit',
            'gl_control_account_code' => '2010',
            'original_amount' => '50000.0000',
            'allocated_amount' => '0.0000',
            'remaining_amount' => '50000.0000',
            'status' => 'open',
        ]);

        $this->voucherService = app(SupplierPaymentVoucherContract::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_same_user_cannot_create_and_approve_supplier_payment(): void
    {
        // 1. User A (Purchasing Clerk) drafts payment voucher for ৳50,000
        $dto = new CreateSupplierVoucherDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            supplierId: $this->supplier->id,
            paymentMethod: 'bank',
            amount: '50000.0000',
            paymentDate: Carbon::today()->toDateString(),
            reference: 'TRANSFER-001',
            createdBy: $this->userA->id,
            allocations: [
                new AllocationItemDTO(openItemId: $this->openItem->id, amount: '50000.0000'),
            ]
        );

        $voucher = $this->voucherService->createVoucher($dto);

        $this->assertEquals('pending_approval', $voucher->status);
        $this->assertEquals($this->userA->id, $voucher->created_by);
        $this->assertNull($voucher->approved_by);
        $this->assertNull($voucher->gl_journal_entry_id);

        // 2. User A attempts to approve their own voucher -> Must throw SegregationOfDutiesException (SoD-01)
        $this->expectException(SegregationOfDutiesException::class);
        $this->expectExceptionMessageMatches('/creator cannot approve/i');

        $this->voucherService->approveVoucher($voucher->id, $this->userA->id);
    }

    public function test_user_who_created_supplier_cannot_approve_payment(): void
    {
        // User A drafts payment voucher
        $dto = new CreateSupplierVoucherDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            supplierId: $this->supplier->id,
            paymentMethod: 'bank',
            amount: '50000.0000',
            paymentDate: Carbon::today()->toDateString(),
            reference: 'TRANSFER-002',
            createdBy: $this->userA->id,
            allocations: [
                new AllocationItemDTO(openItemId: $this->openItem->id, amount: '50000.0000'),
            ]
        );

        $voucher = $this->voucherService->createVoucher($dto);

        // User C (who created the supplier record) attempts to approve -> Must throw SegregationOfDutiesException (SoD-02)
        $this->expectException(SegregationOfDutiesException::class);
        $this->expectExceptionMessageMatches('/supplier/i');

        $this->voucherService->approveVoucher($voucher->id, $this->userC->id);
    }

    public function test_independent_manager_can_approve_supplier_payment_and_posts_gl(): void
    {
        // User A drafts payment voucher
        $dto = new CreateSupplierVoucherDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            supplierId: $this->supplier->id,
            paymentMethod: 'bank',
            amount: '50000.0000',
            paymentDate: Carbon::today()->toDateString(),
            reference: 'TRANSFER-003',
            createdBy: $this->userA->id,
            allocations: [
                new AllocationItemDTO(openItemId: $this->openItem->id, amount: '50000.0000'),
            ]
        );

        $voucher = $this->voucherService->createVoucher($dto);

        // User B (Finance Manager who neither created the voucher nor created the supplier) approves
        $approvedVoucher = $this->voucherService->approveVoucher($voucher->id, $this->userB->id);

        $this->assertEquals('posted', $approvedVoucher->status);
        $this->assertEquals($this->userB->id, $approvedVoucher->approved_by);
        $this->assertNotNull($approvedVoucher->approved_at);
        $this->assertNotNull($approvedVoucher->gl_journal_entry_id);

        // Verify Open Item is fully allocated
        $this->openItem->refresh();
        $this->assertEquals('50000.0000', (string) $this->openItem->allocated_amount);
        $this->assertEquals('0.0000', (string) $this->openItem->remaining_amount);
        $this->assertEquals('fully_allocated', $this->openItem->status);

        // Verify GL Double Entry Journal was posted atomically
        $journal = JournalEntry::where('id', $approvedVoucher->gl_journal_entry_id)->with('lines')->firstOrFail();
        $this->assertTrue($journal->is_posted);

        $lines = $journal->lines;
        $this->assertCount(2, $lines);

        $debitLine = $lines->firstWhere('debit_amount', '50000.0000');
        $creditLine = $lines->firstWhere('credit_amount', '50000.0000');

        $this->assertNotNull($debitLine);
        $this->assertNotNull($creditLine);

        // Debit should be Accounts Payable (2010)
        // Credit should be Bank Current Account (1020)
        $apAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '2010')->firstOrFail();
        $bankAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '1020')->firstOrFail();

        $this->assertEquals($apAccount->id, $debitLine->account_id);
        $this->assertEquals($bankAccount->id, $creditLine->account_id);
    }
}
