<?php

namespace Tests\Feature\Filament;

use App\Core\Tenancy\TenantContext;
use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Resources\GoodsReceipts\Pages\CreateGoodsReceipt;
use App\Models\Branch;
use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\UserLocationScope;
use App\Models\Warehouse;
use App\Modules\Procurement\Contracts\ProcurementDocumentChainContract;
use App\Modules\Procurement\Exceptions\ReceivedQuantityExceedsOrderException;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GoodsReceiptResourceTest extends TestCase
{
    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Company $companyA;

    protected Company $companyB;

    protected Branch $branchA;

    protected Warehouse $warehouseA;

    protected Supplier $supplierA;

    protected TaxCode $taxCodeA;

    protected Product $productA;

    protected ProductVariant $variantA;

    protected User $warehouseMgr;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Setup Tenant A
        $this->tenantA = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Acme Receiving Tenant A',
            'subdomain' => 'grn-a-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenantA);
        Filament::setTenant($this->tenantA);
        setPermissionsTeamId($this->tenantA->id);

        $this->companyA = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Acme Depot Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branchA = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'name' => 'Central Intake Branch',
            'code' => 'CIB-01',
            'is_active' => true,
        ]);

        $this->warehouseA = Warehouse::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Intake Main Warehouse',
            'code' => 'IM-WH-01',
            'is_active' => true,
        ]);

        $this->supplierA = Supplier::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'name' => 'Meghna Grains Ltd',
            'is_active' => true,
        ]);

        $this->taxCodeA = TaxCode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'code' => 'TAX-EX-GRN',
            'name_en' => 'Exempt GRN Code',
            'description' => 'Exempt code',
            'is_active' => true,
        ]);

        $this->productA = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'code' => 'PRD-FLOUR',
            'name_en' => 'Wheat Flour 50kg',
            'base_uom' => 'BAG',
        ]);

        $this->variantA = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'product_id' => $this->productA->id,
            'tax_code_id' => $this->taxCodeA->id,
            'sku' => 'SKU-FLOUR-50KG',
            'name_en' => 'Grade 1 Flour Bag',
            'cost_price' => '2200.0000',
        ]);

        Role::firstOrCreate([
            'name' => 'Warehouse Manager',
            'guard_name' => 'web',
            'tenant_id' => $this->tenantA->id,
        ]);

        $this->warehouseMgr = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Depot Receiver',
            'email' => 'receiver-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);
        $this->warehouseMgr->assignRole('Warehouse Manager');

        $membershipA = TenantMembership::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->warehouseMgr->id,
            'is_active' => true,
        ]);

        UserLocationScope::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'membership_id' => $membershipA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
        ]);

        // 2. Setup Tenant B
        $this->tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Competitor Tenant B',
            'subdomain' => 'grn-b-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenantB);
        $this->companyB = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'legal_name' => 'Competitor Logistics',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        TenantContext::set($this->tenantA);
        Filament::setTenant($this->tenantA);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_goods_receipt_resource_binds_correct_model_and_navigation(): void
    {
        $this->assertEquals(GoodsReceipt::class, GoodsReceiptResource::getModel());
        $this->assertEquals('Procurement', GoodsReceiptResource::getNavigationGroup());
    }

    public function test_goods_receipt_creation_in_draft_does_not_alter_stock(): void
    {
        TenantContext::set($this->tenantA);
        $this->actingAs($this->warehouseMgr);

        $initialMovements = StockMovement::count();
        $initialBalances = StockBalance::count();

        // Create draft GRN via Livewire
        Livewire::test(CreateGoodsReceipt::class)
            ->fillForm([
                'company_id' => $this->companyA->id,
                'branch_id' => $this->branchA->id,
                'warehouse_id' => $this->warehouseA->id,
                'supplier_id' => $this->supplierA->id,
                'receipt_date' => Carbon::today()->toDateString(),
                'challan_number' => 'CHALLAN-MGI-9988',
                'lines' => [
                    [
                        'product_id' => $this->productA->id,
                        'variant_id' => $this->variantA->id,
                        'received_quantity' => '100.0000',
                        'unit_cost' => '2200.0000',
                        'batch_number' => 'LOT-2026-FLOUR-01',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $grn = GoodsReceipt::where('company_id', $this->companyA->id)->latest()->first();
        $this->assertNotNull($grn);
        $this->assertStringStartsWith('GRN-', $grn->grn_number);
        $this->assertEquals('draft', $grn->status);
        $this->assertEquals('CHALLAN-MGI-9988', $grn->challan_number);

        // Draft GRN must NOT alter physical stock
        $this->assertEquals($initialMovements, StockMovement::count());
        $this->assertEquals($initialBalances, StockBalance::count());
    }

    public function test_confirming_goods_receipt_posts_stock_movement_and_updates_po(): void
    {
        TenantContext::set($this->tenantA);
        $this->actingAs($this->warehouseMgr);

        /** @var ProcurementDocumentChainContract $service */
        $service = app(ProcurementDocumentChainContract::class);

        // 1. Create a confirmed Purchase Order for 100 units
        $po = PurchaseOrder::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'supplier_id' => $this->supplierA->id,
            'order_number' => 'PO-RECEIVE-TEST',
            'order_date' => Carbon::today()->toDateString(),
            'subtotal' => '220000.0000',
            'tax_amount' => '0.0000',
            'grand_total' => '220000.0000',
            'status' => 'confirmed',
            'created_by' => $this->warehouseMgr->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'purchase_order_id' => $po->id,
            'product_id' => $this->productA->id,
            'variant_id' => $this->variantA->id,
            'ordered_quantity' => '100.0000',
            'received_quantity' => '0.0000',
            'billed_quantity' => '0.0000',
            'unit_price' => '2200.0000',
            'subtotal' => '220000.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '220000.0000',
        ]);

        // 2. Create partial draft Goods Receipt for 60 units
        $grn = GoodsReceipt::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'warehouse_id' => $this->warehouseA->id,
            'supplier_id' => $this->supplierA->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-TEST-CONF-01',
            'receipt_date' => Carbon::today()->toDateString(),
            'challan_number' => 'CH-PARTIAL-60',
            'status' => 'draft',
            'created_by' => $this->warehouseMgr->id,
        ]);

        $grnLine = $grn->lines()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'goods_receipt_id' => $grn->id,
            'purchase_order_line_id' => $poLine->id,
            'product_id' => $this->productA->id,
            'variant_id' => $this->variantA->id,
            'received_quantity' => '60.0000',
            'billed_quantity' => '0.0000',
            'unit_cost' => '2200.0000',
            'batch_number' => 'LOT-FLOUR-60',
        ]);

        // 3. Confirm GRN
        $service->confirmGoodsReceipt($grn->id);
        $grn->refresh();
        $this->assertEquals('received', $grn->status);

        // 4. Verify physical inventory updated
        $movement = StockMovement::where('reference_type', 'goods_receipts')
            ->where('reference_id', $grn->id)
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals('GOODS_RECEIPT', $movement->movement_type);
        $this->assertEquals('60.0000', $movement->quantity);

        $balance = StockBalance::where('warehouse_id', $this->warehouseA->id)
            ->where('product_variant_id', $this->variantA->id)
            ->first();
        $this->assertNotNull($balance);
        $this->assertEquals('60.0000', $balance->quantity_on_hand);

        // 5. Verify parent PO updated to partially_received
        $po->refresh();
        $poLine->refresh();
        $this->assertEquals('partially_received', $po->status);
        $this->assertEquals('60.0000', $poLine->received_quantity);
    }

    public function test_over_delivery_beyond_ordered_quantity_throws_exception(): void
    {
        TenantContext::set($this->tenantA);

        /** @var ProcurementDocumentChainContract $service */
        $service = app(ProcurementDocumentChainContract::class);

        $po = PurchaseOrder::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'supplier_id' => $this->supplierA->id,
            'order_number' => 'PO-OVER-DELIVERY',
            'order_date' => Carbon::today()->toDateString(),
            'subtotal' => '22000.0000',
            'tax_amount' => '0.0000',
            'grand_total' => '22000.0000',
            'status' => 'confirmed',
            'created_by' => $this->warehouseMgr->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'purchase_order_id' => $po->id,
            'product_id' => $this->productA->id,
            'variant_id' => $this->variantA->id,
            'ordered_quantity' => '10.0000',
            'received_quantity' => '8.0000', // Only 2 units remaining
            'billed_quantity' => '0.0000',
            'unit_price' => '2200.0000',
            'subtotal' => '22000.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '22000.0000',
        ]);

        $grn = GoodsReceipt::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'warehouse_id' => $this->warehouseA->id,
            'supplier_id' => $this->supplierA->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-OVER-FAIL',
            'receipt_date' => Carbon::today()->toDateString(),
            'challan_number' => 'CH-EXCEED-5',
            'status' => 'draft',
            'created_by' => $this->warehouseMgr->id,
        ]);

        // Attempt to receive 5 units when only 2 remain
        $grn->lines()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'goods_receipt_id' => $grn->id,
            'purchase_order_line_id' => $poLine->id,
            'product_id' => $this->productA->id,
            'variant_id' => $this->variantA->id,
            'received_quantity' => '5.0000',
            'billed_quantity' => '0.0000',
            'unit_cost' => '2200.0000',
        ]);

        $this->expectException(ReceivedQuantityExceedsOrderException::class);
        $service->confirmGoodsReceipt($grn->id);
    }

    public function test_tenant_isolation_on_goods_receipts(): void
    {
        TenantContext::set($this->tenantA);
        $grnA = GoodsReceipt::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'warehouse_id' => $this->warehouseA->id,
            'supplier_id' => $this->supplierA->id,
            'grn_number' => 'GRN-ISO-A',
            'receipt_date' => Carbon::today()->toDateString(),
            'challan_number' => 'CH-ISO-A',
            'status' => 'draft',
            'created_by' => $this->warehouseMgr->id,
        ]);

        TenantContext::set($this->tenantB);
        $receiptsB = GoodsReceipt::all();
        $this->assertFalse($receiptsB->contains('id', $grnA->id));
        $this->assertNull(GoodsReceipt::find($grnA->id));
    }
}
