<?php

declare(strict_types=1);

use App\Core\Audit\Services\HashPendingAuditLogsService;
use App\Core\Tenancy\TenantContext;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\PosShift;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockOversellException;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('pilot:daily-check reports each kind of seeded difference and exits non-zero', function () {
    // 1. Setup Tenant, Company and Branch
    $tenant = Tenant::create([
        'id' => (string) Str::uuid(),
        'name' => 'Daily Check Test Tenant',
        'subdomain' => 'dailycheck-'.Str::lower(Str::random(6)),
        'status' => 'active',
    ]);

    TenantContext::set($tenant);

    $company = Company::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'legal_name' => 'Daily Check Retail Ltd',
        'base_currency' => 'BDT',
        'fiscal_year_start_month' => 7,
        'tax_regime' => 'standard_vat',
    ]);

    $branch = Branch::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'name' => 'Mirpur Express Outlet',
        'code' => 'MIRPUR-01',
        'is_active' => true,
    ]);

    $user = User::factory()->create();
    DB::table('tenant_memberships')->insert([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $terminal = PosTerminal::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'terminal_code' => 'T-01',
        'name' => 'Counter Terminal 1',
        'is_active' => true,
    ]);

    $warehouse = Warehouse::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'name' => 'Mirpur Floor Stock',
        'code' => 'WH-MIRPUR-01',
        'is_active' => true,
    ]);

    $product = Product::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'name' => 'Tang Instant Drink 500g',
        'type' => 'storable',
        'status' => 'active',
    ]);

    $variant = ProductVariant::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'product_id' => $product->id,
        'sku' => 'TANG-500G-ORANGE',
        'name' => 'Orange',
        'barcode' => '8941100992211',
        'retail_price' => '350.0000',
    ]);

    // -------------------------------------------------------------------------
    // Difference 1: POS cash counted vs GL cash account (1010)
    // -------------------------------------------------------------------------
    PosShift::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'terminal_id' => $terminal->id,
        'user_id' => $user->id,
        'status' => 'closed',
        'opened_at' => now()->subHours(8),
        'closed_at' => now()->subHour(),
        'opening_cash' => '1000.0000',
        'closing_cash' => '5500.0000', // Counted 5500, but GL has 0
    ]);

    // -------------------------------------------------------------------------
    // Difference 2: Stock balance vs sum of movements
    // -------------------------------------------------------------------------
    StockBalance::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'warehouse_id' => $warehouse->id,
        'product_variant_id' => $variant->id,
        'quantity_on_hand' => '15.0000', // Balance says 15
        'weighted_avg_cost' => '250.0000',
    ]);

    StockMovement::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'warehouse_id' => $warehouse->id,
        'product_variant_id' => $variant->id,
        'movement_type' => 'GRN',
        'quantity' => '10.0000', // Movements sum to only 10
        'unit_cost' => '250.0000',
        'reference_type' => 'goods_receipt',
        'reference_id' => (string) Str::uuid(),
        'occurred_at' => now()->subHours(2),
    ]);

    // -------------------------------------------------------------------------
    // Difference 3: Mushak 6.2 VAT total vs GL VAT payable account (2020)
    // -------------------------------------------------------------------------
    SalesInvoice::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'terminal_id' => $terminal->id,
        'client_txn_id' => 'TXN-VAT-001',
        'invoice_number' => 'INV-MIRPUR-001',
        'invoice_date' => now()->toDateString(),
        'business_date' => now()->toDateString(),
        'status' => 'issued',
        'subtotal' => '2000.0000',
        'tax_amount' => '300.0000', // Mushak VAT says 300, but GL VAT is 0
        'total_payable' => '2300.0000',
        'fiscal_signature' => 'VALID_SDC_SIGNATURE_OK',
        'sdc_invoice_number' => 'SDC-2026-0001',
    ]);

    // -------------------------------------------------------------------------
    // Difference 4A: Unsynced sales older than 4 hours
    // -------------------------------------------------------------------------
    SalesInvoice::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'terminal_id' => $terminal->id,
        'client_txn_id' => 'TXN-OLD-UNSYNC-001',
        'invoice_number' => 'INV-DRAFT-OLD-001',
        'invoice_date' => now()->subHours(6)->toDateString(),
        'business_date' => now()->subHours(6)->toDateString(),
        'status' => 'draft',
        'subtotal' => '1000.0000',
        'total_payable' => '1000.0000',
        'created_at' => now()->subHours(6),
        'updated_at' => now()->subHours(6),
    ]);

    // -------------------------------------------------------------------------
    // Difference 4B: Unfiscalized invoice for terminal
    // -------------------------------------------------------------------------
    SalesInvoice::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'terminal_id' => $terminal->id,
        'client_txn_id' => 'TXN-UNFISCAL-002',
        'invoice_number' => 'INV-NO-FISCAL-002',
        'invoice_date' => now()->toDateString(),
        'business_date' => now()->toDateString(),
        'status' => 'issued',
        'subtotal' => '500.0000',
        'tax_amount' => '75.0000',
        'total_payable' => '575.0000',
        'fiscal_signature' => null, // Missing fiscal signature!
        'sdc_invoice_number' => null,
    ]);

    // -------------------------------------------------------------------------
    // Difference 5: Open stock oversell exception
    // -------------------------------------------------------------------------
    StockOversellException::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'warehouse_id' => $warehouse->id,
        'product_variant_id' => $variant->id,
        'quantity_oversold' => '4.0000',
        'status' => 'OPEN',
        'notes' => 'Offline oversell during peak surge',
    ]);

    // -------------------------------------------------------------------------
    // Difference 6: Audit-chain verification failure (tamper alert)
    // -------------------------------------------------------------------------
    $hasher = app(HashPendingAuditLogsService::class);

    $log1 = AuditLog::create([
        'tenant_id' => $tenant->id,
        'auditable_type' => 'sales_invoices',
        'auditable_id' => (string) Str::uuid(),
        'event' => 'issued',
        'user_id' => $user->id,
        'actor_type' => 'user',
        'payload_hash' => hash('sha256', 'payload-1'),
        'status' => 'pending_hash',
    ]);

    $log2 = AuditLog::create([
        'tenant_id' => $tenant->id,
        'auditable_type' => 'sales_invoices',
        'auditable_id' => (string) Str::uuid(),
        'event' => 'issued',
        'user_id' => $user->id,
        'actor_type' => 'user',
        'payload_hash' => hash('sha256', 'payload-2'),
        'status' => 'pending_hash',
    ]);

    $hasher->processTenant($tenant->id);

    // Tamper with log 2 out of band bypassing trigger as pgsql_owner
    DB::connection('pgsql_owner')->statement('ALTER TABLE audit_logs DISABLE TRIGGER trg_audit_logs_immutable;');
    DB::table('audit_logs')
        ->where('id', $log2->id)
        ->update(['payload_hash' => hash('sha256', 'tampered-payload')]);
    DB::connection('pgsql_owner')->statement('ALTER TABLE audit_logs ENABLE TRIGGER trg_audit_logs_immutable;');

    // -------------------------------------------------------------------------
    // Execute Command & Assert All Differences Are Reported
    // -------------------------------------------------------------------------
    $exitCode = Artisan::call('pilot:daily-check', [
        'tenant' => $tenant->id,
        '--connection' => 'reporting_ro',
    ]);
    $output = Artisan::output();

    // Must exit non-zero
    expect($exitCode)->toBe(1);

    // Must report every single kind of difference
    expect($output)->toContain('FAIL: Found')
        ->and($output)->toContain('POS Cash vs GL (1010)')
        ->and($output)->toContain('Stock Ledger Parity')
        ->and($output)->toContain('Mushak 6.2 vs GL VAT (2020)')
        ->and($output)->toContain('Unsynced Sales (>4h)')
        ->and($output)->toContain('Unfiscalized Invoices')
        ->and($output)->toContain('Open Oversell Exception')
        ->and($output)->toContain('Audit Chain Tampering');
});
