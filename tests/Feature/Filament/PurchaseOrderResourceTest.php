<?php

namespace Tests\Feature\Filament;

use App\Core\Tenancy\TenantContext;
use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\UserLocationScope;
use App\Modules\Procurement\Contracts\ProcurementDocumentChainContract;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PurchaseOrderResourceTest extends TestCase
{
    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Company $companyA;

    protected Company $companyB;

    protected Branch $branchA;

    protected Supplier $supplierA;

    protected TaxCode $taxCodeA;

    protected Product $productA;

    protected ProductVariant $variantA;

    protected User $procurementMgr;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Setup Tenant A
        $this->tenantA = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Acme Procurement Tenant A',
            'subdomain' => 'po-a-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenantA);
        Filament::setTenant($this->tenantA);
        setPermissionsTeamId($this->tenantA->id);

        $this->companyA = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Acme Supplies Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branchA = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'name' => 'Main Depot Branch',
            'code' => 'MDB-01',
            'is_active' => true,
        ]);

        $this->supplierA = Supplier::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'name' => 'National Steel Co',
            'is_active' => true,
        ]);

        $this->taxCodeA = TaxCode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'code' => 'TAX-EX-PO',
            'name_en' => 'Exempt Purchase Code',
            'description' => 'Exempt code',
            'is_active' => true,
        ]);

        $this->productA = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'code' => 'PRD-BAR',
            'name_en' => 'Steel Rod',
            'base_uom' => 'KG',
        ]);

        $this->variantA = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'product_id' => $this->productA->id,
            'tax_code_id' => $this->taxCodeA->id,
            'sku' => 'SKU-ROD-12',
            'name_en' => '12mm Steel Rod',
            'cost_price' => '150.0000',
        ]);

        Role::firstOrCreate([
            'name' => 'Procurement Manager',
            'guard_name' => 'web',
            'tenant_id' => $this->tenantA->id,
        ]);

        $this->procurementMgr = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'PO Officer',
            'email' => 'po-officer-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);
        $this->procurementMgr->assignRole('Procurement Manager');

        $membershipA = TenantMembership::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->procurementMgr->id,
            'is_active' => true,
        ]);

        UserLocationScope::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'membership_id' => $membershipA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => null, // company-wide
        ]);

        // 2. Setup Tenant B
        $this->tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Competitor Tenant B',
            'subdomain' => 'po-b-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenantB);
        $this->companyB = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'legal_name' => 'Competitor Corp',
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

    public function test_purchase_order_resource_binds_correct_model_and_navigation(): void
    {
        $this->assertEquals(PurchaseOrder::class, PurchaseOrderResource::getModel());
        $this->assertEquals('Procurement', PurchaseOrderResource::getNavigationGroup());
    }

    public function test_purchase_order_creation_delegates_to_domain_service_and_preserves_stock(): void
    {
        TenantContext::set($this->tenantA);
        $this->actingAs($this->procurementMgr);

        // Pre-condition: Zero stock balances and movements
        $initialMovements = StockMovement::count();
        $initialBalances = StockBalance::count();

        // Submit creation via Livewire / Filament CreateRecord page
        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm([
                'company_id' => $this->companyA->id,
                'branch_id' => $this->branchA->id,
                'supplier_id' => $this->supplierA->id,
                'order_date' => Carbon::today()->toDateString(),
                'expected_delivery_date' => Carbon::today()->addDays(5)->toDateString(),
                'lines' => [
                    [
                        'product_id' => $this->productA->id,
                        'variant_id' => $this->variantA->id,
                        'ordered_quantity' => '50.0000',
                        'unit_price' => '150.0000',
                        'tax_amount' => '0.0000',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Assert purchase order created
        $po = PurchaseOrder::where('company_id', $this->companyA->id)->latest()->first();
        $this->assertNotNull($po);
        $this->assertStringStartsWith('PO-', $po->order_number);
        $this->assertEquals('draft', $po->status);
        $this->assertEquals('7500.0000', $po->grand_total);
        $this->assertCount(1, $po->lines);
        $this->assertEquals('50.0000', $po->lines->first()->ordered_quantity);
        $this->assertEquals('0.0000', $po->lines->first()->received_quantity);

        // Critical Invariant: PO must NEVER alter stock movements or balances
        $this->assertEquals($initialMovements, StockMovement::count());
        $this->assertEquals($initialBalances, StockBalance::count());
    }

    public function test_purchase_order_confirmation_and_cancellation_actions(): void
    {
        TenantContext::set($this->tenantA);
        $this->actingAs($this->procurementMgr);

        // Create draft PO via service
        /** @var ProcurementDocumentChainContract $service */
        $service = app(ProcurementDocumentChainContract::class);
        $po = PurchaseOrder::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'supplier_id' => $this->supplierA->id,
            'order_number' => 'PO-TEST-CONF-01',
            'order_date' => Carbon::today()->toDateString(),
            'subtotal' => '3000.0000',
            'tax_amount' => '0.0000',
            'grand_total' => '3000.0000',
            'status' => 'draft',
            'created_by' => $this->procurementMgr->id,
        ]);

        // Confirm order
        $service->confirmPurchaseOrder($po->id);
        $po->refresh();
        $this->assertEquals('confirmed', $po->status);

        // Cancel order (with 0 receipts)
        $service->cancelPurchaseOrder($po->id);
        $po->refresh();
        $this->assertEquals('cancelled', $po->status);
    }

    public function test_tenant_isolation_on_purchase_orders(): void
    {
        // Create PO in Tenant A
        TenantContext::set($this->tenantA);
        $poA = PurchaseOrder::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'supplier_id' => $this->supplierA->id,
            'order_number' => 'PO-ISO-TENANT-A',
            'order_date' => Carbon::today()->toDateString(),
            'subtotal' => '1000.0000',
            'tax_amount' => '0.0000',
            'grand_total' => '1000.0000',
            'status' => 'draft',
            'created_by' => $this->procurementMgr->id,
        ]);

        // Switch to Tenant B
        TenantContext::set($this->tenantB);
        $ordersB = PurchaseOrder::all();
        $this->assertFalse($ordersB->contains('id', $poA->id));
        $this->assertNull(PurchaseOrder::find($poA->id));
    }
}
