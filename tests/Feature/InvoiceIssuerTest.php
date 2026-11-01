<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Tax\DTOs\InvoiceLineItemDTO;
use App\Modules\Tax\DTOs\IssueInvoiceRequest;
use App\Modules\Tax\DTOs\ReturnLineItemDTO;
use App\Modules\Tax\DTOs\ReturnInvoiceRequest;
use App\Modules\Tax\Services\InvoiceIssuer;
use App\Modules\Tax\Services\MushakReportService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceIssuerTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected Customer $customer;

    protected User $user;

    protected FiscalYear $fiscalYear;

    protected AccountingPeriod $period;

    protected ChartOfAccount $cashAccount;

    protected ChartOfAccount $salesAccount;

    protected ChartOfAccount $vatAccount;

    protected ChartOfAccount $sdAccount;

    protected ChartOfAccount $cogsAccount;

    protected ChartOfAccount $inventoryAccount;

    protected TaxCode $taxCodeZero;

    protected TaxCode $taxCodeFive;

    protected TaxCode $taxCodeFifteen;

    protected ProductVariant $riceVariant;

    protected ProductVariant $oilVariant;

    protected ProductVariant $soapVariant;

    protected InvoiceIssuer $invoiceIssuer;

    protected MushakReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Mushak Invoicing Tenant',
            'subdomain' => 'mushak-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Kazi Retail Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'DHK-01',
            'name' => 'Dhanmondi Flagship',
            'address' => 'House 42, Road 7, Dhanmondi, Dhaka',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Walk-in Retail Customer',
            'phone' => '01711000000',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Senior Cashier',
            'email' => 'cashier-'.Str::random(6).'@kcerp.com',
            'password' => 'secret123',
        ]);

        $this->fiscalYear = FiscalYear::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'title' => 'FY 2026-2027',
            'start_date' => Carbon::now()->startOfYear()->toDateString(),
            'end_date' => Carbon::now()->endOfYear()->toDateString(),
        ]);

        $this->period = AccountingPeriod::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'period_number' => 1,
            'period_name' => Carbon::now()->format('F Y'),
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->endOfMonth()->toDateString(),
            'status' => 'open',
        ]);

        // General Ledger Accounts (Asset, Revenue, Liability, Expense)
        $this->cashAccount = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1010',
            'account_name' => 'Cash on Hand',
            'account_type' => 'asset',
            'is_reconcilable' => true,
            'is_active' => true,
        ]);

        $this->salesAccount = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '4010',
            'account_name' => 'Retail Sales Revenue',
            'account_type' => 'revenue',
            'is_reconcilable' => false,
            'is_active' => true,
        ]);

        $this->vatAccount = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '2020',
            'account_name' => 'VAT Payable (Output VAT)',
            'account_type' => 'liability',
            'is_reconcilable' => true,
            'is_active' => true,
        ]);

        $this->sdAccount = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '2030',
            'account_name' => 'Supplementary Duty Payable',
            'account_type' => 'liability',
            'is_reconcilable' => true,
            'is_active' => true,
        ]);

        $this->cogsAccount = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '5010',
            'account_name' => 'Cost of Goods Sold',
            'account_type' => 'expense',
            'is_reconcilable' => false,
            'is_active' => true,
        ]);

        $this->inventoryAccount = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1040',
            'account_name' => 'Merchandise Inventory Asset',
            'account_type' => 'asset',
            'is_reconcilable' => true,
            'is_active' => true,
        ]);

        // Tax Codes
        $this->taxCodeZero = TaxCode::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'VAT-EXEMPT',
            'name_en' => 'VAT Exempt (0%)',
            'is_active' => true,
        ]);

        $this->taxCodeFive = TaxCode::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'VAT-5',
            'name_en' => 'Truncated VAT 5%',
            'is_active' => true,
        ]);

        $this->taxCodeFifteen = TaxCode::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'VAT-15',
            'name_en' => 'Standard VAT 15%',
            'is_active' => true,
        ]);

        // Products and Variants for the Basket Vector (Rice, Oil, Soap)
        $riceProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PROD-RICE',
            'name_en' => 'Miniket Rice 5kg',
            'base_uom' => 'Bag',
            'is_active' => true,
        ]);
        $this->riceVariant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $riceProduct->id,
            'tax_code_id' => $this->taxCodeZero->id,
            'sku' => 'SKU-RICE-5KG',
            'name_en' => 'Miniket Rice 5kg',
            'base_uom' => 'Bag',
            'mrp' => 450.0000,
            'cost_price' => 380.0000,
            'is_active' => true,
        ]);

        $oilProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PROD-OIL',
            'name_en' => 'Soybean Oil 1L',
            'base_uom' => 'Bottle',
            'is_active' => true,
        ]);
        $this->oilVariant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $oilProduct->id,
            'tax_code_id' => $this->taxCodeFive->id,
            'sku' => 'SKU-OIL-1L',
            'name_en' => 'Soybean Oil 1L',
            'base_uom' => 'Bottle',
            'mrp' => 280.0000,
            'cost_price' => 240.0000,
            'is_active' => true,
        ]);

        $soapProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PROD-SOAP',
            'name_en' => 'Bath Soap 100g',
            'base_uom' => 'Pcs',
            'is_active' => true,
        ]);
        $this->soapVariant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $soapProduct->id,
            'tax_code_id' => $this->taxCodeFifteen->id,
            'sku' => 'SKU-SOAP-100G',
            'name_en' => 'Bath Soap 100g',
            'base_uom' => 'Pcs',
            'mrp' => 65.0000,
            'cost_price' => 45.0000,
            'is_active' => true,
        ]);

        $this->invoiceIssuer = app(InvoiceIssuer::class);
        $this->reportService = app(MushakReportService::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_invoice_issuer_calculates_tax_draws_number_and_posts_balanced_gl_journal_in_same_transaction(): void
    {
        // Basket items: Rice 450 (0%), Oil 280 (5%), Soap 2x65 = 130 (15%)
        $lines = [
            new InvoiceLineItemDTO(
                variant: $this->riceVariant,
                itemDescription: 'Miniket Rice 5kg',
                quantity: '1',
                unitPrice: '450.00',
                taxCode: $this->taxCodeZero,
                uom: 'Bag',
                vatRatePercent: '0',
            ),
            new InvoiceLineItemDTO(
                variant: $this->oilVariant,
                itemDescription: 'Soybean Oil 1L',
                quantity: '1',
                unitPrice: '280.00',
                taxCode: $this->taxCodeFive,
                uom: 'Bottle',
                vatRatePercent: '5',
            ),
            new InvoiceLineItemDTO(
                variant: $this->soapVariant,
                itemDescription: 'Bath Soap 100g',
                quantity: '2',
                unitPrice: '65.00',
                taxCode: $this->taxCodeFifteen,
                uom: 'Pcs',
                vatRatePercent: '15',
            ),
        ];

        $request = new IssueInvoiceRequest(
            company: $this->company,
            branch: $this->branch,
            postedBy: $this->user->id,
            lines: $lines,
            customer: $this->customer,
            invoiceDate: Carbon::today(),
            pricingBasis: 'tax_inclusive',
        );

        $invoice = $this->invoiceIssuer->issue($request);

        // 1. Verify Sales Invoice Header
        $this->assertInstanceOf(SalesInvoice::class, $invoice);
        $this->assertSame('issued', $invoice->status);
        $this->assertStringStartsWith('SINV-', $invoice->invoice_number);
        $this->assertSame('829.7100', (string) $invoice->subtotal);
        $this->assertSame('30.2900', (string) $invoice->tax_amount);
        $this->assertSame('860.0000', (string) $invoice->total_payable);

        // 2. Verify Line Items
        $this->assertCount(3, $invoice->lines);
        $riceLine = $invoice->lines->firstWhere('item_description', 'Miniket Rice 5kg');
        $this->assertNotNull($riceLine);
        $this->assertSame('450.0000', (string) $riceLine->line_total);
        $this->assertSame('0.0000', (string) $riceLine->tax_amount);

        $oilLine = $invoice->lines->firstWhere('item_description', 'Soybean Oil 1L');
        $this->assertNotNull($oilLine);
        $this->assertSame('266.6700', (string) $oilLine->line_total);
        $this->assertSame('13.3300', (string) $oilLine->tax_amount);

        $soapLine = $invoice->lines->firstWhere('item_description', 'Bath Soap 100g');
        $this->assertNotNull($soapLine);
        $this->assertSame('113.0400', (string) $soapLine->line_total);
        $this->assertSame('16.9600', (string) $soapLine->tax_amount);

        // 3. Verify Atomic GL Journal Entry
        $journal = JournalEntry::where('reference_type', 'sales_invoice')
            ->where('reference_id', $invoice->id)
            ->with('lines')
            ->first();

        $this->assertNotNull($journal);
        $this->assertSame('posted', $journal->status);
        $this->assertSame('sales_invoice', $journal->document_type);

        // Assert Double-entry Balance
        $totalDebit = $journal->lines->sum('debit_amount');
        $totalCredit = $journal->lines->sum('credit_amount');
        $this->assertEquals('860.0000', number_format($totalDebit, 4, '.', ''));
        $this->assertEquals('860.0000', number_format($totalCredit, 4, '.', ''));

        // Check specific lines: Cash (Dr ৳860.00), Sales Revenue (Cr ৳829.71), VAT Payable (Cr ৳30.29)
        $cashLine = $journal->lines->firstWhere('account_id', $this->cashAccount->id);
        $this->assertNotNull($cashLine);
        $this->assertEquals('860.0000', (string) $cashLine->debit_amount);

        $salesLine = $journal->lines->firstWhere('account_id', $this->salesAccount->id);
        $this->assertNotNull($salesLine);
        $this->assertEquals('829.7100', (string) $salesLine->credit_amount);

        $vatLine = $journal->lines->firstWhere('account_id', $this->vatAccount->id);
        $this->assertNotNull($vatLine);
        $this->assertEquals('30.2900', (string) $vatLine->credit_amount);
    }

    public function test_returns_create_mushak_6_7_credit_note_and_reverses_tax_without_modifying_original_invoice(): void
    {
        // 1. Issue original invoice with 2 soaps (৳130.00 gross, net ৳113.04, vat ৳16.96)
        $request = new IssueInvoiceRequest(
            company: $this->company,
            branch: $this->branch,
            postedBy: $this->user->id,
            lines: [
                new InvoiceLineItemDTO(
                    variant: $this->soapVariant,
                    itemDescription: 'Bath Soap 100g',
                    quantity: '2',
                    unitPrice: '65.00',
                    taxCode: $this->taxCodeFifteen,
                    uom: 'Pcs',
                    vatRatePercent: '15',
                ),
            ],
            customer: $this->customer,
        );

        $invoice = $this->invoiceIssuer->issue($request);
        $originalLine = $invoice->lines->first();
        $this->assertNotNull($originalLine);

        // 2. Return 1 soap (50% return)
        $returnRequest = new ReturnInvoiceRequest(
            salesInvoiceId: $invoice->id,
            postedBy: $this->user->id,
            lines: [
                new ReturnLineItemDTO(
                    salesInvoiceLineId: $originalLine->id,
                    quantity: '1',
                    reason: 'Defective packaging',
                ),
            ],
            reason: 'Customer return defective item',
        );

        $creditNote = $this->invoiceIssuer->createReturn($returnRequest);

        // 3. Assert Mushak 6.7 Credit Note
        $this->assertInstanceOf(CreditNote::class, $creditNote);
        $this->assertSame('issued', $creditNote->status);
        $this->assertStringStartsWith('CN-', $creditNote->credit_note_number);
        $this->assertSame($creditNote->credit_note_number, $creditNote->mushak_6_7_number);
        $this->assertSame($invoice->id, $creditNote->sales_invoice_id);

        // Return amount for 1 soap: Subtotal ৳56.52, VAT ৳8.48, Total Refund ৳65.00
        $this->assertSame('56.5200', (string) $creditNote->subtotal);
        $this->assertSame('8.4800', (string) $creditNote->tax_amount);
        $this->assertSame('65.0000', (string) $creditNote->total_amount);

        // 4. Assert Original Sales Invoice is NEVER modified (Review NEW-27, ADR-017)
        $freshInvoice = SalesInvoice::with('lines')->findOrFail($invoice->id);
        $this->assertSame('issued', $freshInvoice->status);
        $this->assertSame('113.0400', (string) $freshInvoice->subtotal);
        $this->assertSame('16.9600', (string) $freshInvoice->tax_amount);
        $this->assertSame('130.0000', (string) $freshInvoice->total_payable);
        $this->assertCount(1, $freshInvoice->lines);
        $this->assertSame('2.0000', (string) $freshInvoice->lines->first()->quantity);

        // 5. Assert Reversing Double-Entry GL Journal
        $returnJournal = JournalEntry::where('reference_type', 'credit_note')
            ->where('reference_id', $creditNote->id)
            ->with('lines')
            ->first();

        $this->assertNotNull($returnJournal);
        $this->assertSame('posted', $returnJournal->status);
        $this->assertTrue($returnJournal->is_reversal);

        // Reversal debits Sales & VAT, credits Cash
        $retSalesLine = $returnJournal->lines->firstWhere('account_id', $this->salesAccount->id);
        $this->assertNotNull($retSalesLine);
        $this->assertEquals('56.5200', (string) $retSalesLine->debit_amount);

        $retVatLine = $returnJournal->lines->firstWhere('account_id', $this->vatAccount->id);
        $this->assertNotNull($retVatLine);
        $this->assertEquals('8.4800', (string) $retVatLine->debit_amount);

        $retCashLine = $returnJournal->lines->firstWhere('account_id', $this->cashAccount->id);
        $this->assertNotNull($retCashLine);
        $this->assertEquals('65.0000', (string) $retCashLine->credit_amount);
    }

    public function test_statutory_mushak_documents_and_registers(): void
    {
        // 1. Issue an invoice for reporting tests
        $invoice = $this->invoiceIssuer->issue(new IssueInvoiceRequest(
            company: $this->company,
            branch: $this->branch,
            postedBy: $this->user->id,
            lines: [
                new InvoiceLineItemDTO(
                    variant: $this->oilVariant,
                    itemDescription: 'Soybean Oil 1L',
                    quantity: '2',
                    unitPrice: '280.00',
                    taxCode: $this->taxCodeFive,
                    vatRatePercent: '5',
                ),
            ],
            customer: $this->customer,
        ));

        // 2. Render Mushak 6.3 A4 (English and Bangla)
        $html63A4En = $this->reportService->renderMushak63($invoice, 'a4', 'en');
        $this->assertStringContainsString('TAX INVOICE', $html63A4En);
        $this->assertStringContainsString('Mushak-6.3', $html63A4En);
        $this->assertStringContainsString($invoice->invoice_number, $html63A4En);

        $html63A4Bn = $this->reportService->renderMushak63($invoice, 'a4', 'bn');
        $this->assertStringContainsString('কর চালানপত্র', $html63A4Bn);
        $this->assertStringContainsString('ফরম মূসক-৬.৩', $html63A4Bn);

        // 3. Render Mushak 6.3 80mm
        $html63Pos = $this->reportService->renderMushak63($invoice, '80mm', 'bn');
        $this->assertStringContainsString('80mm', $html63Pos);
        $this->assertStringContainsString($invoice->invoice_number, $html63Pos);

        // 4. Test Mushak 6.7 Credit Note Rendering
        $creditNote = $this->invoiceIssuer->createReturn(new ReturnInvoiceRequest(
            salesInvoiceId: $invoice->id,
            postedBy: $this->user->id,
            lines: [
                new ReturnLineItemDTO(
                    salesInvoiceLineId: $invoice->lines->first()->id,
                    quantity: '1',
                ),
            ],
        ));

        $html67 = $this->reportService->renderMushak67CreditNote($creditNote, 'bn');
        $this->assertStringContainsString('ক্রেডিট নোট (ফরম মূসক-৬.৭)', $html67);
        $this->assertStringContainsString($creditNote->credit_note_number, $html67);

        // 5. Test Mushak 6.6 VDS Certificate
        $vdsHtml = $this->reportService->renderMushak66Vds([
            'certificate_number' => 'VDS-2026-001',
            'date' => '2026-11-01',
            'supplier_name' => 'Supplier Co Ltd',
            'supplier_bin' => '123456789-0101',
            'invoice_number' => 'INV-SUP-99',
            'invoice_date' => '2026-10-25',
            'total_value' => '50000.00',
            'vat_amount' => '7500.00',
            'vds_amount' => '2500.00',
        ]);
        $this->assertStringContainsString('উৎসে কর কর্তন সনদপত্র (ফরম মূসক-৬.৬)', $vdsHtml);
        $this->assertStringContainsString('VDS-2026-001', $vdsHtml);

        // 6. Test Mushak 6.8 Debit Note
        $debitNoteHtml = $this->reportService->renderMushak68DebitNote([
            'debit_note_number' => 'DN-2026-001',
            'date' => '2026-11-01',
            'original_invoice_number' => 'INV-SUP-99',
            'reason' => 'Price revision upwards',
            'additional_subtotal' => '5000.00',
            'additional_vat' => '750.00',
            'total_payable' => '5750.00',
        ]);
        $this->assertStringContainsString('ডেবিট নোট (ফরম মূসক-৬.৮)', $debitNoteHtml);
        $this->assertStringContainsString('DN-2026-001', $debitNoteHtml);

        // 7. Test Mushak 6.1 Purchase Book Structure
        $purchaseBook = $this->reportService->getMushak61PurchaseBook($this->company->id, '2026-01-01', '2026-12-31');
        $this->assertArrayHasKey('entries', $purchaseBook);
        $this->assertArrayHasKey('total_purchase_value', $purchaseBook);
        $this->assertArrayHasKey('total_input_vat', $purchaseBook);

        // 8. Test Mushak 6.2 Sales Book
        $salesBook = $this->reportService->getMushak62SalesBook($this->company->id, '2026-01-01', '2026-12-31');
        $this->assertArrayHasKey('entries', $salesBook);
        $this->assertArrayHasKey('vat_total', $salesBook);
        $this->assertGreaterThan(0, count($salesBook['entries']));

        // 9. Test Mushak 9.1 Monthly VAT Summary Figures
        $mushak91 = $this->reportService->getMushak91MonthlyFigures($this->company->id, Carbon::now());
        $this->assertArrayHasKey('standard_vat_sales', $mushak91);
        $this->assertArrayHasKey('truncated_vat_sales', $mushak91);
        $this->assertArrayHasKey('exempt_sales', $mushak91);
        $this->assertArrayHasKey('net_vat_payable', $mushak91);
    }

    public function test_register_vat_total_equals_gl_vat_payable_account_for_the_period(): void
    {
        // Issue 2 sales invoices with different tax codes
        $this->invoiceIssuer->issue(new IssueInvoiceRequest(
            company: $this->company,
            branch: $this->branch,
            postedBy: $this->user->id,
            lines: [
                new InvoiceLineItemDTO(
                    variant: $this->oilVariant,
                    itemDescription: 'Soybean Oil 1L',
                    quantity: '2',
                    unitPrice: '280.00',
                    taxCode: $this->taxCodeFive,
                    vatRatePercent: '5',
                ),
            ],
            customer: $this->customer,
        ));

        $this->invoiceIssuer->issue(new IssueInvoiceRequest(
            company: $this->company,
            branch: $this->branch,
            postedBy: $this->user->id,
            lines: [
                new InvoiceLineItemDTO(
                    variant: $this->soapVariant,
                    itemDescription: 'Bath Soap 100g',
                    quantity: '4',
                    unitPrice: '65.00',
                    taxCode: $this->taxCodeFifteen,
                    vatRatePercent: '15',
                ),
            ],
            customer: $this->customer,
        ));

        // Reconcile register VAT with GL VAT Payable account
        $reconciliation = $this->reportService->reconcileRegisterVatWithGl(
            companyId: $this->company->id,
            periodId: $this->period->id,
            vatAccountId: $this->vatAccount->id,
        );

        $this->assertTrue($reconciliation['reconciled'], 'Sales Register VAT must reconcile exactly with GL account 2020');
        $this->assertSame('0.00', $reconciliation['difference']);
        $this->assertSame($reconciliation['register_vat'], $reconciliation['gl_vat']);
    }

    public function test_rendered_mushak_6_3_matches_basket_vector_exactly(): void
    {
        // Basket vector from tests/golden/vat-vectors.json
        // Rice: 450 (0%) -> Net 450.00, VAT 0.00
        // Oil: 280 (5%) -> Net 266.67, VAT 13.33
        // Soap: 2 x 65 = 130 (15%) -> Net 113.04, VAT 16.96
        // Basket Total: Gross ৳860.00, Net ৳829.71, VAT ৳30.29
        $invoice = $this->invoiceIssuer->issue(new IssueInvoiceRequest(
            company: $this->company,
            branch: $this->branch,
            postedBy: $this->user->id,
            lines: [
                new InvoiceLineItemDTO(
                    variant: $this->riceVariant,
                    itemDescription: 'Miniket Rice 5kg',
                    quantity: '1',
                    unitPrice: '450.00',
                    taxCode: $this->taxCodeZero,
                    vatRatePercent: '0',
                ),
                new InvoiceLineItemDTO(
                    variant: $this->oilVariant,
                    itemDescription: 'Soybean Oil 1L',
                    quantity: '1',
                    unitPrice: '280.00',
                    taxCode: $this->taxCodeFive,
                    vatRatePercent: '5',
                ),
                new InvoiceLineItemDTO(
                    variant: $this->soapVariant,
                    itemDescription: 'Bath Soap 100g',
                    quantity: '2',
                    unitPrice: '65.00',
                    taxCode: $this->taxCodeFifteen,
                    vatRatePercent: '15',
                ),
            ],
            customer: $this->customer,
        ));

        $html = $this->reportService->renderMushak63($invoice, 'a4', 'en');

        // Assert exact basket figures in statutory Mushak 6.3 output
        $this->assertStringContainsString('829.71', $html, 'Mushak 6.3 must contain Net Subtotal 829.71');
        $this->assertStringContainsString('30.29', $html, 'Mushak 6.3 must contain VAT 30.29');
        $this->assertStringContainsString('860.00', $html, 'Mushak 6.3 must contain Total Payable 860.00');
        $this->assertStringContainsString('450.00', $html, 'Rice line total 450.00');
        $this->assertStringContainsString('266.67', $html, 'Oil line total 266.67');
        $this->assertStringContainsString('113.04', $html, 'Soap line total 113.04');
    }
}
