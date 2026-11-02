<?php

namespace Tests\Feature\Watchdog;

use App\Core\Tenancy\TenantContext;
use App\Core\Watchdog\Services\IntegrityWatchdogService;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockOversellException;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class IntegrityWatchdogTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected Warehouse $warehouse;

    protected AccountingPeriod $period;

    protected ChartOfAccount $accountCash;

    protected ChartOfAccount $accountSales;

    protected ProductVariant $variantA;

    protected ProductVariant $variantB;

    protected User $systemUser;

    protected IntegrityWatchdogService $watchdog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Watchdog Tenant',
            'subdomain' => 'wd-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Watchdog Mart Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Main Outlet',
            'code' => 'OUT-01',
        ]);

        $this->warehouse = Warehouse::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'WH-WD-01',
            'name' => 'Watchdog Warehouse',
            'is_active' => true,
        ]);

        $this->systemUser = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Watchdog Auditor',
            'email' => 'auditor-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);

        $currentYear = Carbon::now()->year;
        $fiscalYear = FiscalYear::create([
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
            'fiscal_year_id' => $fiscalYear->id,
            'period_number' => 1,
            'period_name' => Carbon::now()->format('F Y'),
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->endOfMonth()->toDateString(),
            'status' => 'open',
        ]);

        $this->accountCash = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1010',
            'account_name' => 'Cash on Hand',
            'account_type' => 'asset',
            'is_reconcilable' => true,
            'is_active' => true,
        ]);

        $this->accountSales = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '4010',
            'account_name' => 'Retail Sales Revenue',
            'account_type' => 'revenue',
            'is_active' => true,
        ]);

        $taxCode = TaxCode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'VAT-15',
            'name_en' => 'Standard VAT 15%',
            'tax_type' => 'standard_vat',
            'is_active' => true,
        ]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PRD-WD-01',
            'name_en' => 'Watchdog Product',
            'base_uom' => 'Pcs',
            'is_active' => true,
        ]);

        $this->variantA = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'tax_code_id' => $taxCode->id,
            'sku' => 'SKU-WD-001',
            'name_en' => 'Watchdog Variant A',
            'base_uom' => 'Pcs',
            'cost_price' => '100.0000',
            'mrp' => '150.0000',
            'is_active' => true,
        ]);

        $this->variantB = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'tax_code_id' => $taxCode->id,
            'sku' => 'SKU-WD-002',
            'name_en' => 'Watchdog Variant B',
            'base_uom' => 'Pcs',
            'cost_price' => '200.0000',
            'mrp' => '280.0000',
            'is_active' => true,
        ]);

        $this->watchdog = app(IntegrityWatchdogService::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_watchdog_fails_loudly_when_tenant_context_is_empty_never_reports_clean(): void
    {
        TenantContext::clear();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Tenant context.*empty|Tenant context must be set/i');

        // Attempting to audit checks without an active tenant context must fail loudly
        $this->watchdog->auditCurrentTenantContext();
    }

    public function test_watchdog_reports_unbalanced_posted_journal_entry(): void
    {
        // 1. Create a balanced posted entry first
        $entryId = (string) Str::uuid();
        JournalEntry::create([
            'id' => $entryId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->period->id,
            'entry_number' => 'JV-2026-WD01',
            'posting_date' => Carbon::now()->toDateString(),
            'business_date' => Carbon::now()->toDateString(),
            'reference_type' => 'manual',
            'posted_by' => $this->systemUser->id,
            'is_posted' => false,
        ]);

        JournalLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'journal_entry_id' => $entryId,
            'account_id' => $this->accountCash->id,
            'debit_amount' => '1000.0000',
            'credit_amount' => '0.0000',
        ]);

        // Seed an intentional imbalance: credit is 900 instead of 1000
        JournalLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'journal_entry_id' => $entryId,
            'account_id' => $this->accountSales->id,
            'debit_amount' => '0.0000',
            'credit_amount' => '900.0000',
        ]);

        // Bypass immutability trigger as pgsql_owner to simulate corrupt/tampered posted state
        DB::connection('pgsql_owner')->statement('ALTER TABLE journal_entries DISABLE TRIGGER trg_journal_entry_immutability;');
        DB::table('journal_entries')
            ->where('id', $entryId)
            ->update(['is_posted' => true]);
        DB::connection('pgsql_owner')->statement('ALTER TABLE journal_entries ENABLE TRIGGER trg_journal_entry_immutability;');

        // Run watchdog for this tenant
        $result = $this->watchdog->checkTenant($this->tenant);

        $this->assertTrue($result->hasBreaches());
        $this->assertNotEmpty($result->unbalancedJournals);
        $this->assertSame($entryId, $result->unbalancedJournals[0]['journal_entry_id']);
        $this->assertSame('JV-2026-WD01', $result->unbalancedJournals[0]['entry_number']);
        $this->assertSame('1000.0000', (string) $result->unbalancedJournals[0]['total_debits']);
        $this->assertSame('900.0000', (string) $result->unbalancedJournals[0]['total_credits']);
    }

    public function test_watchdog_reports_stock_balance_discrepancy(): void
    {
        // Variant A: StockBalance quantity_on_hand = 50, but stock_movements sum to 40
        StockBalance::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variantA->id,
            'quantity_on_hand' => '50.0000',
            'quantity_reserved' => '0.0000',
            'weighted_avg_cost' => '100.0000',
        ]);

        StockMovement::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variantA->id,
            'movement_type' => 'IN',
            'quantity' => '40.0000',
            'unit_cost' => '100.0000',
            'reference_type' => 'opening_balance',
            'reference_id' => (string) Str::uuid(),
        ]);

        $result = $this->watchdog->checkTenant($this->tenant);

        $this->assertTrue($result->hasBreaches());
        $this->assertNotEmpty($result->stockDiscrepancies);
        $this->assertSame($this->variantA->id, $result->stockDiscrepancies[0]['product_variant_id']);
        $this->assertSame('50.0000', (string) $result->stockDiscrepancies[0]['quantity_on_hand']);
        $this->assertSame('40.0000', (string) $result->stockDiscrepancies[0]['total_movements']);
    }

    public function test_watchdog_allows_negative_stock_with_adr016_exception_but_reports_orphan_negative_stock(): void
    {
        // Variant A: Negative balance (-5) WITH an active ADR-016 exception
        DB::statement("SELECT set_config('app.allow_negative_stock', 'true', true)");

        StockBalance::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variantA->id,
            'quantity_on_hand' => '-5.0000',
            'quantity_reserved' => '0.0000',
            'weighted_avg_cost' => '100.0000',
        ]);

        StockMovement::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variantA->id,
            'movement_type' => 'OFFLINE_OVERSELL',
            'quantity' => '-5.0000',
            'unit_cost' => '100.0000',
            'reference_type' => 'sales_invoice',
            'reference_id' => (string) Str::uuid(),
        ]);

        StockOversellException::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variantA->id,
            'quantity_oversold' => '5.0000',
            'sales_invoice_id' => (string) Str::uuid(),
            'cost_basis_used' => '100.0000',
            'status' => 'OPEN',
        ]);

        // Variant B: Negative balance (-2) WITHOUT any exception (orphan negative stock)
        StockBalance::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variantB->id,
            'quantity_on_hand' => '-2.0000',
            'quantity_reserved' => '0.0000',
            'weighted_avg_cost' => '200.0000',
        ]);

        StockMovement::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variantB->id,
            'movement_type' => 'OFFLINE_OVERSELL',
            'quantity' => '-2.0000',
            'unit_cost' => '200.0000',
            'reference_type' => 'sales_invoice',
            'reference_id' => (string) Str::uuid(),
        ]);

        $result = $this->watchdog->checkTenant($this->tenant);

        // Variant A must NOT be flagged as orphan negative stock
        $orphanVariantIds = collect($result->orphanNegativeStocks)->pluck('product_variant_id')->all();
        $this->assertNotContains($this->variantA->id, $orphanVariantIds);

        // Variant B MUST be flagged as an orphan negative stock breach
        $this->assertContains($this->variantB->id, $orphanVariantIds);
    }

    public function test_watchdog_runs_across_multiple_active_tenants_with_transactional_set_config(): void
    {
        // Tenant 2: perfectly clean state
        $tenantClean = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Clean Tenant',
            'subdomain' => 'clean-'.Str::random(6),
            'status' => 'active',
        ]);

        $report = $this->watchdog->checkAllTenants();

        $this->assertArrayHasKey($this->tenant->id, $report->results);
        $this->assertArrayHasKey($tenantClean->id, $report->results);
        $this->assertFalse($report->results[$tenantClean->id]->hasBreaches());
    }
}
