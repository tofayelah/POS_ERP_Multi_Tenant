<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Modules\Inventory\Contracts\StockCountServiceContract;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\DTOs\StockMovementDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockCountSessionTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Warehouse $warehouse;

    protected ProductVariant $variant;

    protected StockCountServiceContract $countService;

    protected StockMovementServiceContract $movementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Stock Count Tenant',
            'subdomain' => 'count-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Count Test Company',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'WH-COUNT-01',
            'name' => 'Count Warehouse',
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
            'code' => 'PROD-COUNT-01',
            'name_en' => 'Count Product',
            'base_uom' => 'Pcs',
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'tax_code_id' => $taxCode->id,
            'sku' => 'SKU-COUNT-001',
            'name_en' => 'Count Variant',
            'base_uom' => 'Pcs',
            'cost_price' => '100.0000',
            'mrp' => '150.0000',
            'is_active' => true,
        ]);

        // Initial receipt of 100 units
        $this->movementService = app(StockMovementServiceContract::class);
        DB::transaction(fn () => $this->movementService->recordMovement(new StockMovementDTO(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variant->id,
            movementType: 'PURCHASE_RECEIPT',
            quantity: '100.0000',
            unitCost: '100.0000',
            referenceType: 'initial_stock',
            referenceId: (string) Str::uuid()
        )));

        $this->countService = app(StockCountServiceContract::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_stock_count_session_reconciles_in_flight_movements_accurately(): void
    {
        // 1. Start count session -> snapshots 100 units at T0
        $session = $this->countService->startSession(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id
        );

        $line = $session->lines()->where('product_variant_id', $this->variant->id)->firstOrFail();
        $this->assertEquals('100.0000', $line->snapshot_quantity);

        // 2. While count is in progress, store cashier sells 10 units (in-flight movement between T0 and T1)
        DB::transaction(fn () => $this->movementService->recordMovement(new StockMovementDTO(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variant->id,
            movementType: 'SALE',
            quantity: '-10.0000',
            unitCost: '100.0000',
            referenceType: 'sales_invoice',
            referenceId: (string) Str::uuid()
        )));

        // 3. Counter counts 90 units on shelf
        $this->countService->recordCount(
            sessionId: $session->id,
            productVariantId: $this->variant->id,
            countedQuantity: '90.0000'
        );

        // 4. Reconcile and approve: Expected = Snapshot (100) - Sale (10) = 90. Counted = 90. Variance = 0.
        $reconciled = $this->countService->reconcileAndApprove($session->id);

        $this->assertEquals('reconciled', $reconciled->status);
        $reconciledLine = $reconciled->lines()->where('product_variant_id', $this->variant->id)->firstOrFail();
        $this->assertEquals('-10.0000', $reconciledLine->in_flight_movements);
        $this->assertEquals('0.0000', $reconciledLine->variance_quantity);

        // No adjustment movement needed because variance is 0
        $adjustmentMovements = StockMovement::where('reference_type', 'stock_count_session')
            ->where('reference_id', $session->id)
            ->count();
        $this->assertEquals(0, $adjustmentMovements);
    }

    public function test_stock_count_with_physical_variance_posts_adjustment_movement(): void
    {
        // Start count session (snapshots 100 units)
        $session = $this->countService->startSession(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id
        );

        // Counter finds 98 units (2 units missing / shrinkage)
        $this->countService->recordCount(
            sessionId: $session->id,
            productVariantId: $this->variant->id,
            countedQuantity: '98.0000'
        );

        $reconciled = $this->countService->reconcileAndApprove($session->id);

        $reconciledLine = $reconciled->lines()->where('product_variant_id', $this->variant->id)->firstOrFail();
        $this->assertEquals('-2.0000', $reconciledLine->variance_quantity);

        // Assert adjustment movement was posted for -2.0000
        $adjustment = StockMovement::where('reference_type', 'stock_count_session')
            ->where('reference_id', $session->id)
            ->firstOrFail();

        $this->assertEquals('STOCK_COUNT_ADJUSTMENT', $adjustment->movement_type);
        $this->assertEquals('-2.0000', $adjustment->quantity);

        // Assert stock balance updated to 98.0000
        $balance = StockBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->value('quantity_on_hand');
        $this->assertEquals('98.0000', $balance);
    }
}
