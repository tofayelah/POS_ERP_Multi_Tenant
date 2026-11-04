<?php

namespace Tests\Feature\Filament;

use App\Core\Tenancy\TenantContext;
use App\Filament\Resources\SupplierBills\Pages\ViewSupplierBill;
use App\Filament\Widgets\ThreeWayMatchingWidget;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierBillLine;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\UserLocationScope;
use App\Models\Warehouse;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ThreeWayMatchingWidgetTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected Warehouse $warehouse;

    protected Supplier $supplier;

    protected TaxCode $taxCode;

    protected Product $product;

    protected ProductVariant $variant;

    protected User $apOfficer;

    protected User $financialController;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Setup Tenant
        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Acme AP Matching Tenant',
            'subdomain' => 'match-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Acme Trading Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Main Commercial Hub',
            'code' => 'MCH-01',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Central Store',
            'code' => 'CS-01',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Delta Grain Mills Ltd',
            'is_active' => true,
        ]);

        $this->taxCode = TaxCode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'TAX-EX-M',
            'name_en' => 'Exempt Match Code',
            'description' => 'Exempt code',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PRD-GRAIN',
            'name_en' => 'Wheat Grain',
            'base_uom' => 'KG',
        ]);

        $this->variant = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'tax_code_id' => $this->taxCode->id,
            'sku' => 'SKU-WHEAT-50',
            'name_en' => '50kg Wheat Bag',
            'cost_price' => '150.0000',
        ]);

        // Roles
        Role::firstOrCreate(['name' => 'Accounts Payable Officer', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'Financial Controller', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);

        // AP Officer User
        $this->apOfficer = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'AP Clerk',
            'email' => 'ap-clerk-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);
        $this->apOfficer->assignRole('Accounts Payable Officer');

        $membershipClerk = TenantMembership::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->apOfficer->id,
            'is_active' => true,
        ]);

        UserLocationScope::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'membership_id' => $membershipClerk->id,
            'company_id' => $this->company->id,
            'branch_id' => null,
        ]);

        // Financial Controller User
        $this->financialController = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Head of Finance',
            'email' => 'controller-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);
        $this->financialController->assignRole('Financial Controller');

        $membershipCtrl = TenantMembership::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->financialController->id,
            'is_active' => true,
        ]);

        UserLocationScope::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'membership_id' => $membershipCtrl->id,
            'company_id' => $this->company->id,
            'branch_id' => null,
        ]);
    }

    public function test_exact_three_way_match_verified_and_approved(): void
    {
        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        // 1. PO: 100 units @ 150.0000 = 15,000.0000
        $po = PurchaseOrder::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'order_number' => 'PO-MATCH-01',
            'order_date' => Carbon::today()->toDateString(),
            'subtotal' => '15000.0000',
            'tax_amount' => '0.0000',
            'grand_total' => '15000.0000',
            'status' => 'confirmed',
            'created_by' => $this->apOfficer->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'ordered_quantity' => '100.0000',
            'received_quantity' => '100.0000',
            'billed_quantity' => '0.0000',
            'unit_price' => '150.0000',
            'subtotal' => '15000.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '15000.0000',
        ]);

        // 2. GRN: Received 100 units
        $grn = GoodsReceipt::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-MATCH-01',
            'receipt_date' => Carbon::today()->toDateString(),
            'challan_number' => 'CH-MATCH-01',
            'status' => 'received',
            'created_by' => $this->apOfficer->id,
        ]);

        $grnLine = GoodsReceiptLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'goods_receipt_id' => $grn->id,
            'purchase_order_line_id' => $poLine->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'received_quantity' => '100.0000',
            'billed_quantity' => '0.0000',
            'unit_cost' => '150.0000',
        ]);

        // 3. Bill: 100 units @ 150.0000
        $bill = SupplierBill::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $po->id,
            'goods_receipt_id' => $grn->id,
            'bill_number' => 'BILL-MATCH-01',
            'bill_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '15000.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '15000.0000',
            'status' => 'draft',
            'created_by' => $this->apOfficer->id,
        ]);

        SupplierBillLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'supplier_bill_id' => $bill->id,
            'goods_receipt_line_id' => $grnLine->id,
            'purchase_order_line_id' => $poLine->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'quantity' => '100.0000',
            'unit_price' => '150.0000',
            'subtotal' => '15000.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '15000.0000',
        ]);

        // 4. Test Widget Calculation
        $widget = new ThreeWayMatchingWidget;
        $widget->record = $bill;
        $reconciliation = $widget->getReconciliationData();

        $this->assertEquals('MATCHED', $reconciliation['status']);
        $this->assertFalse($reconciliation['has_exceptions']);
        $this->assertEquals('15000.0000', $reconciliation['total_po_expected']);
        $this->assertEquals('15000.0000', $reconciliation['total_billed']);
        $this->assertEquals('0.0000', $reconciliation['net_ppv']);
        $this->assertCount(1, $reconciliation['lines']);
        $this->assertEquals('MATCHED', $reconciliation['lines'][0]['status']);

        // 5. Test Livewire Approval
        $this->actingAs($this->apOfficer);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->tenant);

        Livewire::test(ViewSupplierBill::class, ['record' => $bill->id])
            ->callAction('approve_bill')
            ->assertHasNoActionErrors();

        $bill->refresh();
        $this->assertEquals('approved', $bill->status);
    }

    public function test_quantity_overbilling_variance_detected_and_blocks_approval(): void
    {
        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        // PO: 100 units @ 150.0000
        $po = PurchaseOrder::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'order_number' => 'PO-OVER-01',
            'order_date' => Carbon::today()->toDateString(),
            'subtotal' => '15000.0000',
            'tax_amount' => '0.0000',
            'grand_total' => '15000.0000',
            'status' => 'confirmed',
            'created_by' => $this->apOfficer->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'ordered_quantity' => '100.0000',
            'received_quantity' => '80.0000',
            'billed_quantity' => '0.0000',
            'unit_price' => '150.0000',
            'subtotal' => '15000.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '15000.0000',
        ]);

        // GRN: Received only 80 units (20 units undelivered)
        $grn = GoodsReceipt::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-OVER-01',
            'receipt_date' => Carbon::today()->toDateString(),
            'challan_number' => 'CH-OVER-01',
            'status' => 'received',
            'created_by' => $this->apOfficer->id,
        ]);

        $grnLine = GoodsReceiptLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'goods_receipt_id' => $grn->id,
            'purchase_order_line_id' => $poLine->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'received_quantity' => '80.0000',
            'billed_quantity' => '0.0000',
            'unit_cost' => '150.0000',
        ]);

        // Bill: Vendor claims payment for 100 units!
        $bill = SupplierBill::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $po->id,
            'goods_receipt_id' => $grn->id,
            'bill_number' => 'BILL-OVER-01',
            'bill_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '15000.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '15000.0000',
            'status' => 'draft',
            'created_by' => $this->apOfficer->id,
        ]);

        SupplierBillLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'supplier_bill_id' => $bill->id,
            'goods_receipt_line_id' => $grnLine->id,
            'purchase_order_line_id' => $poLine->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'quantity' => '100.0000',
            'unit_price' => '150.0000',
            'subtotal' => '15000.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '15000.0000',
        ]);

        // Test Widget Output
        $widget = new ThreeWayMatchingWidget;
        $widget->record = $bill;
        $reconciliation = $widget->getReconciliationData();

        $this->assertEquals('EXCEPTION', $reconciliation['status']);
        $this->assertTrue($reconciliation['has_exceptions']);
        $this->assertNotEmpty($reconciliation['exceptions']);
        $this->assertStringContainsString('Over-billing', $reconciliation['exceptions'][0]);

        // Attempt approval by regular AP Clerk -> Blocked!
        $this->actingAs($this->apOfficer);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->tenant);

        Livewire::test(ViewSupplierBill::class, ['record' => $bill->id])
            ->callAction('approve_bill')
            ->assertNotified();

        $bill->refresh();
        $this->assertEquals('draft', $bill->status); // Remains draft!
    }

    public function test_purchase_price_variance_ppv_isolated_accurately(): void
    {
        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        // PO: 50 units @ 100.0000 agreed price
        $po = PurchaseOrder::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'order_number' => 'PO-PPV-01',
            'order_date' => Carbon::today()->toDateString(),
            'subtotal' => '5000.0000',
            'tax_amount' => '0.0000',
            'grand_total' => '5000.0000',
            'status' => 'confirmed',
            'created_by' => $this->apOfficer->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'ordered_quantity' => '50.0000',
            'received_quantity' => '50.0000',
            'billed_quantity' => '0.0000',
            'unit_price' => '100.0000',
            'subtotal' => '5000.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '5000.0000',
        ]);

        // GRN: 50 units received
        $grn = GoodsReceipt::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-PPV-01',
            'receipt_date' => Carbon::today()->toDateString(),
            'challan_number' => 'CH-PPV-01',
            'status' => 'received',
            'created_by' => $this->apOfficer->id,
        ]);

        $grnLine = GoodsReceiptLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'goods_receipt_id' => $grn->id,
            'purchase_order_line_id' => $poLine->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'received_quantity' => '50.0000',
            'billed_quantity' => '0.0000',
            'unit_cost' => '100.0000',
        ]);

        // Bill: Vendor invoiced at 120.0000 (PPV of +20.0000 per unit)
        // Total PPV = (120 - 100) * 50 = 1000.0000
        $bill = SupplierBill::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $po->id,
            'goods_receipt_id' => $grn->id,
            'bill_number' => 'BILL-PPV-01',
            'bill_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '6000.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '6000.0000',
            'status' => 'draft',
            'created_by' => $this->apOfficer->id,
        ]);

        SupplierBillLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'supplier_bill_id' => $bill->id,
            'goods_receipt_line_id' => $grnLine->id,
            'purchase_order_line_id' => $poLine->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'quantity' => '50.0000',
            'unit_price' => '120.0000',
            'subtotal' => '6000.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '6000.0000',
        ]);

        $widget = new ThreeWayMatchingWidget;
        $widget->record = $bill;
        $reconciliation = $widget->getReconciliationData();

        $this->assertEquals('EXCEPTION', $reconciliation['status']);
        $this->assertTrue($reconciliation['has_exceptions']);
        $this->assertEquals('5000.0000', $reconciliation['total_po_expected']);
        $this->assertEquals('6000.0000', $reconciliation['total_billed']);
        $this->assertEquals('1000.0000', $reconciliation['net_ppv']);
        $this->assertStringContainsString('Price Variance', $reconciliation['exceptions'][0]);
    }

    public function test_financial_controller_managerial_override_audit_trail(): void
    {
        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        // Create Bill with Price Exception
        $po = PurchaseOrder::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'order_number' => 'PO-OVR-01',
            'order_date' => Carbon::today()->toDateString(),
            'subtotal' => '2000.0000',
            'tax_amount' => '0.0000',
            'grand_total' => '2000.0000',
            'status' => 'confirmed',
            'created_by' => $this->apOfficer->id,
        ]);

        $poLine = PurchaseOrderLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'ordered_quantity' => '20.0000',
            'received_quantity' => '20.0000',
            'billed_quantity' => '0.0000',
            'unit_price' => '100.0000',
            'subtotal' => '2000.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '2000.0000',
        ]);

        $grn = GoodsReceipt::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-OVR-01',
            'receipt_date' => Carbon::today()->toDateString(),
            'challan_number' => 'CH-OVR-01',
            'status' => 'received',
            'created_by' => $this->apOfficer->id,
        ]);

        $grnLine = GoodsReceiptLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'goods_receipt_id' => $grn->id,
            'purchase_order_line_id' => $poLine->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'received_quantity' => '20.0000',
            'billed_quantity' => '0.0000',
            'unit_cost' => '100.0000',
        ]);

        $bill = SupplierBill::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $po->id,
            'goods_receipt_id' => $grn->id,
            'bill_number' => 'BILL-OVR-01',
            'bill_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '2400.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '2400.0000',
            'status' => 'draft',
            'created_by' => $this->apOfficer->id,
        ]);

        SupplierBillLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'supplier_bill_id' => $bill->id,
            'goods_receipt_line_id' => $grnLine->id,
            'purchase_order_line_id' => $poLine->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'quantity' => '20.0000',
            'unit_price' => '120.0000', // +20 price variance
            'subtotal' => '2400.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '2400.0000',
        ]);

        $initialAuditCount = AuditLog::count();

        // Financial Controller executes override approval
        $this->actingAs($this->financialController);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->tenant);

        Livewire::test(ViewSupplierBill::class, ['record' => $bill->id])
            ->callAction('approve_bill', [
                'override_reason' => 'Approved price hike due to emergency fuel surcharge notification.',
            ])
            ->assertHasNoActionErrors();

        $bill->refresh();
        $this->assertEquals('approved', $bill->status);
        $this->assertEquals($this->financialController->id, $bill->approved_by);

        // Assert AuditLog recorded
        $this->assertEquals($initialAuditCount + 1, AuditLog::count());
        $audit = AuditLog::where('record_id', $bill->id)->latest()->first();
        $this->assertNotNull($audit);
        $this->assertEquals('THREE_WAY_MATCH_OVERRIDE', $audit->action);
        $this->assertEquals($this->financialController->id, $audit->actor_id);
        $this->assertStringContainsString('emergency fuel surcharge', $audit->new_values['reason']);
        $this->assertNotEmpty($audit->payload_hash);
    }
}
