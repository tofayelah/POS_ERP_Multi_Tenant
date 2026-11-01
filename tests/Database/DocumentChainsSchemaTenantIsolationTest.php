<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\DeliveryChallan;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierBillLine;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentChainsSchemaTenantIsolationTest extends TestCase
{
    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Company $companyA;

    protected Company $companyB;

    protected Branch $branchA;

    protected Branch $branchB;

    protected Warehouse $warehouseA;

    protected Warehouse $warehouseB;

    protected User $userA;

    protected User $userB;

    protected Customer $customerA;

    protected Customer $customerB;

    protected Supplier $supplierA;

    protected Supplier $supplierB;

    protected Product $productA;

    protected ProductVariant $variantA;

    protected Product $productB;

    protected ProductVariant $variantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Chain Tenant A',
            'subdomain' => 'chain-a-'.Str::random(6),
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Chain Tenant B',
            'subdomain' => 'chain-b-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenantA);

        $this->companyA = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Chain Company A',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branchA = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'name' => 'Gulshan Branch A',
            'code' => 'GUL-01',
        ]);

        $this->warehouseA = Warehouse::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Central Warehouse A',
            'code' => 'WH-A1',
            'is_active' => true,
        ]);

        $this->userA = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sales Officer A',
            'email' => 'sales-a-'.Str::random(6).'@example.com',
            'password' => 'password',
        ]);

        $this->customerA = Customer::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'name' => 'Retail Buyer A',
            'contact_number' => '01711'.rand(100000, 999999),
            'credit_limit' => '50000.0000',
            'current_balance' => '0.0000',
        ]);

        $this->supplierA = Supplier::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'name' => 'Primary Vendor A',
            'email' => 'vendor-a-'.Str::random(6).'@example.com',
            'currency' => 'BDT',
            'created_by' => $this->userA->id,
        ]);

        $this->productA = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'code' => 'PROD-A-01',
            'name_en' => 'Steel Wire A',
            'base_uom' => 'KG',
        ]);

        $this->variantA = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'product_id' => $this->productA->id,
            'sku' => 'STL-WIRE-1MM',
            'variant_name' => '1mm Gauge',
        ]);

        // Tenant B setup
        TenantContext::set($this->tenantB);

        $this->companyB = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'legal_name' => 'Chain Company B',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branchB = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'company_id' => $this->companyB->id,
            'name' => 'Chittagong Branch B',
            'code' => 'CTG-01',
        ]);

        $this->warehouseB = Warehouse::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'company_id' => $this->companyB->id,
            'branch_id' => $this->branchB->id,
            'name' => 'Port Warehouse B',
            'code' => 'WH-B1',
            'is_active' => true,
        ]);

        $this->userB = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Procurement Officer B',
            'email' => 'proc-b-'.Str::random(6).'@example.com',
            'password' => 'password',
        ]);

        $this->customerB = Customer::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'company_id' => $this->companyB->id,
            'name' => 'Industrial Buyer B',
            'contact_number' => '01811'.rand(100000, 999999),
            'credit_limit' => '100000.0000',
            'current_balance' => '0.0000',
        ]);

        $this->supplierB = Supplier::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'company_id' => $this->companyB->id,
            'name' => 'Import Partner B',
            'email' => 'import-b-'.Str::random(6).'@example.com',
            'currency' => 'BDT',
            'created_by' => $this->userB->id,
        ]);

        $this->productB = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'company_id' => $this->companyB->id,
            'code' => 'PROD-B-01',
            'name_en' => 'Copper Cable B',
            'base_uom' => 'MTR',
        ]);

        $this->variantB = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'company_id' => $this->companyB->id,
            'product_id' => $this->productB->id,
            'sku' => 'CPR-CBL-4MM',
            'variant_name' => '4mm Core',
        ]);

        // Default back to Tenant A
        TenantContext::set($this->tenantA);
    }

    /**
     * Asserts tenant isolation on quotations, sales orders, delivery challans,
     * purchase orders, and goods receipts under RLS (NEW-34).
     */
    public function test_tenant_isolation_on_document_chain_tables(): void
    {
        TenantContext::set($this->tenantA);

        $quotationA = Quotation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'customer_id' => $this->customerA->id,
            'quotation_number' => 'QT-2026-0001',
            'quotation_date' => '2026-11-01',
            'valid_until' => '2026-11-15',
            'subtotal' => '1000.0000',
            'tax_amount' => '150.0000',
            'grand_total' => '1150.0000',
            'status' => 'draft',
            'created_by' => $this->userA->id,
        ]);

        $salesOrderA = SalesOrder::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'customer_id' => $this->customerA->id,
            'quotation_id' => $quotationA->id,
            'order_number' => 'SO-2026-0001',
            'order_date' => '2026-11-01',
            'subtotal' => '1000.0000',
            'tax_amount' => '150.0000',
            'grand_total' => '1150.0000',
            'status' => 'confirmed',
            'created_by' => $this->userA->id,
        ]);

        $challanA = DeliveryChallan::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'warehouse_id' => $this->warehouseA->id,
            'customer_id' => $this->customerA->id,
            'sales_order_id' => $salesOrderA->id,
            'challan_number' => 'DC-2026-0001',
            'challan_date' => '2026-11-01',
            'status' => 'draft',
            'created_by' => $this->userA->id,
        ]);

        $purchaseOrderA = PurchaseOrder::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'supplier_id' => $this->supplierA->id,
            'order_number' => 'PO-2026-0001',
            'order_date' => '2026-11-01',
            'subtotal' => '5000.0000',
            'tax_amount' => '750.0000',
            'grand_total' => '5750.0000',
            'status' => 'confirmed',
            'created_by' => $this->userA->id,
        ]);

        $goodsReceiptA = GoodsReceipt::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'warehouse_id' => $this->warehouseA->id,
            'supplier_id' => $this->supplierA->id,
            'purchase_order_id' => $purchaseOrderA->id,
            'grn_number' => 'GRN-2026-0001',
            'receipt_date' => '2026-11-01',
            'status' => 'draft',
            'created_by' => $this->userA->id,
        ]);

        // When switching context to Tenant B, Tenant A's documents must NOT be visible
        TenantContext::set($this->tenantB);

        $this->assertNull(Quotation::find($quotationA->id));
        $this->assertNull(SalesOrder::find($salesOrderA->id));
        $this->assertNull(DeliveryChallan::find($challanA->id));
        $this->assertNull(PurchaseOrder::find($purchaseOrderA->id));
        $this->assertNull(GoodsReceipt::find($goodsReceiptA->id));

        $this->assertEquals(0, Quotation::where('id', $quotationA->id)->count());
        $this->assertEquals(0, SalesOrder::where('id', $salesOrderA->id)->count());
        $this->assertEquals(0, DeliveryChallan::where('id', $challanA->id)->count());
        $this->assertEquals(0, PurchaseOrder::where('id', $purchaseOrderA->id)->count());
        $this->assertEquals(0, GoodsReceipt::where('id', $goodsReceiptA->id)->count());
    }

    /**
     * Asserts that cross-company references are strictly rejected by PostgreSQL composite FKs (NEW-34).
     */
    public function test_composite_foreign_keys_reject_cross_company_references(): void
    {
        TenantContext::set($this->tenantA);

        // 1. Sales Order cannot link to Customer from Company B
        $this->expectException(QueryException::class);
        SalesOrder::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'customer_id' => $this->customerB->id, // Cross-company!
            'order_number' => 'SO-X-001',
            'order_date' => '2026-11-01',
            'subtotal' => '1000.0000',
            'tax_amount' => '0.0000',
            'grand_total' => '1000.0000',
            'status' => 'draft',
            'created_by' => $this->userA->id,
        ]);
    }

    /**
     * Asserts that Delivery Challan cannot reference Warehouse from Company B (NEW-34).
     */
    public function test_delivery_challan_rejects_cross_company_warehouse(): void
    {
        TenantContext::set($this->tenantA);

        $this->expectException(QueryException::class);
        DeliveryChallan::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'warehouse_id' => $this->warehouseB->id, // Cross-company!
            'customer_id' => $this->customerA->id,
            'challan_number' => 'DC-X-001',
            'challan_date' => '2026-11-01',
            'status' => 'draft',
            'created_by' => $this->userA->id,
        ]);
    }

    /**
     * Asserts that Purchase Order cannot reference Supplier from Company B (NEW-34).
     */
    public function test_purchase_order_rejects_cross_company_supplier(): void
    {
        TenantContext::set($this->tenantA);

        $this->expectException(QueryException::class);
        PurchaseOrder::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'supplier_id' => $this->supplierB->id, // Cross-company!
            'order_number' => 'PO-X-001',
            'order_date' => '2026-11-01',
            'subtotal' => '1000.0000',
            'tax_amount' => '0.0000',
            'grand_total' => '1000.0000',
            'status' => 'draft',
            'created_by' => $this->userA->id,
        ]);
    }

    /**
     * Asserts that confirmed Delivery Challans cannot be deleted or have their warehouse mutated (NEW-34).
     */
    public function test_confirmed_delivery_challan_immutability(): void
    {
        TenantContext::set($this->tenantA);

        $challan = DeliveryChallan::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'warehouse_id' => $this->warehouseA->id,
            'customer_id' => $this->customerA->id,
            'challan_number' => 'DC-IMM-001',
            'challan_date' => '2026-11-01',
            'status' => 'confirmed',
            'created_by' => $this->userA->id,
        ]);

        // Attempting to delete confirmed challan must fail via trigger
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Immutability Violation/i');
        $challan->delete();
    }

    /**
     * Asserts that received Goods Receipts cannot be deleted (NEW-34).
     */
    public function test_received_goods_receipt_immutability(): void
    {
        TenantContext::set($this->tenantA);

        $grn = GoodsReceipt::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'warehouse_id' => $this->warehouseA->id,
            'supplier_id' => $this->supplierA->id,
            'grn_number' => 'GRN-IMM-001',
            'receipt_date' => '2026-11-01',
            'status' => 'received',
            'created_by' => $this->userA->id,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Immutability Violation/i');
        $grn->delete();
    }
}
