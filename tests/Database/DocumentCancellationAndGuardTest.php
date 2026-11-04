<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\DTOs\StockMovementDTO;
use App\Modules\Procurement\Contracts\ProcurementDocumentChainContract;
use App\Modules\Procurement\DTOs\CreateGoodsReceiptDTO;
use App\Modules\Procurement\DTOs\CreateGoodsReceiptLineDTO;
use App\Modules\Procurement\DTOs\CreatePurchaseOrderDTO;
use App\Modules\Procurement\DTOs\CreatePurchaseOrderLineDTO;
use App\Modules\Procurement\Exceptions\ReceivedQuantityExceedsOrderException;
use App\Modules\Sales\Contracts\SalesDocumentChainContract;
use App\Modules\Sales\DTOs\CreateDeliveryChallanDTO;
use App\Modules\Sales\DTOs\CreateDeliveryChallanLineDTO;
use App\Modules\Sales\DTOs\CreateSalesOrderDTO;
use App\Modules\Sales\DTOs\CreateSalesOrderLineDTO;
use App\Modules\Sales\Exceptions\DeliveredQuantityExceedsOrderException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class DocumentCancellationAndGuardTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected Warehouse $warehouse;

    protected Customer $customer;

    protected Supplier $supplier;

    protected User $user;

    protected FiscalYear $fiscalYear;

    protected Product $product;

    protected ProductVariant $variant;

    protected TaxCode $taxCode;

    protected SalesDocumentChainContract $salesService;

    protected ProcurementDocumentChainContract $procurementService;

    protected StockMovementServiceContract $stockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Guard Tenant',
            'subdomain' => 'guard-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Guard Company Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Guard Branch',
            'code' => 'GB-01',
        ]);

        $this->warehouse = Warehouse::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Guard Warehouse',
            'code' => 'GW-01',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Guard Customer',
            'contact_number' => '01700998877',
            'credit_limit' => '500000.0000',
            'current_balance' => '0.0000',
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Guard Officer',
            'email' => 'officer-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);

        $this->supplier = Supplier::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Guard Supplier',
            'email' => 'guardsup-'.Str::random(6).'@example.com',
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

        $this->taxCode = TaxCode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'EX-GUARD',
            'name_en' => 'Exempt Guard',
            'description' => 'Exempt',
        ]);

        $this->product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'P-GUARD-01',
            'name_en' => 'Guard Product',
            'base_uom' => 'Pcs',
        ]);

        $this->variant = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'tax_code_id' => $this->taxCode->id,
            'sku' => 'SKU-GUARD-01',
            'name_en' => 'Standard Unit',
            'cost_price' => '200.0000',
        ]);

        $this->salesService = app(SalesDocumentChainContract::class);
        $this->procurementService = app(ProcurementDocumentChainContract::class);
        $this->stockService = app(StockMovementServiceContract::class);

        // Seed 500 units in stock for delivery tests
        DB::transaction(function () {
            $this->stockService->recordMovement(new StockMovementDTO(
                companyId: $this->company->id,
                warehouseId: $this->warehouse->id,
                stockLocationId: null,
                productVariantId: $this->variant->id,
                movementType: 'INBOUND_INITIAL',
                quantity: '500.0000',
                unitCost: '200.0000',
                referenceType: 'initial_stock',
                referenceId: (string) Str::uuid(),
                movementDate: Carbon::today()->toDateString()
            ));
        });
    }

    /**
     * Asserts that a delivery challan cannot deliver more than ordered on the sales order (NEW-34).
     */
    public function test_cannot_over_deliver_sales_order_line(): void
    {
        TenantContext::set($this->tenant);

        $so = $this->salesService->createSalesOrder(new CreateSalesOrderDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            customerId: $this->customer->id,
            quotationId: null,
            orderDate: Carbon::today()->toDateString(),
            expectedDeliveryDate: null,
            createdBy: $this->user->id,
            lines: [
                new CreateSalesOrderLineDTO(
                    quotationLineId: null,
                    productId: $this->product->id,
                    variantId: $this->variant->id,
                    orderedQuantity: '50.0000',
                    unitPrice: '300.0000',
                ),
            ]
        ));

        $so = $this->salesService->confirmSalesOrder($so->id);

        // Attempting to deliver 60 units on a 50-unit order must throw DeliveredQuantityExceedsOrderException
        $this->expectException(DeliveredQuantityExceedsOrderException::class);

        $challan = $this->salesService->createDeliveryChallan(new CreateDeliveryChallanDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            warehouseId: $this->warehouse->id,
            customerId: $this->customer->id,
            salesOrderId: $so->id,
            challanDate: Carbon::today()->toDateString(),
            createdBy: $this->user->id,
            lines: [
                new CreateDeliveryChallanLineDTO(
                    salesOrderLineId: $so->lines->first()->id,
                    productId: $this->product->id,
                    variantId: $this->variant->id,
                    quantity: '60.0000',
                ),
            ]
        ));

        $this->salesService->confirmDeliveryChallan($challan->id);
    }

    /**
     * Asserts that a goods receipt cannot receive more than ordered on the purchase order (NEW-34).
     */
    public function test_cannot_over_receive_purchase_order_line(): void
    {
        TenantContext::set($this->tenant);

        $po = $this->procurementService->createPurchaseOrder(new CreatePurchaseOrderDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            supplierId: $this->supplier->id,
            orderDate: Carbon::today()->toDateString(),
            expectedDeliveryDate: null,
            createdBy: $this->user->id,
            lines: [
                new CreatePurchaseOrderLineDTO(
                    productId: $this->product->id,
                    variantId: $this->variant->id,
                    orderedQuantity: '50.0000',
                    unitPrice: '200.0000',
                ),
            ]
        ));

        $po = $this->procurementService->confirmPurchaseOrder($po->id);

        // Attempting to receive 60 units on a 50-unit order must throw ReceivedQuantityExceedsOrderException
        $this->expectException(ReceivedQuantityExceedsOrderException::class);

        $grn = $this->procurementService->createGoodsReceipt(new CreateGoodsReceiptDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            warehouseId: $this->warehouse->id,
            supplierId: $this->supplier->id,
            purchaseOrderId: $po->id,
            receiptDate: Carbon::today()->toDateString(),
            createdBy: $this->user->id,
            lines: [
                new CreateGoodsReceiptLineDTO(
                    purchaseOrderLineId: $po->lines->first()->id,
                    productId: $this->product->id,
                    variantId: $this->variant->id,
                    receivedQuantity: '60.0000',
                    unitCost: '200.0000',
                ),
            ]
        ));

        $this->procurementService->confirmGoodsReceipt($grn->id);
    }

    /**
     * Asserts that confirmed sales orders with fulfilled deliveries cannot be cancelled directly (NEW-34).
     */
    public function test_cannot_cancel_sales_order_with_existing_deliveries(): void
    {
        TenantContext::set($this->tenant);

        $so = $this->salesService->createSalesOrder(new CreateSalesOrderDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            customerId: $this->customer->id,
            quotationId: null,
            orderDate: Carbon::today()->toDateString(),
            expectedDeliveryDate: null,
            createdBy: $this->user->id,
            lines: [
                new CreateSalesOrderLineDTO(
                    quotationLineId: null,
                    productId: $this->product->id,
                    variantId: $this->variant->id,
                    orderedQuantity: '20.0000',
                    unitPrice: '300.0000',
                ),
            ]
        ));

        $so = $this->salesService->confirmSalesOrder($so->id);

        $challan = $this->salesService->createDeliveryChallan(new CreateDeliveryChallanDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            warehouseId: $this->warehouse->id,
            customerId: $this->customer->id,
            salesOrderId: $so->id,
            challanDate: Carbon::today()->toDateString(),
            createdBy: $this->user->id,
            lines: [
                new CreateDeliveryChallanLineDTO(
                    salesOrderLineId: $so->lines->first()->id,
                    productId: $this->product->id,
                    variantId: $this->variant->id,
                    quantity: '10.0000',
                ),
            ]
        ));

        $this->salesService->confirmDeliveryChallan($challan->id);

        // Attempting to cancel a partially delivered sales order must throw RuntimeException
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Cannot cancel sales order with existing deliveries/i');
        $this->salesService->cancelSalesOrder($so->id);
    }

    /**
     * Asserts that confirmed purchase orders with fulfilled receipts cannot be cancelled directly (NEW-34).
     */
    public function test_cannot_cancel_purchase_order_with_existing_receipts(): void
    {
        TenantContext::set($this->tenant);

        $po = $this->procurementService->createPurchaseOrder(new CreatePurchaseOrderDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            supplierId: $this->supplier->id,
            orderDate: Carbon::today()->toDateString(),
            expectedDeliveryDate: null,
            createdBy: $this->user->id,
            lines: [
                new CreatePurchaseOrderLineDTO(
                    productId: $this->product->id,
                    variantId: $this->variant->id,
                    orderedQuantity: '20.0000',
                    unitPrice: '200.0000',
                ),
            ]
        ));

        $po = $this->procurementService->confirmPurchaseOrder($po->id);

        $grn = $this->procurementService->createGoodsReceipt(new CreateGoodsReceiptDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            warehouseId: $this->warehouse->id,
            supplierId: $this->supplier->id,
            purchaseOrderId: $po->id,
            receiptDate: Carbon::today()->toDateString(),
            createdBy: $this->user->id,
            lines: [
                new CreateGoodsReceiptLineDTO(
                    purchaseOrderLineId: $po->lines->first()->id,
                    productId: $this->product->id,
                    variantId: $this->variant->id,
                    receivedQuantity: '10.0000',
                    unitCost: '200.0000',
                ),
            ]
        ));

        $this->procurementService->confirmGoodsReceipt($grn->id);

        // Attempting to cancel a partially received purchase order must throw RuntimeException
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Cannot cancel purchase order with existing receipts/i');
        $this->procurementService->cancelPurchaseOrder($po->id);
    }

    /**
     * Asserts that unfulfilled sales orders and purchase orders can be cleanly cancelled (NEW-34).
     */
    public function test_unfulfilled_orders_can_be_cancelled(): void
    {
        TenantContext::set($this->tenant);

        $so = $this->salesService->createSalesOrder(new CreateSalesOrderDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            customerId: $this->customer->id,
            quotationId: null,
            orderDate: Carbon::today()->toDateString(),
            expectedDeliveryDate: null,
            createdBy: $this->user->id,
            lines: [
                new CreateSalesOrderLineDTO(
                    quotationLineId: null,
                    productId: $this->product->id,
                    variantId: $this->variant->id,
                    orderedQuantity: '10.0000',
                    unitPrice: '300.0000',
                ),
            ]
        ));

        $cancelledSo = $this->salesService->cancelSalesOrder($so->id);
        $this->assertEquals('cancelled', $cancelledSo->status);

        $po = $this->procurementService->createPurchaseOrder(new CreatePurchaseOrderDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            supplierId: $this->supplier->id,
            orderDate: Carbon::today()->toDateString(),
            expectedDeliveryDate: null,
            createdBy: $this->user->id,
            lines: [
                new CreatePurchaseOrderLineDTO(
                    productId: $this->product->id,
                    variantId: $this->variant->id,
                    orderedQuantity: '10.0000',
                    unitPrice: '200.0000',
                ),
            ]
        ));

        $cancelledPo = $this->procurementService->cancelPurchaseOrder($po->id);
        $this->assertEquals('cancelled', $cancelledPo->status);
    }
}
