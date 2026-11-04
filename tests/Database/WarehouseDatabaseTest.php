<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\StockLocation;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Tests\TestCase;

class WarehouseDatabaseTest extends TestCase
{
    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Company $companyA;

    protected Company $companyB;

    protected Branch $branchA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Warehouse Tenant A',
            'subdomain' => 'wh-a-'.Str::random(6),
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Warehouse Tenant B',
            'subdomain' => 'wh-b-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenantA);

        $this->companyA = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Company A',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branchA = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'name' => 'Branch A1',
            'code' => 'BR-A1',
            'is_active' => true,
        ]);

        TenantContext::set($this->tenantB);

        $this->companyB = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'legal_name' => 'Company B',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        TenantContext::set($this->tenantA);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_can_create_warehouse_belonging_to_company_with_optional_branch(): void
    {
        // Warehouse without branch link (e.g. Central Distribution Centre)
        $whCentral = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => null,
            'code' => 'CDC-01',
            'name' => 'Central Distribution Centre',
            'address' => 'Tejgaon, Dhaka',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => $whCentral->id,
            'code' => 'CDC-01',
            'branch_id' => null,
        ]);

        // Warehouse linked to a branch (e.g. Retail Outlet Stockroom)
        $whRetail = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'code' => 'RETAIL-01',
            'name' => 'Dhanmondi Branch Stockroom',
            'address' => 'Road 27, Dhanmondi',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => $whRetail->id,
            'code' => 'RETAIL-01',
            'branch_id' => $this->branchA->id,
        ]);

        $this->assertInstanceOf(Company::class, $whRetail->company);
        $this->assertInstanceOf(Branch::class, $whRetail->branch);
    }

    public function test_warehouse_cannot_reference_branch_from_another_company_or_tenant(): void
    {
        $this->expectException(QueryException::class);

        // Attempt to create warehouse under companyA referencing a branch from another tenant/company
        // Creating a rogue branch under companyB
        $rogueBranch = Branch::create([
            'tenant_id' => $this->tenantB->id,
            'company_id' => $this->companyB->id,
            'name' => 'Rogue Branch',
            'code' => 'ROGUE-01',
            'is_active' => true,
        ]);

        Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $rogueBranch->id,
            'code' => 'INVALID-WH',
            'name' => 'Invalid Cross-Company Warehouse',
            'is_active' => true,
        ]);
    }

    public function test_can_create_hierarchical_stock_locations_inside_warehouse(): void
    {
        $wh = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => null,
            'code' => 'WH-MAIN',
            'name' => 'Main Warehouse',
            'is_active' => true,
        ]);

        // Top-level location: Zone A
        $zoneA = StockLocation::create([
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'warehouse_id' => $wh->id,
            'parent_id' => null,
            'code' => 'ZONE-A',
            'name' => 'Zone A - FMCG',
            'location_type' => 'bulk',
            'is_active' => true,
        ]);

        // Child location: Rack 1 under Zone A
        $rack1 = StockLocation::create([
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'warehouse_id' => $wh->id,
            'parent_id' => $zoneA->id,
            'code' => 'RACK-01',
            'name' => 'Rack 01',
            'location_type' => 'picking',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('stock_locations', [
            'id' => $rack1->id,
            'parent_id' => $zoneA->id,
            'warehouse_id' => $wh->id,
        ]);

        $this->assertEquals($zoneA->id, $rack1->parent->id);
        $this->assertCount(1, $zoneA->children);
    }

    public function test_forced_rls_isolates_warehouses_and_stock_locations_between_tenants(): void
    {
        // Tenant A creates warehouse
        TenantContext::set($this->tenantA);
        $whA = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'code' => 'WH-A',
            'name' => 'Warehouse A',
            'is_active' => true,
        ]);

        $locA = StockLocation::create([
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'warehouse_id' => $whA->id,
            'code' => 'LOC-A',
            'name' => 'Location A',
            'location_type' => 'picking',
            'is_active' => true,
        ]);

        // Tenant B creates warehouse
        TenantContext::set($this->tenantB);
        $whB = Warehouse::create([
            'tenant_id' => $this->tenantB->id,
            'company_id' => $this->companyB->id,
            'code' => 'WH-B',
            'name' => 'Warehouse B',
            'is_active' => true,
        ]);

        // As Tenant B, should only see Warehouse B
        $warehousesSeenByB = Warehouse::all();
        $this->assertTrue($warehousesSeenByB->contains('id', $whB->id));
        $this->assertFalse($warehousesSeenByB->contains('id', $whA->id));

        $locationsSeenByB = StockLocation::all();
        $this->assertFalse($locationsSeenByB->contains('id', $locA->id));

        // Switch back to Tenant A
        TenantContext::set($this->tenantA);
        $warehousesSeenByA = Warehouse::all();
        $this->assertTrue($warehousesSeenByA->contains('id', $whA->id));
        $this->assertFalse($warehousesSeenByA->contains('id', $whB->id));
    }
}
