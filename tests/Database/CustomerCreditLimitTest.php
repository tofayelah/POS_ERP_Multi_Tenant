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
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxCode;
use App\Models\TaxCodeRate;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Accounting\Subledger\Contracts\CustomerCreditCheckerContract;
use App\Modules\Accounting\Subledger\Exceptions\CreditLimitExceededException;
use App\Modules\Accounting\Subledger\Services\CustomerCreditChecker;
use App\Modules\Tax\DTOs\InvoiceLineItemDTO;
use App\Modules\Tax\DTOs\IssueInvoiceRequest;
use App\Modules\Tax\Services\InvoiceIssuer;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerCreditLimitTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected User $user;

    protected FiscalYear $fiscalYear;

    protected AccountingPeriod $period;

    protected Customer $customer;

    protected ProductVariant $variant;

    protected TaxCode $taxCode;

    protected CustomerCreditCheckerContract $creditChecker;

    protected InvoiceIssuer $invoiceIssuer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Credit Limit Test Tenant',
            'subdomain' => 'baki-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Baki Retail Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Dhanmondi Branch',
            'code' => 'DHA-01',
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Cashier 1',
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

        // Customer with ৳10,000 credit limit
        $this->customer = Customer::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Anisur Rahman',
            'phone' => '+8801811223344',
            'credit_limit' => '10000.0000',
            'is_active' => true,
        ]);

        // Tax code: Exempt / 0%
        $this->taxCode = TaxCode::create([
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
            'tax_code_id' => $this->taxCode->id,
            'rate_percent' => '0.00',
            'supplementary_duty_percent' => '0.00',
            'effective_from' => '2020-01-01',
            'effective_to' => null,
            'is_active' => true,
        ]);

        // Catalog Product Variant
        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Standard Commodity',
            'type' => 'standard',
            'tax_code_id' => $this->taxCode->id,
        ]);

        $this->variant = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'sku' => 'SKU-COMM-01',
            'name' => 'Standard Commodity 1kg',
            'retail_price' => '100.00',
            'wholesale_price' => '80.00',
            'is_active' => true,
        ]);

        $this->creditChecker = app(CustomerCreditCheckerContract::class);
        $this->invoiceIssuer = app(InvoiceIssuer::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_customer_initial_due_is_zero(): void
    {
        $due = $this->creditChecker->getCurrentDue($this->company->id, $this->customer->id);
        $this->assertEquals('0.00', (string) $due->getBigDecimal());
    }

    public function test_credit_sale_within_limit_succeeds_and_creates_subledger_open_item(): void
    {
        // Credit sale of ৳8,000 (80 units @ ৳100)
        $line = new InvoiceLineItemDTO(
            variant: $this->variant,
            quantity: 80,
            unitPrice: '100.00',
            itemDescription: 'Standard Commodity 1kg'
        );

        $request = new IssueInvoiceRequest(
            company: $this->company,
            branch: $this->branch,
            postedBy: $this->user->id,
            lines: [$line],
            customer: $this->customer,
            paymentStatus: 'credit'
        );

        $invoice = $this->invoiceIssuer->issue($request);

        $this->assertEquals('issued', $invoice->status);
        $this->assertEquals('credit', $invoice->payment_status);
        $this->assertEquals('8000.0000', (string) $invoice->total_payable);

        // Check that subledger open item was created
        $openItem = \App\Models\SubledgerOpenItem::where('source_id', $invoice->id)->first();
        $this->assertNotNull($openItem);
        $this->assertEquals('8000.0000', (string) $openItem->original_amount);
        $this->assertEquals('8000.0000', (string) $openItem->remaining_amount);
        $this->assertEquals('open', $openItem->status);

        // Current due should now be ৳8,000
        $currentDue = $this->creditChecker->getCurrentDue($this->company->id, $this->customer->id);
        $this->assertEquals('8000.00', (string) $currentDue->getBigDecimal());
    }

    public function test_credit_sale_exceeding_limit_is_refused(): void
    {
        // 1. Post initial credit sale of ৳8,000
        $line1 = new InvoiceLineItemDTO(
            variant: $this->variant,
            quantity: 80,
            unitPrice: '100.00',
            itemDescription: 'Standard Commodity 1kg'
        );

        $this->invoiceIssuer->issue(new IssueInvoiceRequest(
            company: $this->company,
            branch: $this->branch,
            postedBy: $this->user->id,
            lines: [$line1],
            customer: $this->customer,
            paymentStatus: 'credit'
        ));

        // 2. Attempt second credit sale of ৳2,500 (8000 + 2500 = 10500 > 10000 limit)
        $line2 = new InvoiceLineItemDTO(
            variant: $this->variant,
            quantity: 25,
            unitPrice: '100.00',
            itemDescription: 'Standard Commodity 1kg'
        );

        $this->expectException(CreditLimitExceededException::class);

        $this->invoiceIssuer->issue(new IssueInvoiceRequest(
            company: $this->company,
            branch: $this->branch,
            postedBy: $this->user->id,
            lines: [$line2],
            customer: $this->customer,
            paymentStatus: 'credit'
        ));
    }

    public function test_cash_sale_exceeding_credit_limit_is_allowed_because_not_credit(): void
    {
        // 1. Customer already has ৳8,000 due
        $line1 = new InvoiceLineItemDTO(
            variant: $this->variant,
            quantity: 80,
            unitPrice: '100.00',
            itemDescription: 'Standard Commodity 1kg'
        );

        $this->invoiceIssuer->issue(new IssueInvoiceRequest(
            company: $this->company,
            branch: $this->branch,
            postedBy: $this->user->id,
            lines: [$line1],
            customer: $this->customer,
            paymentStatus: 'credit'
        ));

        // 2. Sale of ৳5,000 tendered in CASH (paymentStatus = 'paid')
        $line2 = new InvoiceLineItemDTO(
            variant: $this->variant,
            quantity: 50,
            unitPrice: '100.00',
            itemDescription: 'Standard Commodity 1kg'
        );

        $cashInvoice = $this->invoiceIssuer->issue(new IssueInvoiceRequest(
            company: $this->company,
            branch: $this->branch,
            postedBy: $this->user->id,
            lines: [$line2],
            customer: $this->customer,
            paymentStatus: 'paid'
        ));

        $this->assertEquals('issued', $cashInvoice->status);
        $this->assertEquals('paid', $cashInvoice->payment_status);

        // Due remains ৳8,000 (cash sale didn't increase due)
        $currentDue = $this->creditChecker->getCurrentDue($this->company->id, $this->customer->id);
        $this->assertEquals('8000.00', (string) $currentDue->getBigDecimal());
    }
}
