<?php

namespace Tests\Unit;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\CostLayer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Modules\Inventory\Contracts\CostingServiceContract;
use Illuminate\Support\Str;
use Tests\TestCase;

class CostingServiceTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Warehouse $warehouse;

    protected ProductVariant $variant;

    protected CostingServiceContract $costingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Costing Tenant',
            'subdomain' => 'cost-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Costing Company',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'WH-COST-01',
            'name' => 'Costing Warehouse',
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
            'code' => 'PROD-COST-01',
            'name_en' => 'Costing Product',
            'base_uom' => 'Pcs',
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'tax_code_id' => $taxCode->id,
            'sku' => 'SKU-COST-001',
            'name_en' => 'Costing Variant',
            'base_uom' => 'Pcs',
            'cost_price' => '50.0000',
            'mrp' => '100.0000',
            'is_active' => true,
        ]);

        $this->costingService = app(CostingServiceContract::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_fifo_consumption_consumes_earliest_layers_first(): void
    {
        // Layer 1: 10 units @ 50.0000 received 2026-11-01
        $this->costingService->addCostLayer(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variant->id,
            quantity: '10.0000',
            unitCost: '50.0000',
            receiptDate: '2026-11-01',
            referenceType: 'goods_received_note',
            referenceId: (string) Str::uuid()
        );

        // Layer 2: 10 units @ 70.0000 received 2026-11-05
        $this->costingService->addCostLayer(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variant->id,
            quantity: '10.0000',
            unitCost: '70.0000',
            receiptDate: '2026-11-05',
            referenceType: 'goods_received_note',
            referenceId: (string) Str::uuid()
        );

        // Act: Consume 15 units
        $result = $this->costingService->consumeFifoLayers(
            warehouseId: $this->warehouse->id,
            productVariantId: $this->variant->id,
            quantityToConsume: '15.0000'
        );

        // Assert: Total COGS = (10 * 50) + (5 * 70) = 500 + 350 = 850.0000
        $this->assertEquals('850.0000', $result->totalCogs);
        $this->assertEquals('15.0000', $result->consumedQuantity);
        $this->assertEquals('0.0000', $result->remainingShortfall);

        // Assert: Layer 1 is fully depleted (0.0000 remaining)
        // Assert: Layer 2 has 5.0000 remaining
        $layers = CostLayer::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->orderBy('receipt_date', 'ASC')
            ->get();

        $this->assertEquals('0.0000', $layers[0]->quantity_remaining);
        $this->assertEquals('5.0000', $layers[1]->quantity_remaining);
    }

    public function test_wac_calculation_computes_moving_average_accurately(): void
    {
        // 10 units @ 50 BDT, received 10 units @ 70 BDT -> New WAC = (500 + 700) / 20 = 1200 / 20 = 60.0000
        $newWac = $this->costingService->calculateNewWac(
            currentQty: '10.0000',
            currentWac: '50.0000',
            inboundQty: '10.0000',
            inboundCost: '70.0000'
        );

        $this->assertEquals('60.0000', $newWac);

        // When current quantity is 0 or negative, incoming cost becomes the new WAC
        $wacFromZero = $this->costingService->calculateNewWac(
            currentQty: '0.0000',
            currentWac: '50.0000',
            inboundQty: '5.0000',
            inboundCost: '80.0000'
        );

        $this->assertEquals('80.0000', $wacFromZero);
    }
}
