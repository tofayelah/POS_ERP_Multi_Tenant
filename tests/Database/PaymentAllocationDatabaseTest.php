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
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SubledgerOpenItem;
use App\Models\TaxCode;
use App\Models\TaxCodeRate;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Accounting\Subledger\Contracts\CustomerCreditCheckerContract;
use App\Modules\Accounting\Subledger\Contracts\PaymentServiceContract;
use App\Modules\Accounting\Subledger\DTOs\AllocationItemDTO;
use App\Modules\Accounting\Subledger\DTOs\RecordPaymentDTO;
use App\Modules\Accounting\Subledger\Exceptions\AllocationExceedsOpenItemException;
use App\Modules\Accounting\Subledger\Exceptions\AllocationExceedsPaymentException;
use App\Modules\Tax\DTOs\InvoiceLineItemDTO;
use App\Modules\Tax\DTOs\IssueInvoiceRequest;
use App\Modules\Tax\Services\InvoiceIssuer;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentAllocationDatabaseTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected User $user;

    protected FiscalYear $fiscalYear;

    protected AccountingPeriod $period;

    protected Customer $customer;

    protected ProductVariant $variant;

    protected InvoiceIssuer $invoiceIssuer;

    protected PaymentServiceContract $paymentService;

    protected CustomerCreditCheckerContract $creditChecker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Allocation Test Tenant',
            'subdomain' => 'alloc-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Allocation Retail Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Gulshan Branch',
            'code' => 'GUL-01',
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Cashier 2',
            'email' => 'cashier-'.Str::random(6).'@example.com',
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
            'account_name' => 'Accounts Receivable (Debtors)',
            'account_type' => 'asset',
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
            'account_code' => '2020',
            'account_name' => 'Output VAT Payable',
            'account_type' => 'liability',
        ]);

        // Customer with large credit limit (৳100,000)
        $this->customer = Customer::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Dhaka Departmental Store',
            'phone' => '+8801711002299',
            'credit_limit' => '100000.0000',
            'is_active' => true,
        ]);

        $taxCode = TaxCode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'ZERO',
            'name_en' => 'Zero Rated VAT',
            'description' => 'Zero Rated VAT',
        ]);

        TaxCodeRate::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'tax_code_id' => $taxCode->id,
            'rate_percent' => '0.00',
            'supplementary_duty_percent' => '0.00',
            'effective_from' => '2020-01-01',
            'effective_to' => null,
            'is_active' => true,
        ]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PROD-ALLOC-01',
            'name_en' => 'Wholesale Carton',
            'base_uom' => 'Ctn',
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'tax_code_id' => $taxCode->id,
            'sku' => 'SKU-CTN-01',
            'name_en' => 'Wholesale Carton 10kg',
            'base_uom' => 'Ctn',
            'mrp' => '1000.0000',
            'cost_price' => '800.0000',
            'is_active' => true,
        ]);

        $this->invoiceIssuer = app(InvoiceIssuer::class);
        $this->paymentService = app(PaymentServiceContract::class);
        $this->creditChecker = app(CustomerCreditCheckerContract::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_single_payment_can_be_allocated_across_multiple_invoices_with_partial_payment(): void
    {
        // 1. Issue Invoice 1: ৳10,000 (10 cartons @ ৳1000)
        $inv1 = $this->invoiceIssuer->issue(new IssueInvoiceRequest(
            company: $this->company,
            branch: $this->branch,
            postedBy: $this->user->id,
            lines: [new InvoiceLineItemDTO($this->variant, 10, '1000.00', 'Wholesale Carton')],
            customer: $this->customer,
            paymentStatus: 'credit'
        ));

        // 2. Issue Invoice 2: ৳15,000 (15 cartons @ ৳1000)
        $inv2 = $this->invoiceIssuer->issue(new IssueInvoiceRequest(
            company: $this->company,
            branch: $this->branch,
            postedBy: $this->user->id,
            lines: [new InvoiceLineItemDTO($this->variant, 15, '1000.00', 'Wholesale Carton')],
            customer: $this->customer,
            paymentStatus: 'credit'
        ));

        // 3. Issue Invoice 3: ৳25,000 (25 cartons @ ৳1000)
        $inv3 = $this->invoiceIssuer->issue(new IssueInvoiceRequest(
            company: $this->company,
            branch: $this->branch,
            postedBy: $this->user->id,
            lines: [new InvoiceLineItemDTO($this->variant, 25, '1000.00', 'Wholesale Carton')],
            customer: $this->customer,
            paymentStatus: 'credit'
        ));

        $this->assertEquals('50000.00', (string) $this->creditChecker->getCurrentDue($this->company->id, $this->customer->id)->getBigDecimal());

        $openItem1 = SubledgerOpenItem::where('source_id', $inv1->id)->firstOrFail();
        $openItem2 = SubledgerOpenItem::where('source_id', $inv2->id)->firstOrFail();
        $openItem3 = SubledgerOpenItem::where('source_id', $inv3->id)->firstOrFail();

        // 4. Record single inbound payment of ৳30,000 via bKash
        $paymentDTO = new RecordPaymentDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            paymentType: 'inbound',
            paymentMethod: 'bkash',
            partyType: 'customer',
            partyId: $this->customer->id,
            paymentDate: Carbon::today()->toDateString(),
            amount: '30000.0000',
            reference: 'TRX-BKASH-998877',
            postedBy: $this->user->id,
            allocations: [
                new AllocationItemDTO(openItemId: $openItem1->id, amount: '10000.0000', notes: 'Full pay Inv 1'),
                new AllocationItemDTO(openItemId: $openItem2->id, amount: '15000.0000', notes: 'Full pay Inv 2'),
                new AllocationItemDTO(openItemId: $openItem3->id, amount: '5000.0000', notes: 'Partial pay Inv 3'),
            ]
        );

        $payment = $this->paymentService->recordPayment($paymentDTO);

        $this->assertEquals('posted', $payment->status);
        $this->assertEquals('30000.0000', (string) $payment->amount);
        $this->assertNotNull($payment->gl_journal_entry_id);

        // 5. Verify open item balances
        $openItem1->refresh();
        $this->assertEquals('10000.0000', (string) $openItem1->allocated_amount);
        $this->assertEquals('0.0000', (string) $openItem1->remaining_amount);
        $this->assertEquals('fully_allocated', $openItem1->status);

        $openItem2->refresh();
        $this->assertEquals('15000.0000', (string) $openItem2->allocated_amount);
        $this->assertEquals('0.0000', (string) $openItem2->remaining_amount);
        $this->assertEquals('fully_allocated', $openItem2->status);

        $openItem3->refresh();
        $this->assertEquals('5000.0000', (string) $openItem3->allocated_amount);
        $this->assertEquals('20000.0000', (string) $openItem3->remaining_amount);
        $this->assertEquals('partially_allocated', $openItem3->status);

        // 6. Verify Customer's current due dynamically recalculated to ৳20,000
        $this->assertEquals('20000.00', (string) $this->creditChecker->getCurrentDue($this->company->id, $this->customer->id)->getBigDecimal());

        // 7. Verify GL Double Entry Journal was posted atomically
        $journal = JournalEntry::where('id', $payment->gl_journal_entry_id)->with('lines')->firstOrFail();
        $this->assertTrue($journal->is_posted);

        $lines = $journal->lines;
        $this->assertCount(2, $lines);

        $debitLine = $lines->firstWhere('debit_amount', '30000.0000');
        $creditLine = $lines->firstWhere('credit_amount', '30000.0000');

        $this->assertNotNull($debitLine);
        $this->assertNotNull($creditLine);

        // Debit should be Bank / Cash / MFS (1020/1010)
        // Credit should be Accounts Receivable (1030)
        $arAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '1030')->firstOrFail();
        $this->assertEquals($arAccount->id, $creditLine->account_id);
    }

    public function test_allocation_cannot_exceed_open_item_remaining_amount(): void
    {
        // 1. Issue Invoice: ৳10,000
        $inv = $this->invoiceIssuer->issue(new IssueInvoiceRequest(
            company: $this->company,
            branch: $this->branch,
            postedBy: $this->user->id,
            lines: [new InvoiceLineItemDTO($this->variant, 10, '1000.00', 'Wholesale Carton')],
            customer: $this->customer,
            paymentStatus: 'credit'
        ));

        $openItem = SubledgerOpenItem::where('source_id', $inv->id)->firstOrFail();

        // 2. Attempt to allocate ৳12,000 against a ৳10,000 open item
        $paymentDTO = new RecordPaymentDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            paymentType: 'inbound',
            paymentMethod: 'bank',
            partyType: 'customer',
            partyId: $this->customer->id,
            paymentDate: Carbon::today()->toDateString(),
            amount: '12000.0000',
            reference: 'CHQ-1234',
            postedBy: $this->user->id,
            allocations: [
                new AllocationItemDTO(openItemId: $openItem->id, amount: '12000.0000'),
            ]
        );

        $this->expectException(AllocationExceedsOpenItemException::class);
        $this->paymentService->recordPayment($paymentDTO);
    }

    public function test_allocation_cannot_exceed_payment_amount(): void
    {
        // 1. Issue Invoice: ৳20,000
        $inv = $this->invoiceIssuer->issue(new IssueInvoiceRequest(
            company: $this->company,
            branch: $this->branch,
            postedBy: $this->user->id,
            lines: [new InvoiceLineItemDTO($this->variant, 20, '1000.00', 'Wholesale Carton')],
            customer: $this->customer,
            paymentStatus: 'credit'
        ));

        $openItem = SubledgerOpenItem::where('source_id', $inv->id)->firstOrFail();

        // 2. Payment is only ৳10,000, but allocation specifies ৳15,000
        $paymentDTO = new RecordPaymentDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            paymentType: 'inbound',
            paymentMethod: 'cash',
            partyType: 'customer',
            partyId: $this->customer->id,
            paymentDate: Carbon::today()->toDateString(),
            amount: '10000.0000',
            reference: 'CASH-REC',
            postedBy: $this->user->id,
            allocations: [
                new AllocationItemDTO(openItemId: $openItem->id, amount: '15000.0000'),
            ]
        );

        $this->expectException(AllocationExceedsPaymentException::class);
        $this->paymentService->recordPayment($paymentDTO);
    }
}
