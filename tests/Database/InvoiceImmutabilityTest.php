<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\TaxCode;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceImmutabilityTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected TaxCode $taxCode;

    protected ProductVariant $variant;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Invoice Immutability Tenant',
            'subdomain' => 'inv-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Acme Invoicing Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'BR-01',
            'name' => 'Dhaka Main Branch',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Test Retail Customer',
            'is_active' => true,
        ]);

        $this->taxCode = TaxCode::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'VAT-15',
            'name_en' => 'Standard VAT 15%',
            'tax_type' => 'standard_vat',
            'is_active' => true,
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PROD-INV-01',
            'name_en' => 'Test Invoice Product',
            'base_uom' => 'Pcs',
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'tax_code_id' => $this->taxCode->id,
            'sku' => 'SKU-INV-01',
            'name_en' => 'Test Invoice Variant',
            'base_uom' => 'Pcs',
            'mrp' => 115.0000,
            'cost_price' => 80.0000,
            'is_active' => true,
        ]);
    }

    /**
     * Helper to create a valid line item for an invoice.
     */
    protected function createLine(string $invoiceId, float $subtotal = 100.0, float $tax = 15.0, float $total = 115.0): SalesInvoiceLine
    {
        return SalesInvoiceLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'sales_invoice_id' => $invoiceId,
            'product_variant_id' => $this->variant->id,
            'item_description' => 'Test Line',
            'uom' => 'Pcs',
            'quantity' => 1.0000,
            'unit_price' => $total,
            'pricing_basis' => 'tax_inclusive',
            'tax_code_id' => $this->taxCode->id,
            'vat_rate' => 15.00,
            'tax_amount' => $tax,
            'line_total' => $subtotal,
        ]);
    }

    /**
     * T1: inserting an invoice without a status creates a draft, and lines can be added to it (NEW-27).
     */
    public function test_t1_inserting_invoice_without_status_creates_draft_and_lines_can_be_added(): void
    {
        $invoiceId = (string) Str::uuid();

        // Inserting an invoice without specifying status
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T1-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoice = SalesInvoice::findOrFail($invoiceId);

        // Check T1 expectation 1: Default status MUST be 'draft'
        $this->assertEquals('draft', $invoice->status, 'T1 Failure: newly inserted invoice without explicit status must default to draft');

        // Check T1 expectation 2: Lines can be attached to the newly created invoice
        $line = $this->createLine($invoiceId, 100.0, 15.0, 115.0);
        $this->assertDatabaseHas('sales_invoice_lines', ['id' => $line->id]);
    }

    /**
     * T2: issuing an invoice with no lines, or whose header totals differ from the sum of its lines, is rejected (NEW-27, NEW-24).
     */
    public function test_t2_issuing_invoice_with_no_lines_or_mismatched_totals_is_rejected(): void
    {
        // Case A: 0 lines invoice transitioning from draft to issued must be rejected
        $zeroLineInvoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $zeroLineInvoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T2A-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => 999.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 0.0000,
            'total_payable' => 999.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('sales_invoices')->where('id', $zeroLineInvoiceId)->update(['status' => 'issued']);
    }

    public function test_t2_issuing_invoice_with_mismatched_header_totals_is_rejected(): void
    {
        // Case B: 1 line with 100.00 subtotal, but header subtotal is 999.00 -> must be rejected
        $mismatchedInvoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $mismatchedInvoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T2B-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => 999.0000, // Header claims 999.00
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 1014.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Line only has 100.00
        $this->createLine($mismatchedInvoiceId, 100.0, 15.0, 115.0);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Header-Line Imbalance Violation/i');
        DB::table('sales_invoices')->where('id', $mismatchedInvoiceId)->update(['status' => 'issued']);
    }

    public function test_t2_issuing_invoice_with_mismatched_tax_amount_is_rejected(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T2-TAX-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 99.0000, // Header claims 99 tax, line has 15
            'total_payable' => 199.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createLine($invoiceId, 100.0, 15.0, 115.0);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Tax Amount Imbalance Violation/i');
        DB::table('sales_invoices')->where('id', $invoiceId)->update(['status' => 'issued']);
    }

    public function test_t2_issuing_invoice_with_mismatched_duty_amount_is_rejected(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T2-SD-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 25.0000, // Header claims 25 SD, line has 0
            'tax_amount' => 15.0000,
            'total_payable' => 140.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createLine($invoiceId, 100.0, 15.0, 115.0);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Supplementary Duty Imbalance Violation/i');
        DB::table('sales_invoices')->where('id', $invoiceId)->update(['status' => 'issued']);
    }

    public function test_t2_issuing_invoice_with_mismatched_total_payable_is_rejected(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T2-TOT-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 500.0000, // Total payable mathematically wrong (should be 115)
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createLine($invoiceId, 100.0, 15.0, 115.0);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Total Payable Imbalance Violation/i');
        DB::table('sales_invoices')->where('id', $invoiceId)->update(['status' => 'issued']);
    }

    public function test_t2_issuing_invoice_with_matching_lines_and_totals_is_allowed(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T2-OK-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createLine($invoiceId, 100.0, 15.0, 115.0);

        DB::table('sales_invoices')->where('id', $invoiceId)->update(['status' => 'issued']);

        $invoice = SalesInvoice::findOrFail($invoiceId);
        $this->assertSame('issued', $invoice->status);
    }

    public function test_t3_draft_invoice_can_be_updated_while_remaining_draft(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T3-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Edits while remaining draft are permitted
        DB::table('sales_invoices')->where('id', $invoiceId)->update([
            'payment_status' => 'paid',
            'subtotal' => 200.0000,
            'total_payable' => 230.0000,
            'tax_amount' => 30.0000,
        ]);

        $invoice = SalesInvoice::findOrFail($invoiceId);
        $this->assertSame('draft', $invoice->status);
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertSame('200.0000', (string) $invoice->subtotal);
    }

    /**
     * T4: an update that fills fiscal_signature may not change any other column (status, number, dates, customer, discount, duty or totals) (NEW-27).
     */
    public function test_t4_fiscal_enrichment_cannot_modify_status(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T4A-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'issued',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'fiscal_signature' => null,
            'sdc_invoice_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attacker attempts to reopen issued invoice to draft through fiscal enrichment
        $this->expectException(QueryException::class);
        DB::table('sales_invoices')->where('id', $invoiceId)->update([
            'fiscal_signature' => 'VALID_SIG_HASH_001',
            'status' => 'draft',
        ]);
    }

    public function test_t4_fiscal_enrichment_cannot_modify_invoice_number_or_date(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T4B-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'issued',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'fiscal_signature' => null,
            'sdc_invoice_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attacker attempts to renumber and redate through fiscal enrichment
        $this->expectException(QueryException::class);
        DB::table('sales_invoices')->where('id', $invoiceId)->update([
            'fiscal_signature' => 'VALID_SIG_HASH_002',
            'invoice_number' => 'INV-FORGED-999',
            'invoice_date' => '2020-01-01',
        ]);
    }

    public function test_t4_legitimate_fiscal_enrichment_is_allowed(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T4C-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'issued',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'fiscal_signature' => null,
            'sdc_invoice_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Legitimate fiscal fill strictly fills signature and SDC number
        DB::table('sales_invoices')->where('id', $invoiceId)->update([
            'fiscal_signature' => 'VALID_SIG_HASH_OK',
            'sdc_invoice_number' => 'SDC-2026-0001',
        ]);

        $invoice = SalesInvoice::findOrFail($invoiceId);
        $this->assertEquals('issued', $invoice->status);
        $this->assertEquals('VALID_SIG_HASH_OK', $invoice->fiscal_signature);
        $this->assertEquals('SDC-2026-0001', $invoice->sdc_invoice_number);
    }

    public function test_t4_direct_update_of_issued_invoice_without_fiscal_enrichment_is_rejected(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T4-NO-FISCAL-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'issued',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Issued Sales Invoice .* is immutable and cannot be modified/i');
        DB::table('sales_invoices')->where('id', $invoiceId)->update([
            'total_payable' => 200.0000,
        ]);
    }

    public function test_t4_fiscal_enrichment_cannot_modify_customer_or_financial_totals(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T4-FIN-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'issued',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Fiscal enrichment on Sales Invoice .* may only fill fiscal_signature or sdc_invoice_number from NULL/i');
        DB::table('sales_invoices')->where('id', $invoiceId)->update([
            'fiscal_signature' => 'VALID_SIG_HASH_003',
            'discount_amount' => 10.0000,
        ]);
    }

    public function test_t4_overwriting_already_set_fiscal_signature_is_rejected(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T4-OVERWRITE-SIG-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'issued',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'fiscal_signature' => 'INITIAL_SIGNATURE_HASH',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/fiscal_signature on Sales Invoice .* cannot be overwritten once set/i');
        DB::table('sales_invoices')->where('id', $invoiceId)->update([
            'fiscal_signature' => 'NEW_OVERWRITE_SIGNATURE',
        ]);
    }

    public function test_t4_overwriting_already_set_sdc_invoice_number_is_rejected(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T4-OVERWRITE-SDC-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'issued',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'sdc_invoice_number' => 'SDC-2026-ORIGINAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/sdc_invoice_number on Sales Invoice .* cannot be overwritten once set/i');
        DB::table('sales_invoices')->where('id', $invoiceId)->update([
            'sdc_invoice_number' => 'SDC-2026-FORGED',
        ]);
    }

    public function test_t4_deleting_issued_invoice_is_rejected(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T4-DEL-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'issued',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Issued Sales Invoice .* cannot be deleted. Issue a Mushak 6.7 Credit Note/i');
        DB::table('sales_invoices')->where('id', $invoiceId)->delete();
    }

    /**
     * T5: changing sales_invoice_id on a line is rejected (NEW-27).
     */
    public function test_t5_changing_sales_invoice_id_on_line_is_rejected(): void
    {
        // Invoice 1: Issued invoice with a line
        $invoice1Id = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoice1Id,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T5-1-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $line = $this->createLine($invoice1Id, 100.0, 15.0, 115.0);

        // Issue invoice 1
        DB::table('sales_invoices')->where('id', $invoice1Id)->update(['status' => 'issued']);

        // Invoice 2: Draft invoice
        $invoice2Id = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoice2Id,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T5-2-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => 0.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 0.0000,
            'total_payable' => 0.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempting to move line from issued Invoice 1 to draft Invoice 2
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Cannot reassign sales_invoice_id on invoice line/i');
        DB::table('sales_invoice_lines')->where('id', $line->id)->update([
            'sales_invoice_id' => $invoice2Id,
        ]);
    }

    public function test_t5_inserting_line_into_issued_invoice_is_rejected(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T5-INS-ISS-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'issued',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Cannot INSERT line items on a Sales Invoice with status issued/i');
        $this->createLine($invoiceId, 100.0, 15.0, 115.0);
    }

    public function test_t5_updating_line_on_issued_invoice_is_rejected(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T5-UPD-ISS-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $line = $this->createLine($invoiceId, 100.0, 15.0, 115.0);
        DB::table('sales_invoices')->where('id', $invoiceId)->update(['status' => 'issued']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Cannot UPDATE line items on a Sales Invoice with status issued/i');
        DB::table('sales_invoice_lines')->where('id', $line->id)->update([
            'quantity' => 5.0000,
        ]);
    }

    public function test_t5_deleting_line_from_issued_invoice_is_rejected(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T5-DEL-ISS-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $line = $this->createLine($invoiceId, 100.0, 15.0, 115.0);
        DB::table('sales_invoices')->where('id', $invoiceId)->update(['status' => 'issued']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Cannot DELETE line items on a Sales Invoice with status issued/i');
        DB::table('sales_invoice_lines')->where('id', $line->id)->delete();
    }

    public function test_t5_inserting_line_into_cancelled_invoice_is_rejected(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T5-INS-CANC-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'cancelled',
            'subtotal' => 0.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 0.0000,
            'total_payable' => 0.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Cannot INSERT line items on a Sales Invoice with status cancelled/i');
        $this->createLine($invoiceId, 100.0, 15.0, 115.0);
    }

    public function test_t5_updating_line_on_cancelled_invoice_is_rejected(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T5-UPD-CANC-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $line = $this->createLine($invoiceId, 100.0, 15.0, 115.0);
        DB::table('sales_invoices')->where('id', $invoiceId)->update(['status' => 'cancelled']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Cannot UPDATE line items on a Sales Invoice with status cancelled/i');
        DB::table('sales_invoice_lines')->where('id', $line->id)->update([
            'quantity' => 10.0000,
        ]);
    }

    public function test_t5_deleting_line_from_cancelled_invoice_is_rejected(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T5-DEL-CANC-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $line = $this->createLine($invoiceId, 100.0, 15.0, 115.0);
        DB::table('sales_invoices')->where('id', $invoiceId)->update(['status' => 'cancelled']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Cannot DELETE line items on a Sales Invoice with status cancelled/i');
        DB::table('sales_invoice_lines')->where('id', $line->id)->delete();
    }

    public function test_t5_updating_line_on_draft_invoice_is_allowed(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T5-UPD-DFT-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $line = $this->createLine($invoiceId, 100.0, 15.0, 115.0);

        DB::table('sales_invoice_lines')->where('id', $line->id)->update([
            'quantity' => 3.0000,
            'line_total' => 300.0000,
            'tax_amount' => 45.0000,
        ]);

        $freshLine = SalesInvoiceLine::findOrFail($line->id);
        $this->assertSame('3.0000', (string) $freshLine->quantity);
        $this->assertSame('300.0000', (string) $freshLine->line_total);
    }

    public function test_t5_deleting_line_from_draft_invoice_is_allowed(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T5-DEL-DFT-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $line = $this->createLine($invoiceId, 100.0, 15.0, 115.0);
        $this->assertDatabaseHas('sales_invoice_lines', ['id' => $line->id]);

        DB::table('sales_invoice_lines')->where('id', $line->id)->delete();
        $this->assertDatabaseMissing('sales_invoice_lines', ['id' => $line->id]);
    }

    /**
     * T6: a cancelled invoice can't be updated or deleted (NEW-27).
     */
    public function test_t6_cancelled_invoice_cannot_be_deleted(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T6A-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'cancelled',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Deleting a cancelled invoice must be rejected
        $this->expectException(QueryException::class);
        DB::table('sales_invoices')->where('id', $invoiceId)->delete();
    }

    public function test_t6_cancelled_invoice_cannot_be_updated(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-T6B-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'cancelled',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Updating a cancelled invoice must be rejected
        $this->expectException(QueryException::class);
        DB::table('sales_invoices')->where('id', $invoiceId)->update([
            'subtotal' => 999.0000,
        ]);
    }

    /**
     * Void flow: draft invoices cannot be deleted, but may be transitioned to cancelled (voided).
     */
    public function test_t6_void_flow_allows_cancelling_draft_but_blocks_deletion(): void
    {
        $invoiceId = (string) Str::uuid();
        $invoiceNumber = 'INV-VOID-'.Str::random(6);

        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Direct deletion of draft is rejected (preserves sequence)
        try {
            DB::table('sales_invoices')->where('id', $invoiceId)->delete();
            $this->fail('Draft deletion should have thrown QueryException');
        } catch (QueryException $e) {
            $this->assertStringContainsString('void flow', $e->getMessage());
        }

        // 2. Void flow: update status to cancelled is permitted
        DB::table('sales_invoices')->where('id', $invoiceId)->update(['status' => 'cancelled']);

        $invoice = SalesInvoice::findOrFail($invoiceId);
        $this->assertEquals('cancelled', $invoice->status);
        $this->assertEquals($invoiceNumber, $invoice->invoice_number);
    }

    /**
     * Composite Foreign Key Protections (NEW-27, AGENTS.md Rule 2).
     */
    public function test_composite_fk_rejects_cross_company_branch_reference(): void
    {
        $otherCompany = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Foreign Company Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $foreignBranch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $otherCompany->id,
            'code' => 'BR-FOREIGN',
            'name' => 'Foreign Branch',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(fk_sales_invoices_branch|foreign key constraint)/i');

        // Invoice under $this->company attempting to reference $foreignBranch under $otherCompany
        DB::table('sales_invoices')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $foreignBranch->id,
            'invoice_number' => 'INV-X-BR-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_composite_fk_rejects_cross_company_customer_reference(): void
    {
        $otherCompany = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Other Customer Co Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $foreignCustomer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $otherCompany->id,
            'name' => 'Foreign Customer',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(fk_sales_invoices_customer|foreign key constraint)/i');

        // Invoice under $this->company referencing $foreignCustomer under $otherCompany
        DB::table('sales_invoices')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $foreignCustomer->id,
            'invoice_number' => 'INV-X-CUST-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_composite_fk_rejects_cross_company_product_variant_on_line(): void
    {
        $invoiceId = (string) Str::uuid();
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-X-LINE-'.Str::random(6),
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => 100.0000,
            'discount_amount' => 0.0000,
            'supplementary_duty_amount' => 0.0000,
            'tax_amount' => 15.0000,
            'total_payable' => 115.0000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherCompany = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Foreign Inventory Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $foreignProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $otherCompany->id,
            'code' => 'PROD-FOR-01',
            'name_en' => 'Foreign Product',
            'base_uom' => 'Pcs',
            'is_active' => true,
        ]);

        $foreignTaxCode = TaxCode::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $otherCompany->id,
            'code' => 'VAT-FOR-15',
            'name_en' => 'Foreign VAT 15%',
            'is_active' => true,
        ]);

        $foreignVariant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $otherCompany->id,
            'product_id' => $foreignProduct->id,
            'tax_code_id' => $foreignTaxCode->id,
            'sku' => 'SKU-FOR-01',
            'name_en' => 'Foreign Variant',
            'base_uom' => 'Pcs',
            'mrp' => 115.0000,
            'cost_price' => 80.0000,
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(fk_invoice_lines_variant|foreign key constraint)/i');

        // Line under $this->company attempting to use variant under $otherCompany
        SalesInvoiceLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'sales_invoice_id' => $invoiceId,
            'product_variant_id' => $foreignVariant->id,
            'item_description' => 'Foreign Item Line',
            'uom' => 'Pcs',
            'quantity' => 1.0000,
            'unit_price' => 115.0000,
            'pricing_basis' => 'tax_inclusive',
            'tax_code_id' => $this->taxCode->id,
            'vat_rate' => 15.00,
            'tax_amount' => 15.0000,
            'line_total' => 100.0000,
        ]);
    }
}
