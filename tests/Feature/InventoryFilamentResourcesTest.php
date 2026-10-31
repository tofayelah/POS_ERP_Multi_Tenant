<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Filament\Resources\LandedCosts\LandedCostResource;
use App\Filament\Resources\StockBalances\StockBalanceResource;
use App\Filament\Resources\StockCountSessions\StockCountSessionResource;
use App\Filament\Resources\StockLocations\StockLocationResource;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Filament\Resources\StockOversellExceptions\StockOversellExceptionResource;
use App\Filament\Resources\StockTransfers\StockTransferResource;
use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Models\Company;
use App\Models\LandedCost;
use App\Models\StockBalance;
use App\Models\StockCountSession;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockOversellException;
use App\Models\StockTransfer;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryFilamentResourcesTest extends TestCase
{
    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Company $companyA;

    protected Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant A',
            'subdomain' => 'tenant-a-'.Str::random(6),
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant B',
            'subdomain' => 'tenant-b-'.Str::random(6),
            'status' => 'active',
        ]);

        $this->companyA = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Company A',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->companyB = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'legal_name' => 'Company B',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_all_inventory_filament_resources_exist_and_bind_correct_models(): void
    {
        $this->assertEquals(Warehouse::class, WarehouseResource::getModel());
        $this->assertEquals(StockLocation::class, StockLocationResource::getModel());
        $this->assertEquals(StockBalance::class, StockBalanceResource::getModel());
        $this->assertEquals(StockMovement::class, StockMovementResource::getModel());
        $this->assertEquals(StockTransfer::class, StockTransferResource::getModel());
        $this->assertEquals(StockCountSession::class, StockCountSessionResource::getModel());
        $this->assertEquals(LandedCost::class, LandedCostResource::getModel());
        $this->assertEquals(StockOversellException::class, StockOversellExceptionResource::getModel());
    }

    public function test_tenant_isolation_on_inventory_back_office_models(): void
    {
        // Create warehouse in Tenant A
        TenantContext::set($this->tenantA);
        $whA = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'code' => 'WH-A',
            'name' => 'Warehouse A',
            'is_active' => true,
        ]);

        // Create warehouse in Tenant B
        TenantContext::set($this->tenantB);
        $whB = Warehouse::create([
            'tenant_id' => $this->tenantB->id,
            'company_id' => $this->companyB->id,
            'code' => 'WH-B',
            'name' => 'Warehouse B',
            'is_active' => true,
        ]);

        // Tenant A only sees WH-A
        TenantContext::set($this->tenantA);
        $warehousesA = Warehouse::all();
        $this->assertTrue($warehousesA->contains('id', $whA->id));
        $this->assertFalse($warehousesA->contains('id', $whB->id));

        // Tenant B only sees WH-B
        TenantContext::set($this->tenantB);
        $warehousesB = Warehouse::all();
        $this->assertTrue($warehousesB->contains('id', $whB->id));
        $this->assertFalse($warehousesB->contains('id', $whA->id));
    }
}
