<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\SalesInvoice;
use App\Models\SubledgerOpenItem;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VdsCertificate;
use App\Modules\Accounting\Subledger\Contracts\VdsCertificateContract;
use App\Modules\Accounting\Subledger\DTOs\CustomerVdsDeductionDTO;
use App\Modules\Accounting\Subledger\DTOs\SupplierVdsWithholdingDTO;
use Carbon\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class VdsWithholdingCertificateTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected User $user;

    protected FiscalYear $fiscalYear;

    protected AccountingPeriod $period;

    protected VdsCertificateContract $vdsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'VDS Compliance Tenant',
            'subdomain' => 'vds-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Bengal Retail Conglomerate Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Dhanmondi Flagship',
            'code' => 'DHM-01',
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Tax Accountant',
            'email' => 'tax-'.Str::random(6).'@example.com',
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

        // COA Setup
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
            'account_code' => '1060',
            'account_name' => 'VDS Receivable (Customer Withholding)',
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
            'account_name' => 'VDS Payable (Supplier Withholding to NBR)',
            'account_type' => 'liability',
        ]);

        $this->vdsService = app(VdsCertificateContract::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_customer_payment_with_vds_deduction_creates_mushak_6_6_and_clears_open_item_and_posts_gl(): void
    {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Apex Footwear Ltd',
            'phone' => '+8801811223344',
            'customer_bin' => '1122334455667',
            'credit_limit' => '100000.0000',
            'is_active' => true,
        ]);

        $invoice = SalesInvoice::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-00860',
            'invoice_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '829.7100',
            'total_vat' => '30.2900',
            'grand_total' => '860.0000',
            'tendered_amount' => '0.0000',
            'change_amount' => '0.0000',
            'status' => 'issued',
            'fiscal_year' => $this->fiscalYear->title,
        ]);

        $openItem = SubledgerOpenItem::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'party_type' => 'customer',
            'party_id' => $customer->id,
            'source_type' => 'sales_invoice',
            'source_id' => $invoice->id,
            'document_number' => $invoice->invoice_number,
            'document_date' => $invoice->invoice_date,
            'due_date' => $invoice->due_date,
            'direction' => 'debit',
            'gl_control_account_code' => '1030',
            'original_amount' => '860.0000',
            'allocated_amount' => '0.0000',
            'remaining_amount' => '860.0000',
            'status' => 'open',
        ]);

        // Customer pays ৳829.71 into bank, withholding ৳30.29 VDS with Mushak 6.6 certificate # VDS-2026-0042
        $dto = new CustomerVdsDeductionDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            customerId: $customer->id,
            salesInvoiceId: $invoice->id,
            certificateNumber: 'VDS-2026-0042',
            withholdingEntityName: 'Apex Footwear Ltd',
            withholdingEntityBin: '1122334455667',
            issueDate: Carbon::today()->toDateString(),
            taxPeriod: Carbon::today()->format('F Y'),
            totalInvoiceAmount: '860.0000',
            totalVatAmount: '30.2900',
            vdsAmount: '30.2900',
            netPaymentAmount: '829.7100',
            paymentMethod: 'bank',
            reference: 'EFT-APEX-8849',
            createdBy: $this->user->id
        );

        $certificate = $this->vdsService->recordCustomerVdsDeduction($dto);

        // 1. Assert VdsCertificate record
        $this->assertInstanceOf(VdsCertificate::class, $certificate);
        $this->assertEquals('received_from_customer', $certificate->direction);
        $this->assertEquals('VDS-2026-0042', $certificate->certificate_number);
        $this->assertEquals('860.0000', (string) $certificate->total_invoice_amount);
        $this->assertEquals('30.2900', (string) $certificate->vds_amount);
        $this->assertEquals('valid', $certificate->status);
        $this->assertEquals($invoice->id, $certificate->sales_invoice_id);
        $this->assertNotNull($certificate->payment_id);

        // 2. Assert Payment record
        $this->assertDatabaseHas('payments', [
            'id' => $certificate->payment_id,
            'payment_type' => 'inbound',
            'payment_method' => 'bank',
            'party_id' => $customer->id,
            'amount' => '829.7100',
            'status' => 'posted',
        ]);

        // 3. Assert Subledger Open Item is fully allocated
        $openItem->refresh();
        $this->assertEquals('860.0000', (string) $openItem->allocated_amount);
        $this->assertEquals('0.0000', (string) $openItem->remaining_amount);
        $this->assertEquals('fully_allocated', $openItem->status);

        // 4. Assert GL Journal Entry is atomically posted and balanced
        $journal = JournalEntry::where('reference_type', 'vds_certificate')
            ->where('reference_id', $certificate->id)
            ->with('lines')
            ->firstOrFail();

        $this->assertTrue($journal->is_posted);
        $lines = $journal->lines;
        $this->assertCount(3, $lines);

        // Line 1: Debit Bank (1020) ৳829.71
        $bankLine = $lines->firstWhere('account.account_code', '1020');
        $this->assertNotNull($bankLine);
        $this->assertEquals('829.7100', (string) $bankLine->debit_amount);
        $this->assertEquals('0.0000', (string) $bankLine->credit_amount);

        // Line 2: Debit VDS Receivable (1060) ৳30.29
        $vdsLine = $lines->firstWhere('account.account_code', '1060');
        $this->assertNotNull($vdsLine);
        $this->assertEquals('30.2900', (string) $vdsLine->debit_amount);
        $this->assertEquals('0.0000', (string) $vdsLine->credit_amount);

        // Line 3: Credit Accounts Receivable (1030) ৳860.00
        $arLine = $lines->firstWhere('account.account_code', '1030');
        $this->assertNotNull($arLine);
        $this->assertEquals('0.0000', (string) $arLine->debit_amount);
        $this->assertEquals('860.0000', (string) $arLine->credit_amount);

        // Double entry check: Total Debits == Total Credits == ৳860.00
        $this->assertEquals('860.0000', (string) $journal->total_debit);
        $this->assertEquals('860.0000', (string) $journal->total_credit);
    }

    public function test_supplier_payment_with_vds_withholding_issues_mushak_6_6_and_clears_ap_and_posts_gl(): void
    {
        $supplier = Supplier::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Prime Packaging Solutions',
            'code' => 'SUP-PRIME',
            'supplier_bin' => '5566778899001',
            'created_by' => $this->user->id,
            'is_active' => true,
        ]);

        $bill = SupplierBill::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'bill_number' => 'BILL-2026-9901',
            'bill_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '85000.0000',
            'tax_amount' => '15000.0000',
            'total_amount' => '100000.0000',
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $openItem = SubledgerOpenItem::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'party_type' => 'supplier',
            'party_id' => $supplier->id,
            'source_type' => 'supplier_bill',
            'source_id' => $bill->id,
            'document_number' => $bill->bill_number,
            'document_date' => $bill->bill_date,
            'due_date' => $bill->due_date,
            'direction' => 'credit',
            'gl_control_account_code' => '2010',
            'original_amount' => '100000.0000',
            'allocated_amount' => '0.0000',
            'remaining_amount' => '100000.0000',
            'status' => 'open',
        ]);

        // Issue ৳85,000 via bank payment, withhold ৳15,000 VDS, generate Mushak 6.6
        $dto = new SupplierVdsWithholdingDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            supplierId: $supplier->id,
            supplierBillId: $bill->id,
            certificateNumber: 'VDS-OUT-2026-0001',
            issueDate: Carbon::today()->toDateString(),
            taxPeriod: Carbon::today()->format('F Y'),
            totalBillAmount: '100000.0000',
            totalVatAmount: '15000.0000',
            vdsAmount: '15000.0000',
            netPaymentAmount: '85000.0000',
            paymentMethod: 'bank',
            reference: 'CHQ-991023',
            createdBy: $this->user->id
        );

        $certificate = $this->vdsService->issueSupplierVdsCertificate($dto);

        // 1. Assert Certificate
        $this->assertInstanceOf(VdsCertificate::class, $certificate);
        $this->assertEquals('issued_to_supplier', $certificate->direction);
        $this->assertEquals('VDS-OUT-2026-0001', $certificate->certificate_number);
        $this->assertEquals('100000.0000', (string) $certificate->total_invoice_amount);
        $this->assertEquals('15000.0000', (string) $certificate->vds_amount);
        $this->assertEquals('valid', $certificate->status);
        $this->assertEquals($bill->id, $certificate->supplier_bill_id);
        $this->assertNotNull($certificate->payment_id);

        // 2. Assert Payment record
        $this->assertDatabaseHas('payments', [
            'id' => $certificate->payment_id,
            'payment_type' => 'outbound',
            'payment_method' => 'bank',
            'party_id' => $supplier->id,
            'amount' => '85000.0000',
            'status' => 'posted',
        ]);

        // 3. Assert Subledger Open Item is fully allocated
        $openItem->refresh();
        $this->assertEquals('100000.0000', (string) $openItem->allocated_amount);
        $this->assertEquals('0.0000', (string) $openItem->remaining_amount);
        $this->assertEquals('fully_allocated', $openItem->status);

        // 4. Assert GL Journal Entry is atomically posted and balanced
        $journal = JournalEntry::where('reference_type', 'vds_certificate')
            ->where('reference_id', $certificate->id)
            ->with('lines')
            ->firstOrFail();

        $this->assertTrue($journal->is_posted);
        $lines = $journal->lines;
        $this->assertCount(3, $lines);

        // Line 1: Debit Accounts Payable (2010) ৳100,000
        $apLine = $lines->firstWhere('account.account_code', '2010');
        $this->assertNotNull($apLine);
        $this->assertEquals('100000.0000', (string) $apLine->debit_amount);
        $this->assertEquals('0.0000', (string) $apLine->credit_amount);

        // Line 2: Credit Bank Current Account (1020) ৳85,000
        $bankLine = $lines->firstWhere('account.account_code', '1020');
        $this->assertNotNull($bankLine);
        $this->assertEquals('0.0000', (string) $bankLine->debit_amount);
        $this->assertEquals('85000.0000', (string) $bankLine->credit_amount);

        // Line 3: Credit VDS Payable (2040) ৳15,000
        $vdsLine = $lines->firstWhere('account.account_code', '2040');
        $this->assertNotNull($vdsLine);
        $this->assertEquals('0.0000', (string) $vdsLine->debit_amount);
        $this->assertEquals('15000.0000', (string) $vdsLine->credit_amount);

        // Double entry balance
        $this->assertEquals('100000.0000', (string) $journal->total_debit);
        $this->assertEquals('100000.0000', (string) $journal->total_credit);
    }

    public function test_vds_deduction_cannot_exceed_open_item_balance(): void
    {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Over-Deductor Corp',
            'phone' => '+8801700000000',
            'is_active' => true,
        ]);

        $invoice = SalesInvoice::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-OVER',
            'invoice_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '1000.0000',
            'total_vat' => '50.0000',
            'grand_total' => '1050.0000',
            'tendered_amount' => '0.0000',
            'change_amount' => '0.0000',
            'status' => 'issued',
            'fiscal_year' => $this->fiscalYear->title,
        ]);

        SubledgerOpenItem::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'party_type' => 'customer',
            'party_id' => $customer->id,
            'source_type' => 'sales_invoice',
            'source_id' => $invoice->id,
            'document_number' => $invoice->invoice_number,
            'document_date' => $invoice->invoice_date,
            'due_date' => $invoice->due_date,
            'direction' => 'debit',
            'gl_control_account_code' => '1030',
            'original_amount' => '1050.0000',
            'allocated_amount' => '0.0000',
            'remaining_amount' => '1050.0000',
            'status' => 'open',
        ]);

        // Attempting to settle ৳1,000 net + ৳200 VDS = ৳1,200 (exceeds ৳1,050)
        $dto = new CustomerVdsDeductionDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            customerId: $customer->id,
            salesInvoiceId: $invoice->id,
            certificateNumber: 'VDS-INVALID-01',
            withholdingEntityName: 'Over-Deductor Corp',
            withholdingEntityBin: '1234567890123',
            issueDate: Carbon::today()->toDateString(),
            taxPeriod: Carbon::today()->format('F Y'),
            totalInvoiceAmount: '1050.0000',
            totalVatAmount: '50.0000',
            vdsAmount: '200.0000',
            netPaymentAmount: '1000.0000',
            paymentMethod: 'bank',
            reference: 'REF-OVER',
            createdBy: $this->user->id
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/exceeds remaining open balance/i');

        $this->vdsService->recordCustomerVdsDeduction($dto);
    }
}
