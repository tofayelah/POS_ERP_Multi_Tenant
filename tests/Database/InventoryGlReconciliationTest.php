<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Accounting\Contracts\AccountingPostingServiceContract;
use App\Modules\Accounting\DTOs\JournalEntryDTO;
use App\Modules\Accounting\DTOs\JournalLineDTO;
use App\Modules\Inventory\Contracts\InventoryGlReconciliationContract;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\DTOs\StockMovementDTO;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryGlReconciliationTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected FiscalYear $fiscalYear;

    protected AccountingPeriod $period;

    protected User $user;

    protected ChartOfAccount $inventoryAccount;

    protected ChartOfAccount $apAccount;

    protected ChartOfAccount $cogsAccount;

    protected ChartOfAccount $adjustmentAccount;

    protected Warehouse $warehouse;

    protected ProductVariant $variant;

    protected StockMovementServiceContract $stockService;

    protected InventoryGlReconciliationContract $reconciliationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'GL Reconcile Tenant',
            'subdomain' => 'reconcile-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Reconciliation Test Company',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Accountant User',
            'email' => 'acct-'.Str::random(6).'@example.com',
            'password' => 'password',
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

        $this->inventoryAccount = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1040',
            'account_name' => 'Merchandise Inventory',
            'account_type' => 'asset',
            'is_reconcilable' => true,
            'is_active' => true,
        ]);

        $this->apAccount = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '2010',
            'account_name' => 'Accounts Payable',
            'account_type' => 'liability',
            'is_reconcilable' => false,
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

        $this->adjustmentAccount = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '5030',
            'account_name' => 'Inventory Adjustment',
            'account_type' => 'expense',
            'is_reconcilable' => false,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'WH-REC-01',
            'name' => 'Reconciliation Warehouse',
            'is_active' => true,
        ]);

        $taxCode = TaxCode::create([
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
            'code' => 'PROD-REC',
            'name_en' => 'Reconciliation Product',
            'base_uom' => 'Pcs',
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'tax_code_id' => $taxCode->id,
            'sku' => 'SKU-REC-001',
            'name_en' => 'Reconciliation Variant',
            'base_uom' => 'Pcs',
            'cost_price' => '150.0000',
            'mrp' => '200.0000',
            'is_active' => true,
        ]);

        $this->stockService = app(StockMovementServiceContract::class);
        $this->reconciliationService = app(InventoryGlReconciliationContract::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_purchase_receipt_reconciles_inventory_valuation_to_gl_account_1040(): void
    {
        DB::transaction(function () {
            $movement = $this->stockService->recordMovement(new StockMovementDTO(
                companyId: $this->company->id,
                warehouseId: $this->warehouse->id,
                stockLocationId: null,
                productVariantId: $this->variant->id,
                movementType: 'PURCHASE_RECEIPT',
                quantity: '100.0000',
                unitCost: '150.0000',
                referenceType: 'goods_received_note',
                referenceId: (string) Str::uuid()
            ));

            $journal = $this->reconciliationService->postInventoryGlJournal($movement, $this->user->id);
            $this->assertNotNull($journal);
            $this->assertTrue($journal->is_posted);
        });

        $report = $this->reconciliationService->getReconciliationReport($this->company->id);

        $this->assertTrue($report->isReconciled);
        $this->assertEquals('15000.00', $report->stockValuation);
        $this->assertEquals('15000.00', $report->glBalance);
        $this->assertEquals('0.00', $report->variance);
    }

    public function test_sale_movement_records_cogs_and_maintains_gl_reconciliation(): void
    {
        // 1. Initial purchase: 100 units @ 150 BDT
        DB::transaction(function () {
            $receipt = $this->stockService->recordMovement(new StockMovementDTO(
                companyId: $this->company->id,
                warehouseId: $this->warehouse->id,
                stockLocationId: null,
                productVariantId: $this->variant->id,
                movementType: 'PURCHASE_RECEIPT',
                quantity: '100.0000',
                unitCost: '150.0000',
                referenceType: 'goods_received_note',
                referenceId: (string) Str::uuid()
            ));
            $this->reconciliationService->postInventoryGlJournal($receipt, $this->user->id);
        });

        // 2. Sale: 30 units (COGS: 30 * 150 = 4,500 BDT)
        DB::transaction(function () {
            $sale = $this->stockService->recordMovement(new StockMovementDTO(
                companyId: $this->company->id,
                warehouseId: $this->warehouse->id,
                stockLocationId: null,
                productVariantId: $this->variant->id,
                movementType: 'SALE',
                quantity: '-30.0000',
                unitCost: '150.0000',
                referenceType: 'sales_invoice',
                referenceId: (string) Str::uuid()
            ));
            $this->reconciliationService->postInventoryGlJournal($sale, $this->user->id);
        });

        $report = $this->reconciliationService->getReconciliationReport($this->company->id);

        // 70 units @ 150 BDT = 10,500.00 BDT
        $this->assertTrue($report->isReconciled);
        $this->assertEquals('10500.00', $report->stockValuation);
        $this->assertEquals('10500.00', $report->glBalance);
        $this->assertEquals('0.00', $report->variance);
    }

    public function test_stock_count_adjustment_maintains_gl_reconciliation(): void
    {
        // 1. Initial purchase: 50 units @ 150 BDT = 7,500 BDT
        DB::transaction(function () {
            $receipt = $this->stockService->recordMovement(new StockMovementDTO(
                companyId: $this->company->id,
                warehouseId: $this->warehouse->id,
                stockLocationId: null,
                productVariantId: $this->variant->id,
                movementType: 'PURCHASE_RECEIPT',
                quantity: '50.0000',
                unitCost: '150.0000',
                referenceType: 'goods_received_note',
                referenceId: (string) Str::uuid()
            ));
            $this->reconciliationService->postInventoryGlJournal($receipt, $this->user->id);
        });

        // 2. Physical count write-down of -5 units (750 BDT shrinkage)
        DB::transaction(function () {
            $adjustment = $this->stockService->recordMovement(new StockMovementDTO(
                companyId: $this->company->id,
                warehouseId: $this->warehouse->id,
                stockLocationId: null,
                productVariantId: $this->variant->id,
                movementType: 'STOCK_COUNT_ADJUSTMENT',
                quantity: '-5.0000',
                unitCost: '150.0000',
                referenceType: 'stock_count_session',
                referenceId: (string) Str::uuid()
            ));
            $this->reconciliationService->postInventoryGlJournal($adjustment, $this->user->id);
        });

        $report = $this->reconciliationService->getReconciliationReport($this->company->id);

        // 45 units @ 150 BDT = 6,750.00 BDT
        $this->assertTrue($report->isReconciled);
        $this->assertEquals('6750.00', $report->stockValuation);
        $this->assertEquals('6750.00', $report->glBalance);
        $this->assertEquals('0.00', $report->variance);
    }

    public function test_reconciler_flags_unbacked_manual_gl_discrepancy(): void
    {
        // 1. Initial purchase: 10 units @ 150 BDT = 1,500.00 BDT
        DB::transaction(function () {
            $receipt = $this->stockService->recordMovement(new StockMovementDTO(
                companyId: $this->company->id,
                warehouseId: $this->warehouse->id,
                stockLocationId: null,
                productVariantId: $this->variant->id,
                movementType: 'PURCHASE_RECEIPT',
                quantity: '10.0000',
                unitCost: '150.0000',
                referenceType: 'goods_received_note',
                referenceId: (string) Str::uuid()
            ));
            $this->reconciliationService->postInventoryGlJournal($receipt, $this->user->id);
        });

        // 2. Post an unbacked manual journal debiting Account 1040 by 500 BDT directly (rogue entry)
        /** @var AccountingPostingServiceContract $postingService */
        $postingService = app(AccountingPostingServiceContract::class);
        DB::transaction(function () use ($postingService) {
            $postingService->post(new JournalEntryDTO(
                companyId: $this->company->id,
                periodId: $this->period->id,
                documentType: 'JV',
                postingDate: Carbon::now()->toDateString(),
                businessDate: Carbon::now()->toDateString(),
                referenceType: 'manual_rogue',
                referenceId: null,
                postedBy: $this->user->id,
                narration: 'Rogue manual debit to inventory',
                lines: [
                    new JournalLineDTO(
                        accountId: $this->inventoryAccount->id,
                        debitAmount: Money::of('500.00'),
                        creditAmount: Money::zero(),
                    ),
                    new JournalLineDTO(
                        accountId: $this->apAccount->id,
                        debitAmount: Money::zero(),
                        creditAmount: Money::of('500.00'),
                    ),
                ]
            ));
        });

        $report = $this->reconciliationService->getReconciliationReport($this->company->id);

        // Stock Valuation = 1,500.00 BDT, GL Balance = 2,000.00 BDT -> Discrepancy of -500.00 BDT
        $this->assertFalse($report->isReconciled);
        $this->assertEquals('15000.00', (string) Money::of($report->stockValuation)->multiply(10)->getAmount()); // sanity
        $this->assertEquals('1500.00', $report->stockValuation);
        $this->assertEquals('2000.00', $report->glBalance);
        $this->assertEquals('-500.00', $report->variance);
    }
}
