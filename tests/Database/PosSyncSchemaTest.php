<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\TaxCode;
use App\Models\TaxCodeRate;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosSyncSchemaTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected PosTerminal $terminal;

    protected ProductVariant $variant;

    protected TaxCode $taxCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'POS Sync Tenant',
            'subdomain' => 'pos-sync-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'POS Sync Mart Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Dhanmondi Branch',
            'code' => 'DHA-SYNC',
        ]);

        $this->terminal = PosTerminal::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'terminal_code' => 'TERM-01',
            'name' => 'Checkout Counter 1',
            'is_active' => true,
        ]);

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

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PROD-01',
            'name_en' => 'Standard Item',
            'base_uom' => 'Pcs',
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'tax_code_id' => $this->taxCode->id,
            'sku' => 'SKU-01',
            'name_en' => 'Standard Item Pcs',
            'base_uom' => 'Pcs',
            'mrp' => '100.0000',
            'cost_price' => '80.0000',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_sales_invoices_table_has_offline_sync_and_clock_skew_columns(): void
    {
        $columns = DB::table('information_schema.columns')
            ->where('table_name', 'sales_invoices')
            ->pluck('column_name')
            ->toArray();

        $this->assertContains('device_time', $columns);
        $this->assertContains('server_received_time', $columns);
        $this->assertContains('clock_skew_seconds', $columns);
        $this->assertContains('is_clock_skew_flagged', $columns);
        $this->assertContains('is_synced_from_offline', $columns);
    }

    public function test_sales_invoice_model_casts_clock_skew_attributes(): void
    {
        $deviceTime = Carbon::now()->subMinutes(2);
        $serverTime = Carbon::now();

        $invoice = SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'terminal_id' => $this->terminal->id,
            'client_txn_id' => 'TXN-MODEL-001',
            'invoice_number' => 'INV-SYNC-TEST-001',
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => '100.0000',
            'discount_amount' => '0.0000',
            'supplementary_duty_amount' => '0.0000',
            'tax_amount' => '0.0000',
            'total_payable' => '100.0000',
            'payment_status' => 'paid',
            'is_synced_from_offline' => true,
            'device_time' => $deviceTime,
            'server_received_time' => $serverTime,
            'clock_skew_seconds' => 120,
            'is_clock_skew_flagged' => false,
        ]);

        $fresh = SalesInvoice::findOrFail($invoice->id);

        $this->assertTrue($fresh->is_synced_from_offline);
        $this->assertFalse($fresh->is_clock_skew_flagged);
        $this->assertSame(120, $fresh->clock_skew_seconds);
        $this->assertInstanceOf(Carbon::class, $fresh->device_time);
        $this->assertInstanceOf(Carbon::class, $fresh->server_received_time);
    }

    public function test_pos_offline_idempotency_constraint_rejects_duplicate_terminal_and_client_txn(): void
    {
        SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'terminal_id' => $this->terminal->id,
            'client_txn_id' => 'TXN-DUP-001',
            'invoice_number' => 'INV-SYNC-TEST-002',
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => '100.0000',
            'discount_amount' => '0.0000',
            'supplementary_duty_amount' => '0.0000',
            'tax_amount' => '0.0000',
            'total_payable' => '100.0000',
            'payment_status' => 'paid',
            'is_synced_from_offline' => true,
        ]);

        $this->expectException(QueryException::class);

        // Attempt second invoice with identical terminal_id and client_txn_id
        SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'terminal_id' => $this->terminal->id,
            'client_txn_id' => 'TXN-DUP-001',
            'invoice_number' => 'INV-SYNC-TEST-003',
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => '100.0000',
            'discount_amount' => '0.0000',
            'supplementary_duty_amount' => '0.0000',
            'tax_amount' => '0.0000',
            'total_payable' => '100.0000',
            'payment_status' => 'paid',
            'is_synced_from_offline' => true,
        ]);
    }

    public function test_issued_invoice_immutability_trigger_protects_clock_skew_columns(): void
    {
        $invoiceId = (string) Str::uuid();

        // 1. Insert draft
        DB::table('sales_invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'terminal_id' => $this->terminal->id,
            'client_txn_id' => 'TXN-IMMUTABLE-001',
            'invoice_number' => 'INV-SYNC-TEST-004',
            'invoice_date' => Carbon::today()->toDateString(),
            'business_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'subtotal' => '100.0000',
            'discount_amount' => '0.0000',
            'supplementary_duty_amount' => '0.0000',
            'tax_amount' => '0.0000',
            'total_payable' => '100.0000',
            'payment_status' => 'paid',
            'is_synced_from_offline' => true,
            'device_time' => Carbon::now()->subMinutes(10),
            'server_received_time' => Carbon::now(),
            'clock_skew_seconds' => 600,
            'is_clock_skew_flagged' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add line
        DB::table('sales_invoice_lines')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'sales_invoice_id' => $invoiceId,
            'product_variant_id' => $this->variant->id,
            'tax_code_id' => $this->taxCode->id,
            'vat_rate' => '0.00',
            'item_description' => 'Test Item',
            'uom' => 'Pcs',
            'quantity' => '1.0000',
            'unit_price' => '100.0000',
            'tax_amount' => '0.0000',
            'line_total' => '100.0000',
            'created_at' => now(),
        ]);

        // Transition to issued
        DB::table('sales_invoices')->where('id', $invoiceId)->update(['status' => 'issued']);

        // Attempting to modify clock_skew_seconds on issued invoice must be rejected by Trigger 4
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Statutory Audit Violation: Fiscal enrichment on Sales Invoice');

        DB::table('sales_invoices')->where('id', $invoiceId)->update([
            'clock_skew_seconds' => 10,
            'fiscal_signature' => 'SIG-VALID', // Even with fiscal fields, tampering with skew is refused
        ]);
    }
}
