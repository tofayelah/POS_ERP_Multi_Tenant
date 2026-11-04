<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockBalanceDatabaseTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Warehouse $warehouse;

    protected StockLocation $stockLocation;

    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Stock Balance Tenant',
            'subdomain' => 'bal-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Balance Test Company',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'WH-BAL-01',
            'name' => 'Bal Warehouse',
            'is_active' => true,
        ]);

        $this->stockLocation = StockLocation::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'code' => 'LOC-B1',
            'name' => 'Location B1',
            'location_type' => 'picking',
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
            'code' => 'PROD-BAL-01',
            'name_en' => 'Balance Product',
            'base_uom' => 'Pcs',
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'tax_code_id' => $taxCode->id,
            'sku' => 'SKU-BAL-001',
            'name_en' => 'Balance Variant',
            'base_uom' => 'Pcs',
            'cost_price' => '100.0000',
            'mrp' => '150.0000',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_can_create_and_update_stock_balance(): void
    {
        $balance = StockBalance::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_location_id' => $this->stockLocation->id,
            'product_variant_id' => $this->variant->id,
            'batch_number' => 'BATCH-001',
            'quantity_on_hand' => '10.0000',
            'quantity_reserved' => '2.0000',
            'weighted_avg_cost' => '100.0000',
        ]);

        $this->assertDatabaseHas('stock_balances', [
            'id' => $balance->id,
            'quantity_on_hand' => '10.0000',
            'quantity_reserved' => '2.0000',
            'weighted_avg_cost' => '100.0000',
        ]);

        $this->assertEquals('8.0000', $balance->quantity_available);
    }

    public function test_duplicate_stock_balance_is_rejected_by_unique_constraint(): void
    {
        StockBalance::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_location_id' => $this->stockLocation->id,
            'product_variant_id' => $this->variant->id,
            'batch_number' => 'BATCH-SAME',
            'quantity_on_hand' => '10.0000',
        ]);

        $this->expectException(QueryException::class);

        StockBalance::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_location_id' => $this->stockLocation->id,
            'product_variant_id' => $this->variant->id,
            'batch_number' => 'BATCH-SAME',
            'quantity_on_hand' => '5.0000',
        ]);
    }

    public function test_negative_balance_is_rejected_in_standard_online_context(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Inventory Balance Violation: Negative stock balance is prohibited');

        StockBalance::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_location_id' => $this->stockLocation->id,
            'product_variant_id' => $this->variant->id,
            'quantity_on_hand' => '-5.0000',
        ]);
    }

    public function test_negative_balance_is_permitted_in_offline_oversell_sync_context(): void
    {
        // In transaction with app.allow_negative_stock enabled per ADR-016
        DB::transaction(function () {
            DB::statement("SELECT set_config('app.allow_negative_stock', 'true', true)");

            $balance = StockBalance::create([
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'warehouse_id' => $this->warehouse->id,
                'stock_location_id' => $this->stockLocation->id,
                'product_variant_id' => $this->variant->id,
                'quantity_on_hand' => '-3.0000',
                'weighted_avg_cost' => '100.0000',
            ]);

            $this->assertDatabaseHas('stock_balances', [
                'id' => $balance->id,
                'quantity_on_hand' => '-3.0000',
            ]);
        });
    }
}
