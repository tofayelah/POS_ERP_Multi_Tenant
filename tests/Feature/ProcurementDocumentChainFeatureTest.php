<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Accounting\Subledger\Contracts\SubledgerReconciliationContract;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Procurement\Contracts\ProcurementDocumentChainContract;
use App\Modules\Procurement\DTOs\CreateGoodsReceiptDTO;
use App\Modules\Procurement\DTOs\CreateGoodsReceiptLineDTO;
use App\Modules\Procurement\DTOs\CreatePurchaseOrderDTO;
use App\Modules\Procurement\DTOs\CreatePurchaseOrderLineDTO;
use App\Modules\Procurement\DTOs\CreateVendorBillFromReceiptDTO;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcurementDocumentChainFeatureTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected Warehouse $warehouse;

    protected Supplier $supplier;

    protected User $user;

    protected FiscalYear $fiscalYear;

    protected AccountingPeriod $period;

    protected ChartOfAccount $apAccount;

    protected ChartOfAccount $expenseAccount;

    protected Product $product;

    protected ProductVariant $variant;

    protected TaxCode $taxCode;

    protected ProcurementDocumentChainContract $procurementService;

    protected StockMovementServiceContract $stockService;

    protected SubledgerReconciliationContract $reconciliationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Procurement Chain Tenant',
            'subdomain' => 'proc-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Procurement Chain Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Factory Receiving Branch',
            'code' => 'FRB-01',
        ]);

        $this->warehouse = Warehouse::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Central Materials Warehouse',
            'code' => 'CM-WH-01',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'P2P Procurement Officer',
            'email' => 'p2p-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);

        $this->supplier = Supplier::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Dhaka Raw Materials Ltd',
            'email' => 'raw-'.Str::random(6).'@example.com',
            'currency' => 'BDT',
            'created_by' => $this->user->id,
        ]);

        $this->fiscalYear = FiscalYear::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'title' => 'FY 2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_closed' => false,
        ]);

        $this->period = AccountingPeriod::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'period_number' => Carbon::now()->month,
            'period_name' => Carbon::now()->format('F Y'),
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->endOfMonth()->toDateString(),
            'status' => 'open',
        ]);

        // General Ledger Accounts for Procurement
        $this->apAccount = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '2010',
            'account_name' => 'Accounts Payable (Creditors)',
            'account_type' => 'liability',
        ]);

        $this->expenseAccount = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '5010',
            'account_name' => 'Cost of Goods Sold / Purchase Expense',
            'account_type' => 'expense',
        ]);

        $this->taxCode = TaxCode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'EXEMPT-P',
            'name_en' => 'Exempt Purchase',
            'description' => 'Exempt purchase',
        ]);

        $this->product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'P-STEEL-INGOT',
            'name_en' => 'High Grade Steel Ingot',
            'base_uom' => 'KG',
        ]);

        $this->variant = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'tax_code_id' => $this->taxCode->id,
            'sku' => 'SKU-INGOT-HG',
            'name_en' => 'Grade A Ingot 50kg',
            'cost_price' => '500.0000',
        ]);

        // Services
        $this->procurementService = app(ProcurementDocumentChainContract::class);
        $this->stockService = app(StockMovementServiceContract::class);
        $this->reconciliationService = app(SubledgerReconciliationContract::class);
    }

    /**
     * Runs full Procure-to-Pay document chain with partial goods receipts,
     * verifying stock movements, GL posting, and subledger AP reconciliation (NEW-34).
     */
    public function test_procure_to_pay_document_chain_end_to_end_with_partial_receipt(): void
    {
        TenantContext::set($this->tenant);

        // 1. Step 1: Create Purchase Order (200 units @ ৳500 = ৳100,000) & Confirm
        $purchaseOrder = $this->procurementService->createPurchaseOrder(new CreatePurchaseOrderDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            supplierId: $this->supplier->id,
            orderDate: Carbon::today()->toDateString(),
            expectedDeliveryDate: Carbon::today()->addDays(10)->toDateString(),
            createdBy: $this->user->id,
            lines: [
                new CreatePurchaseOrderLineDTO(
                    productId: $this->product->id,
                    variantId: $this->variant->id,
                    orderedQuantity: '200.0000',
                    unitPrice: '500.0000',
                    taxAmount: '0.0000'
                ),
            ]
        ));

        $purchaseOrder = $this->procurementService->confirmPurchaseOrder($purchaseOrder->id);

        $this->assertStringStartsWith('PO-', $purchaseOrder->order_number);
        $this->assertEquals('confirmed', $purchaseOrder->status);
        $this->assertEquals('100000.0000', $purchaseOrder->grand_total);
        $this->assertEquals('200.0000', $purchaseOrder->lines->first()->ordered_quantity);
        $this->assertEquals('0.0000', $purchaseOrder->lines->first()->received_quantity);
        $this->assertEquals('0.0000', $purchaseOrder->lines->first()->billed_quantity);

        // 2. Step 2: Partial Goods Receipt 1 (GRN 1: 120 units)
        $grn1 = $this->procurementService->createGoodsReceipt(new CreateGoodsReceiptDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            warehouseId: $this->warehouse->id,
            supplierId: $this->supplier->id,
            purchaseOrderId: $purchaseOrder->id,
            receiptDate: Carbon::today()->toDateString(),
            challanNumber: 'SUP-CH-99881',
            createdBy: $this->user->id,
            lines: [
                new CreateGoodsReceiptLineDTO(
                    purchaseOrderLineId: $purchaseOrder->lines->first()->id,
                    productId: $this->product->id,
                    variantId: $this->variant->id,
                    receivedQuantity: '120.0000',
                    unitCost: '500.0000',
                    batchNumber: 'INGOT-LOT-01'
                ),
            ]
        ));

        $grn1 = $this->procurementService->confirmGoodsReceipt($grn1->id);

        $this->assertStringStartsWith('GRN-', $grn1->grn_number);
        $this->assertEquals('received', $grn1->status);

        // Verify stock movement ledger: 120 units incremented in warehouse
        $stockBalance = StockBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->where('batch_number', 'INGOT-LOT-01')
            ->first();
        $this->assertNotNull($stockBalance);
        $this->assertEquals('120.0000', $stockBalance->quantity_on_hand);

        // Verify Purchase Order updated to partially_received
        $purchaseOrder->refresh();
        $this->assertEquals('partially_received', $purchaseOrder->status);
        $this->assertEquals('120.0000', $purchaseOrder->lines->first()->received_quantity);
        $this->assertEquals('0.0000', $purchaseOrder->lines->first()->billed_quantity);

        // 3. Step 3: Issue Vendor Bill for GRN 1 (120 units = ৳60,000)
        $bill1 = $this->procurementService->createVendorBillFromReceipt(new CreateVendorBillFromReceiptDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            goodsReceiptId: $grn1->id,
            billDate: Carbon::today()->toDateString(),
            dueDate: Carbon::today()->addDays(30)->toDateString(),
            createdBy: $this->user->id,
            periodId: $this->period->id
        ));

        $this->assertStringStartsWith('BILL-', $bill1->bill_number);
        $this->assertEquals('60000.0000', $bill1->total_amount);

        // Verify Purchase Order billed_quantity
        $purchaseOrder->refresh();
        $this->assertEquals('120.0000', $purchaseOrder->lines->first()->billed_quantity);

        // Verify Subledger & GL Reconciliation for Vendor Bill 1
        $reconAfterBill1 = $this->reconciliationService->reconcilePayables($this->company->id);
        $this->assertTrue($reconAfterBill1->isReconciled);
        $this->assertEquals('60000.00', (string) $reconAfterBill1->subledgerTotal);
        $this->assertEquals('60000.00', (string) $reconAfterBill1->glControlTotal);

        // 4. Step 4: Partial Goods Receipt 2 (Remaining 80 units)
        $grn2 = $this->procurementService->createGoodsReceipt(new CreateGoodsReceiptDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            warehouseId: $this->warehouse->id,
            supplierId: $this->supplier->id,
            purchaseOrderId: $purchaseOrder->id,
            receiptDate: Carbon::today()->toDateString(),
            challanNumber: 'SUP-CH-99882',
            createdBy: $this->user->id,
            lines: [
                new CreateGoodsReceiptLineDTO(
                    purchaseOrderLineId: $purchaseOrder->lines->first()->id,
                    productId: $this->product->id,
                    variantId: $this->variant->id,
                    receivedQuantity: '80.0000',
                    unitCost: '500.0000',
                    batchNumber: 'INGOT-LOT-01'
                ),
            ]
        ));

        $grn2 = $this->procurementService->confirmGoodsReceipt($grn2->id);

        // Verify warehouse stock is now 200 units
        $stockBalance->refresh();
        $this->assertEquals('200.0000', $stockBalance->quantity_on_hand);

        // Verify Purchase Order updated to received
        $purchaseOrder->refresh();
        $this->assertEquals('received', $purchaseOrder->status);
        $this->assertEquals('200.0000', $purchaseOrder->lines->first()->received_quantity);

        // 5. Step 5: Issue Vendor Bill for GRN 2 (80 units = ৳40,000)
        $bill2 = $this->procurementService->createVendorBillFromReceipt(new CreateVendorBillFromReceiptDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            goodsReceiptId: $grn2->id,
            billDate: Carbon::today()->toDateString(),
            dueDate: Carbon::today()->addDays(30)->toDateString(),
            createdBy: $this->user->id,
            periodId: $this->period->id
        ));

        $this->assertEquals('40000.0000', $bill2->total_amount);

        // Verify Purchase Order status transitions to closed (all received & all billed)
        $purchaseOrder->refresh();
        $this->assertEquals('closed', $purchaseOrder->status);
        $this->assertEquals('200.0000', $purchaseOrder->lines->first()->billed_quantity);

        // 6. Step 6: Final End-to-End Triple Reconciliation Assertions
        $finalReconciliation = $this->reconciliationService->reconcilePayables($this->company->id);
        $this->assertTrue($finalReconciliation->isReconciled);
        $this->assertEquals('100000.00', (string) $finalReconciliation->subledgerTotal);
        $this->assertEquals('100000.00', (string) $finalReconciliation->glControlTotal);

        // Total movements received into stock
        $receivedQty = StockMovement::where('warehouse_id', $this->warehouse->id)
            ->where('movement_type', 'GOODS_RECEIPT')
            ->sum('quantity');
        $this->assertEquals('200.0000', $receivedQty);
    }
}
