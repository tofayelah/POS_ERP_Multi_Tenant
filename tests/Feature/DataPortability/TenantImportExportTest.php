<?php

namespace Tests\Feature\DataPortability;

use App\Core\Tenancy\TenantContext;
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
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\DataMigration\Services\MasterDataImportService;
use App\Modules\DataMigration\Services\TenantExportService;
use App\Modules\DataMigration\Services\TenantOffboardingService;
use App\Modules\DataMigration\Services\TenantRestoreService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class TenantImportExportTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected Warehouse $warehouse;

    protected AccountingPeriod $period;

    protected User $user;

    protected ChartOfAccount $accountCash;

    protected ChartOfAccount $accountInventory;

    protected ChartOfAccount $accountEquity;

    protected TaxCode $taxCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Data Migration Tenant',
            'subdomain' => 'dm-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Data Migration Corp',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'HQ Branch',
            'code' => 'HQ-01',
        ]);

        $this->warehouse = Warehouse::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'WH-DM-01',
            'name' => 'HQ Warehouse',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Migration Admin',
            'email' => 'admin-'.Str::random(6).'@example.com',
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

        $this->accountInventory = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1050',
            'account_name' => 'Merchandise Inventory',
            'account_type' => 'asset',
            'is_active' => true,
        ]);

        $this->accountEquity = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '3010',
            'account_name' => "Owner's Equity",
            'account_type' => 'equity',
            'is_active' => true,
        ]);

        $this->taxCode = TaxCode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'VAT-15',
            'name_en' => 'Standard VAT 15%',
            'tax_type' => 'standard_vat',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_master_data_import_dry_run_reports_all_row_errors_without_inserting(): void
    {
        $importer = app(MasterDataImportService::class);

        $rowsWithErrors = [
            [
                'code' => 'PRD-ERR-1',
                'name_en' => '', // Empty name (Error)
                'sku' => 'SKU-001',
                'mrp' => '-50.00', // Negative price (Error)
                'cost_price' => '30.00',
                'base_uom' => 'Pcs',
            ],
            [
                'code' => '', // Missing code (Error)
                'name_en' => 'Valid Name',
                'sku' => '', // Missing SKU (Error)
                'mrp' => '100.00',
                'cost_price' => '80.00',
                'base_uom' => 'Pcs',
            ],
        ];

        $result = $importer->importProducts($rowsWithErrors, $this->company->id, dryRun: true);

        $this->assertFalse($result['valid']);
        $this->assertGreaterThanOrEqual(4, count($result['errors']));
        $this->assertEquals(0, $result['processed']);

        // Assert no records inserted
        $this->assertEquals(0, Product::where('tenant_id', $this->tenant->id)->count());
        $this->assertEquals(0, ProductVariant::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_master_data_import_executes_idempotently(): void
    {
        $importer = app(MasterDataImportService::class);

        $validRows = [
            [
                'code' => 'PRD-IMPORT-1',
                'name_en' => 'Imported Tea Packet 500g',
                'sku' => 'TEA-500G',
                'barcode' => '894110012345',
                'mrp' => '250.00',
                'cost_price' => '200.00',
                'base_uom' => 'Pcs',
                'tax_code_id' => $this->taxCode->id,
            ],
        ];

        // First run
        $firstResult = $importer->importProducts($validRows, $this->company->id, dryRun: false);
        $this->assertTrue($firstResult['valid']);
        $this->assertEquals(1, $firstResult['processed']);
        $this->assertEquals(1, Product::where('tenant_id', $this->tenant->id)->count());

        $firstVariant = ProductVariant::where('sku', 'TEA-500G')->first();
        $this->assertNotNull($firstVariant);
        $this->assertEquals('250.00', $firstVariant->mrp);

        // Second run with updated price
        $validRows[0]['mrp'] = '275.00';
        $secondResult = $importer->importProducts($validRows, $this->company->id, dryRun: false);

        $this->assertTrue($secondResult['valid']);
        // Still 1 product and 1 variant, no duplicate created
        $this->assertEquals(1, Product::where('tenant_id', $this->tenant->id)->count());
        $this->assertEquals(1, ProductVariant::where('tenant_id', $this->tenant->id)->count());

        $firstVariant->refresh();
        $this->assertEquals('275.00', $firstVariant->mrp);
    }

    public function test_opening_balances_import_posts_single_balanced_journal_entry(): void
    {
        $importer = app(MasterDataImportService::class);

        $openingLines = [
            [
                'account_id' => $this->accountInventory->id,
                'debit' => '10000.00',
                'credit' => '0.00',
                'line_description' => 'Opening Merchandise Inventory',
            ],
            [
                'account_id' => $this->accountCash->id,
                'debit' => '5000.00',
                'credit' => '0.00',
                'line_description' => 'Opening Cash Balance',
            ],
            [
                'account_id' => $this->accountEquity->id,
                'debit' => '0.00',
                'credit' => '15000.00',
                'line_description' => 'Opening Retained Earnings / Capital',
            ],
        ];

        $entry = $importer->importOpeningBalances(
            balances: $openingLines,
            companyId: $this->company->id,
            periodId: $this->period->id,
            userId: $this->user->id
        );

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertTrue($entry->is_posted);
        $this->assertEquals('OPENING_BALANCE', $entry->reference_type);

        $lines = JournalLine::where('journal_entry_id', $entry->id)->get();
        $this->assertCount(3, $lines);
        $this->assertEquals('15000.00', $lines->sum('debit_amount'));
        $this->assertEquals('15000.00', $lines->sum('credit_amount'));

        // Unbalanced opening balance fails loudly
        $unbalancedLines = [
            [
                'account_id' => $this->accountInventory->id,
                'debit' => '10000.00',
                'credit' => '0.00',
            ],
            [
                'account_id' => $this->accountEquity->id,
                'debit' => '0.00',
                'credit' => '9000.00', // Missing 1000.00!
            ],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Opening balances do not balance/');

        $importer->importOpeningBalances(
            balances: $unbalancedLines,
            companyId: $this->company->id,
            periodId: $this->period->id,
            userId: $this->user->id
        );
    }

    public function test_tenant_export_and_restore_produces_identical_trial_balance_and_stock_value(): void
    {
        // 1. Seed source tenant with balanced journal entry and stock balance
        $entry = JournalEntry::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'period_id' => $this->period->id,
            'entry_number' => 'JV-EXPORT-001',
            'posting_date' => Carbon::now()->toDateString(),
            'business_date' => Carbon::now()->toDateString(),
            'reference_type' => 'MANUAL',
            'is_posted' => true,
            'posted_by' => $this->user->id,
        ]);

        JournalLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'journal_entry_id' => $entry->id,
            'account_id' => $this->accountCash->id,
            'debit_amount' => '2500.00',
            'credit_amount' => '0.00',
            'currency' => 'BDT',
            'exchange_rate' => '1.000000',
            'base_debit_amount' => '2500.00',
            'base_credit_amount' => '0.00',
        ]);

        JournalLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'journal_entry_id' => $entry->id,
            'account_id' => $this->accountEquity->id,
            'debit_amount' => '0.00',
            'credit_amount' => '2500.00',
            'currency' => 'BDT',
            'exchange_rate' => '1.000000',
            'base_debit_amount' => '0.00',
            'base_credit_amount' => '2500.00',
        ]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PRD-EXP-01',
            'name_en' => 'Exportable Item',
            'base_uom' => 'Pcs',
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'tax_code_id' => $this->taxCode->id,
            'sku' => 'EXP-SKU-01',
            'name_en' => 'Exportable Item Variant',
            'base_uom' => 'Pcs',
            'mrp' => '120.00',
            'cost_price' => '100.00',
            'is_active' => true,
        ]);

        StockBalance::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => '50.000',
            'quantity_reserved' => '0.000',
        ]);

        // 2. Export tenant data
        $exportService = app(TenantExportService::class);
        $archive = $exportService->export($this->tenant->id);

        $this->assertNotEmpty($archive['metadata']);
        $this->assertEquals($this->tenant->id, $archive['metadata']['tenant_id']);
        $this->assertEquals('2500.00', $archive['metadata']['trial_balance']['total_debits']);
        $this->assertEquals('2500.00', $archive['metadata']['trial_balance']['total_credits']);
        $this->assertEquals('50.000', $archive['metadata']['stock_summary']['total_quantity']);

        // 3. Create target tenant and restore
        $targetTenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Restored Tenant Target',
            'subdomain' => 'restored-'.Str::random(6),
            'status' => 'active',
        ]);

        $restoreService = app(TenantRestoreService::class);
        $restoreResult = $restoreService->restore($archive, $targetTenant->id);

        $this->assertTrue($restoreResult['success']);
        $this->assertEquals('2500.00', $restoreResult['trial_balance']['total_debits']);
        $this->assertEquals('2500.00', $restoreResult['trial_balance']['total_credits']);

        // 4. Verify target tenant isolation and numbers under TenantContext
        TenantContext::set($targetTenant);

        $restoredDebits = DB::table('journal_lines')
            ->where('tenant_id', $targetTenant->id)
            ->sum('debit_amount');
        $restoredCredits = DB::table('journal_lines')
            ->where('tenant_id', $targetTenant->id)
            ->sum('credit_amount');

        $this->assertEquals('2500.00', number_format((float) $restoredDebits, 2, '.', ''));
        $this->assertEquals('2500.00', number_format((float) $restoredCredits, 2, '.', ''));

        $restoredQty = DB::table('stock_balances')
            ->where('tenant_id', $targetTenant->id)
            ->sum('quantity_on_hand');
        $this->assertEquals('50.000', number_format((float) $restoredQty, 3, '.', ''));
    }

    public function test_tenant_offboarding_workflow_with_export_and_controlled_purge(): void
    {
        $offboardService = app(TenantOffboardingService::class);

        $result = $offboardService->offboard($this->tenant->id, purgeImmediately: true);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['archive_id']);
        $this->assertGreaterThan(0, $result['purged_records_count']);

        // Verify tenant is marked offboarded or purged
        $tenantStatus = DB::table('tenants')->where('id', $this->tenant->id)->value('status');
        $this->assertNull($tenantStatus, 'Purged tenant record should be removed following offboarding purge');

        // Confirm tenant data tables are clean
        $remainingLines = DB::connection('pgsql_owner')
            ->table('journal_lines')
            ->where('tenant_id', $this->tenant->id)
            ->count();
        $this->assertEquals(0, $remainingLines);
    }
}
