<?php

namespace Tests\Feature\Filament;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserLocationScope;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProcurementPolicyTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branchA;

    protected Branch $branchB;

    protected Warehouse $warehouseA;

    protected Warehouse $warehouseB;

    protected Supplier $supplier;

    protected Customer $customer;

    protected TaxCode $taxCode;

    protected Product $product;

    protected ProductVariant $variant;

    protected User $procurementMgr;

    protected User $warehouseMgrA;

    protected User $salesMgr;

    protected User $cashierA;

    protected User $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create Tenant & bind context
        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Procurement Policy Tenant',
            'subdomain' => 'pol-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        // 2. Create Company
        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Policy Test Corp Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        // 3. Create Branches
        $this->branchA = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Dhanmondi Branch',
            'code' => 'BR-DHN',
            'is_active' => true,
        ]);

        $this->branchB = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Gulshan Branch',
            'code' => 'BR-GUL',
            'is_active' => true,
        ]);

        // 4. Create Warehouses
        $this->warehouseA = Warehouse::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Dhanmondi Depot',
            'code' => 'WH-DHN',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branchB->id,
            'name' => 'Gulshan Depot',
            'code' => 'WH-GUL',
            'is_active' => true,
        ]);

        // 5. Create Supplier, Customer, TaxCode, Product & Variant
        $this->supplier = Supplier::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Apex Raw Materials Ltd',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Apex Wholesale Buyer Ltd',
            'is_active' => true,
        ]);

        $this->taxCode = TaxCode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'TAX-EXEMPT',
            'name_en' => 'Exempt Item',
            'description' => 'Exempt item',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PRD-STEEL',
            'name_en' => 'Reinforced Steel',
            'base_uom' => 'KG',
        ]);

        $this->variant = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'tax_code_id' => $this->taxCode->id,
            'sku' => 'SKU-STEEL-10MM',
            'name_en' => '10mm Steel Bar',
            'cost_price' => '120.0000',
        ]);

        // 6. Setup Roles
        $roles = [
            'Procurement Manager',
            'Warehouse Manager',
            'Sales Manager',
            'Cashier',
            'Auditor',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
                'tenant_id' => $this->tenant->id,
            ]);
        }

        // 7. Setup Users & Scopes
        $this->procurementMgr = $this->createUserWithRole('Procurement Manager', [
            ['company_id' => $this->company->id, 'branch_id' => null], // Company-wide
        ]);

        $this->warehouseMgrA = $this->createUserWithRole('Warehouse Manager', [
            ['company_id' => $this->company->id, 'branch_id' => $this->branchA->id], // Branch A only
        ]);

        $this->salesMgr = $this->createUserWithRole('Sales Manager', [
            ['company_id' => $this->company->id, 'branch_id' => null], // Company-wide
        ]);

        $this->cashierA = $this->createUserWithRole('Cashier', [
            ['company_id' => $this->company->id, 'branch_id' => $this->branchA->id],
        ]);

        $this->auditor = $this->createUserWithRole('Auditor', [
            ['company_id' => $this->company->id, 'branch_id' => null],
        ]);
    }

    /**
     * @param  array<int, array{company_id: string, branch_id: ?string}>  $scopes
     */
    protected function createUserWithRole(string $role, array $scopes = []): User
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => $role.' User',
            'email' => Str::slug($role).'-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);

        $user->assignRole($role);

        foreach ($scopes as $scope) {
            UserLocationScope::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'tenant_id' => $this->tenant->id,
                'company_id' => $scope['company_id'],
                'branch_id' => $scope['branch_id'],
            ]);
        }

        return $user;
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_purchase_order_policy_rbac_and_status_guards(): void
    {
        TenantContext::set($this->tenant);

        // 1. viewAny assertions
        $this->assertTrue(Gate::forUser($this->procurementMgr)->allows('viewAny', PurchaseOrder::class));
        $this->assertTrue(Gate::forUser($this->auditor)->allows('viewAny', PurchaseOrder::class));
        $this->assertFalse(Gate::forUser($this->cashierA)->allows('viewAny', PurchaseOrder::class));
        $this->assertFalse(Gate::forUser($this->warehouseMgrA)->allows('viewAny', PurchaseOrder::class));

        // 2. create assertions
        $this->assertTrue(Gate::forUser($this->procurementMgr)->allows('create', PurchaseOrder::class));
        $this->assertFalse(Gate::forUser($this->auditor)->allows('create', PurchaseOrder::class));
        $this->assertFalse(Gate::forUser($this->cashierA)->allows('create', PurchaseOrder::class));

        // 3. Draft PO assertions
        $draftPo = PurchaseOrder::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
            'supplier_id' => $this->supplier->id,
            'order_number' => 'PO-TEST-0001',
            'order_date' => Carbon::today()->toDateString(),
            'subtotal' => '1200.0000',
            'tax_amount' => '0.0000',
            'grand_total' => '1200.0000',
            'status' => 'draft',
            'created_by' => $this->procurementMgr->id,
        ]);

        $this->assertTrue(Gate::forUser($this->procurementMgr)->allows('update', $draftPo));
        $this->assertTrue(Gate::forUser($this->procurementMgr)->allows('confirm', $draftPo));
        $this->assertTrue(Gate::forUser($this->procurementMgr)->allows('cancel', $draftPo));
        $this->assertTrue(Gate::forUser($this->procurementMgr)->allows('delete', $draftPo));

        // 4. Confirmed PO assertions (Immutability check)
        $draftPo->status = 'confirmed';
        $draftPo->save();

        $this->assertFalse(Gate::forUser($this->procurementMgr)->allows('update', $draftPo));
        $this->assertFalse(Gate::forUser($this->procurementMgr)->allows('confirm', $draftPo));
        $this->assertFalse(Gate::forUser($this->procurementMgr)->allows('delete', $draftPo));
        $this->assertTrue(Gate::forUser($this->procurementMgr)->allows('cancel', $draftPo)); // 0 receipts -> can cancel

        // 5. Partially received PO assertions (Cannot cancel once goods arrive)
        $line = PurchaseOrderLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'purchase_order_id' => $draftPo->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'ordered_quantity' => '10.0000',
            'received_quantity' => '5.0000',
            'billed_quantity' => '0.0000',
            'unit_price' => '120.0000',
            'subtotal' => '1200.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '1200.0000',
        ]);

        $draftPo->status = 'partially_received';
        $draftPo->save();

        $this->assertFalse(Gate::forUser($this->procurementMgr)->allows('cancel', $draftPo));
        $this->assertFalse(Gate::forUser($this->procurementMgr)->allows('deleteAny', PurchaseOrder::class));
    }

    public function test_goods_receipt_policy_rbac_and_location_guards(): void
    {
        TenantContext::set($this->tenant);

        // 1. viewAny assertions
        $this->assertTrue(Gate::forUser($this->warehouseMgrA)->allows('viewAny', GoodsReceipt::class));
        $this->assertTrue(Gate::forUser($this->procurementMgr)->allows('viewAny', GoodsReceipt::class));
        $this->assertFalse(Gate::forUser($this->cashierA)->allows('viewAny', GoodsReceipt::class));
        $this->assertFalse(Gate::forUser($this->salesMgr)->allows('viewAny', GoodsReceipt::class));

        // 2. create assertions
        $this->assertTrue(Gate::forUser($this->warehouseMgrA)->allows('create', GoodsReceipt::class));
        $this->assertFalse(Gate::forUser($this->cashierA)->allows('create', GoodsReceipt::class));
        $this->assertFalse(Gate::forUser($this->procurementMgr)->allows('create', GoodsReceipt::class));

        // 3. Draft GRN in Branch A
        $grnA = GoodsReceipt::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
            'warehouse_id' => $this->warehouseA->id,
            'supplier_id' => $this->supplier->id,
            'grn_number' => 'GRN-TEST-0001',
            'receipt_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->warehouseMgrA->id,
        ]);

        $this->assertTrue(Gate::forUser($this->warehouseMgrA)->allows('view', $grnA));
        $this->assertTrue(Gate::forUser($this->warehouseMgrA)->allows('update', $grnA));
        $this->assertTrue(Gate::forUser($this->warehouseMgrA)->allows('confirm', $grnA));

        // 4. Draft GRN in Branch B (Location Scoping Assertion)
        $grnB = GoodsReceipt::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branchB->id,
            'warehouse_id' => $this->warehouseB->id,
            'supplier_id' => $this->supplier->id,
            'grn_number' => 'GRN-TEST-0002',
            'receipt_date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->procurementMgr->id,
        ]);

        // Warehouse Manager A (scoped only to Branch A) MUST be denied viewing/confirming Branch B's intake
        $this->assertFalse(Gate::forUser($this->warehouseMgrA)->allows('view', $grnB));
        $this->assertFalse(Gate::forUser($this->warehouseMgrA)->allows('update', $grnB));
        $this->assertFalse(Gate::forUser($this->warehouseMgrA)->allows('confirm', $grnB));

        // 5. Confirmed GRN immutability
        $grnA->status = 'received';
        $grnA->save();

        $this->assertFalse(Gate::forUser($this->warehouseMgrA)->allows('update', $grnA));
        $this->assertFalse(Gate::forUser($this->warehouseMgrA)->allows('confirm', $grnA));
        $this->assertFalse(Gate::forUser($this->warehouseMgrA)->allows('delete', $grnA));
    }

    public function test_quotation_policy_rbac_and_validity_guards(): void
    {
        TenantContext::set($this->tenant);

        // 1. viewAny assertions
        $this->assertTrue(Gate::forUser($this->salesMgr)->allows('viewAny', Quotation::class));
        $this->assertTrue(Gate::forUser($this->auditor)->allows('viewAny', Quotation::class));
        $this->assertFalse(Gate::forUser($this->warehouseMgrA)->allows('viewAny', Quotation::class));
        $this->assertFalse(Gate::forUser($this->cashierA)->allows('viewAny', Quotation::class));

        // 2. create assertions
        $this->assertTrue(Gate::forUser($this->salesMgr)->allows('create', Quotation::class));
        $this->assertFalse(Gate::forUser($this->warehouseMgrA)->allows('create', Quotation::class));

        // 3. Draft Quotation (Active validity)
        $quote = Quotation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
            'customer_id' => $this->customer->id,
            'quotation_number' => 'QT-TEST-0001',
            'quotation_date' => Carbon::today()->toDateString(),
            'valid_until' => Carbon::today()->addDays(10)->toDateString(),
            'subtotal' => '5000.0000',
            'tax_amount' => '0.0000',
            'grand_total' => '5000.0000',
            'status' => 'draft',
            'created_by' => $this->salesMgr->id,
        ]);

        $this->assertTrue(Gate::forUser($this->salesMgr)->allows('view', $quote));
        $this->assertTrue(Gate::forUser($this->salesMgr)->allows('update', $quote));
        $this->assertTrue(Gate::forUser($this->salesMgr)->allows('convert', $quote));
        $this->assertTrue(Gate::forUser($this->salesMgr)->allows('cancel', $quote));

        // 4. Expired quotation cannot be converted
        $quote->valid_until = Carbon::yesterday();
        $quote->save();

        $this->assertFalse(Gate::forUser($this->salesMgr)->allows('convert', $quote));

        // 5. Converted quotation is immutable
        $quote->valid_until = Carbon::today()->addDays(10);
        $quote->status = 'converted';
        $quote->save();

        $this->assertFalse(Gate::forUser($this->salesMgr)->allows('update', $quote));
        $this->assertFalse(Gate::forUser($this->salesMgr)->allows('convert', $quote));
        $this->assertFalse(Gate::forUser($this->salesMgr)->allows('cancel', $quote));
        $this->assertFalse(Gate::forUser($this->salesMgr)->allows('delete', $quote));
    }
}
