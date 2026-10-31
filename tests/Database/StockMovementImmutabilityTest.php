<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockMovementImmutabilityTest extends TestCase
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
            'name' => 'Stock Movement Tenant',
            'subdomain' => 'mov-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Movement Test Company',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'WH-MOV-01',
            'name' => 'Main Warehouse',
            'is_active' => true,
        ]);

        $this->stockLocation = StockLocation::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'code' => 'BIN-A1',
            'name' => 'Bin A1',
            'location_type' => 'picking',
            'is_active' => true,
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Test Product',
            'code' => 'PROD-01',
            'type' => 'goods',
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'sku' => 'SKU-001',
            'name' => 'Test Variant Standard',
            'cost_price' => '100.0000',
            'retail_price' => '150.0000',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_can_insert_valid_stock_movement(): void
    {
        $movement = StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_location_id' => $this->stockLocation->id,
            'product_variant_id' => $this->variant->id,
            'batch_number' => 'BATCH-2026-001',
            'expiry_date' => Carbon::now()->addYear()->toDateString(),
            'movement_type' => 'PURCHASE_RECEIPT',
            'quantity' => '50.0000',
            'unit_cost' => '100.0000',
            'total_cost' => '5000.0000',
            'reference_type' => 'goods_received_note',
            'reference_id' => (string) Str::uuid(),
            'movement_date' => Carbon::now(),
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'id' => $movement->id,
            'movement_type' => 'PURCHASE_RECEIPT',
            'quantity' => '50.0000',
            'stock_location_id' => $this->stockLocation->id,
        ]);

        $this->assertInstanceOf(Warehouse::class, $movement->warehouse);
        $this->assertInstanceOf(StockLocation::class, $movement->stockLocation);
        $this->assertInstanceOf(ProductVariant::class, $movement->productVariant);
    }

    public function test_updating_existing_stock_movement_fails_via_immutability_trigger(): void
    {
        $movement = StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_location_id' => $this->stockLocation->id,
            'product_variant_id' => $this->variant->id,
            'movement_type' => 'PURCHASE_RECEIPT',
            'quantity' => '50.0000',
            'unit_cost' => '100.0000',
            'total_cost' => '5000.0000',
            'reference_type' => 'goods_received_note',
            'reference_id' => (string) Str::uuid(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Inventory Audit Violation: Stock movements are an append-only ledger');

        DB::table('stock_movements')
            ->where('id', $movement->id)
            ->update(['quantity' => '60.0000']);
    }

    public function test_deleting_existing_stock_movement_fails_via_immutability_trigger(): void
    {
        $movement = StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_location_id' => $this->stockLocation->id,
            'product_variant_id' => $this->variant->id,
            'movement_type' => 'PURCHASE_RECEIPT',
            'quantity' => '50.0000',
            'unit_cost' => '100.0000',
            'total_cost' => '5000.0000',
            'reference_type' => 'goods_received_note',
            'reference_id' => (string) Str::uuid(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Inventory Audit Violation: Stock movements are an append-only ledger');

        DB::table('stock_movements')
            ->where('id', $movement->id)
            ->delete();
    }
}
