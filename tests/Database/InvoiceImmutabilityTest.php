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
        DB::table('sales_invoices')->where('id', $mismatchedInvoiceId)->update(['status' => 'issued']);
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
        DB::table('sales_invoice_lines')->where('id', $line->id)->update([
            'sales_invoice_id' => $invoice2Id,
        ]);
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
}
