<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
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
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Procurement\Contracts\ProcurementDocumentChainContract;
use App\Modules\Procurement\DTOs\CreateGoodsReceiptDTO;
use App\Modules\Procurement\DTOs\CreateGoodsReceiptLineDTO;
use App\Modules\Procurement\DTOs\CreatePurchaseOrderDTO;
use App\Modules\Procurement\DTOs\CreatePurchaseOrderLineDTO;
use App\Modules\Procurement\DTOs\CreateVendorBillFromReceiptDTO;
use App\Modules\Sales\Contracts\SalesDocumentChainContract;
use App\Modules\Sales\DTOs\CreateDeliveryChallanDTO;
use App\Modules\Sales\DTOs\CreateDeliveryChallanLineDTO;
use App\Modules\Sales\DTOs\CreateInvoiceFromDeliveryDTO;
use App\Modules\Sales\DTOs\CreateQuotationDTO;
use App\Modules\Sales\DTOs\CreateQuotationLineDTO;
use App\Modules\Sales\DTOs\CreateSalesOrderDTO;
use App\Modules\Sales\DTOs\CreateSalesOrderLineDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class WalkthroughChainController extends Controller
{
    public function __construct(
        protected ProcurementDocumentChainContract $procurementService,
        protected SalesDocumentChainContract $salesService,
    ) {}

    /**
     * @return array{0: User, 1: Tenant, 2: Company, 3: Branch, 4: Warehouse, 5: Product, 6: ProductVariant, 7: Supplier, 8: Customer, 9: AccountingPeriod}
     */
    protected function resolveContext(): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated user.');
        }

        /** @var Tenant $tenant */
        $tenant = Tenant::where('subdomain', 'acme')->firstOrFail();
        TenantContext::set($tenant);
        setPermissionsTeamId($tenant->id);

        /** @var Company $company */
        $company = Company::where('tenant_id', $tenant->id)->where('legal_name', 'Acme Retail Ltd')->firstOrFail();

        /** @var Branch $branch */
        $branch = Branch::where('tenant_id', $tenant->id)->where('company_id', $company->id)->firstOrFail();

        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::where('tenant_id', $tenant->id)->where('company_id', $company->id)->where('code', 'WH-DHK-01')->firstOrFail();

        /** @var Product $product */
        $product = Product::where('tenant_id', $tenant->id)->where('company_id', $company->id)->where('code', 'PRD-MUSTARD-OIL')->firstOrFail();

        /** @var ProductVariant $variant */
        $variant = ProductVariant::where('tenant_id', $tenant->id)->where('company_id', $company->id)->where('sku', 'OIL-MUST-1L')->firstOrFail();

        /** @var Supplier $supplier */
        $supplier = Supplier::where('tenant_id', $tenant->id)->where('company_id', $company->id)->where('code', 'SUP-MEGHNA-01')->firstOrFail();

        /** @var Customer $customer */
        $customer = Customer::where('tenant_id', $tenant->id)->where('company_id', $company->id)->where('name', 'Rahman Grocery & Departmental Store')->firstOrFail();

        /** @var AccountingPeriod $period */
        $period = AccountingPeriod::where('tenant_id', $tenant->id)->where('company_id', $company->id)->where('status', 'open')->firstOrFail();

        return [$user, $tenant, $company, $branch, $warehouse, $product, $variant, $supplier, $customer, $period];
    }

    public function createPurchaseOrder(Request $request): JsonResponse
    {
        [$user, $tenant, $company, $branch, $warehouse, $product, $variant, $supplier] = $this->resolveContext();

        if (! $user->hasAnyRole(['Procurement Manager', 'Tenant Owner', 'Tenant Admin'])) {
            return response()->json(['error' => 'Forbidden: Only Procurement Manager can create Purchase Orders.'], Response::HTTP_FORBIDDEN);
        }

        $po = $this->procurementService->createPurchaseOrder(new CreatePurchaseOrderDTO(
            companyId: $company->id,
            branchId: $branch->id,
            supplierId: $supplier->id,
            orderDate: Carbon::today()->toDateString(),
            expectedDeliveryDate: Carbon::today()->addDays(7)->toDateString(),
            createdBy: $user->id,
            lines: [
                new CreatePurchaseOrderLineDTO(
                    productId: $product->id,
                    variantId: $variant->id,
                    orderedQuantity: '100.0000',
                    unitPrice: '200.0000',
                    taxAmount: '3000.0000'
                ),
            ]
        ));

        $po = $this->procurementService->confirmPurchaseOrder($po->id);

        return response()->json([
            'message' => 'Purchase Order successfully created & confirmed by Procurement Manager.',
            'po_id' => $po->id,
            'po_number' => $po->order_number,
            'status' => $po->status,
            'grand_total' => $po->grand_total,
            'user' => $user->email,
            'role' => 'Procurement Manager',
        ]);
    }

    public function createGoodsReceipt(Request $request): JsonResponse
    {
        [$user, $tenant, $company, $branch, $warehouse, $product, $variant, $supplier] = $this->resolveContext();

        if (! $user->hasAnyRole(['Warehouse Manager', 'Tenant Owner', 'Tenant Admin'])) {
            return response()->json(['error' => 'Forbidden: Only Warehouse Manager can receive goods.'], Response::HTTP_FORBIDDEN);
        }

        /** @var PurchaseOrder $po */
        $po = PurchaseOrder::where('tenant_id', $tenant->id)
            ->where('company_id', $company->id)
            ->where('status', 'confirmed')
            ->latest()
            ->firstOrFail();

        $poLine = $po->lines()->firstOrFail();

        $grn = $this->procurementService->createGoodsReceipt(new CreateGoodsReceiptDTO(
            companyId: $company->id,
            branchId: $branch->id,
            warehouseId: $warehouse->id,
            supplierId: $supplier->id,
            purchaseOrderId: $po->id,
            receiptDate: Carbon::today()->toDateString(),
            challanNumber: 'SUP-CH-MEGHNA-001',
            createdBy: $user->id,
            lines: [
                new CreateGoodsReceiptLineDTO(
                    purchaseOrderLineId: $poLine->id,
                    productId: $product->id,
                    variantId: $variant->id,
                    receivedQuantity: '100.0000',
                    unitCost: '200.0000',
                    batchNumber: 'BATCH-MEGHNA-2026-01'
                ),
            ]
        ));

        $grn = $this->procurementService->confirmGoodsReceipt($grn->id);

        return response()->json([
            'message' => 'Goods Receipt successfully processed & stock updated by Warehouse Manager.',
            'grn_id' => $grn->id,
            'grn_number' => $grn->grn_number,
            'status' => $grn->status,
            'user' => $user->email,
            'role' => 'Warehouse Manager',
        ]);
    }

    public function createVendorBill(Request $request): JsonResponse
    {
        [$user, $tenant, $company, $branch, $warehouse, $product, $variant, $supplier, $customer, $period] = $this->resolveContext();

        if (! $user->hasAnyRole(['Accountant', 'Tenant Owner', 'Tenant Admin'])) {
            return response()->json(['error' => 'Forbidden: Only Accountant can create and approve Vendor Bills.'], Response::HTTP_FORBIDDEN);
        }

        /** @var GoodsReceipt $grn */
        $grn = GoodsReceipt::where('tenant_id', $tenant->id)
            ->where('company_id', $company->id)
            ->where('status', 'received')
            ->latest()
            ->firstOrFail();

        $today = Carbon::today()->toDateString();
        $dueDate = Carbon::today()->addDays(30)->toDateString();

        $bill = $this->procurementService->createVendorBillFromReceipt(new CreateVendorBillFromReceiptDTO(
            companyId: $company->id,
            branchId: $branch->id,
            goodsReceiptId: $grn->id,
            billDate: $today,
            dueDate: $dueDate,
            createdBy: $user->id,
            periodId: $period->id
        ));

        return response()->json([
            'message' => 'Vendor Bill posted to GL and AP Subledger by Lead Accountant.',
            'bill_id' => $bill->id,
            'bill_number' => $bill->bill_number,
            'status' => $bill->status,
            'total_amount' => $bill->total_amount,
            'gl_journal_id' => $bill->gl_journal_entry_id,
            'user' => $user->email,
            'role' => 'Accountant',
        ]);
    }

    public function createQuotationAndSalesOrder(Request $request): JsonResponse
    {
        [$user, $tenant, $company, $branch, $warehouse, $product, $variant, $supplier, $customer] = $this->resolveContext();

        if (! $user->hasAnyRole(['Sales Manager', 'Tenant Owner', 'Tenant Admin'])) {
            return response()->json(['error' => 'Forbidden: Only Sales Manager can create Quotations and Sales Orders.'], Response::HTTP_FORBIDDEN);
        }

        $today = Carbon::today()->toDateString();
        $validUntil = Carbon::today()->addDays(15)->toDateString();

        // 1. Create Quotation
        $quotation = $this->salesService->createQuotation(new CreateQuotationDTO(
            companyId: $company->id,
            branchId: $branch->id,
            customerId: $customer->id,
            quotationDate: $today,
            validUntil: $validUntil,
            createdBy: $user->id,
            lines: [
                new CreateQuotationLineDTO(
                    productId: $product->id,
                    variantId: $variant->id,
                    quantity: '50.0000',
                    unitPrice: '250.0000',
                    taxAmount: '1875.0000'
                ),
            ]
        ));

        // 2. Convert to Sales Order & Confirm
        $so = $this->salesService->convertQuotationToSalesOrder($quotation->id, new CreateSalesOrderDTO(
            companyId: $company->id,
            branchId: $branch->id,
            customerId: $customer->id,
            quotationId: $quotation->id,
            orderDate: $today,
            expectedDeliveryDate: Carbon::today()->addDays(7)->toDateString(),
            createdBy: $user->id,
            lines: [
                new CreateSalesOrderLineDTO(
                    quotationLineId: $quotation->lines()->firstOrFail()->id,
                    productId: $product->id,
                    variantId: $variant->id,
                    orderedQuantity: '50.0000',
                    unitPrice: '250.0000',
                    taxAmount: '1875.0000'
                ),
            ]
        ));

        $so = $this->salesService->confirmSalesOrder($so->id);

        return response()->json([
            'message' => 'Quotation and Sales Order successfully created & confirmed by Sales Manager.',
            'quotation_id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'sales_order_id' => $so->id,
            'sales_order_number' => $so->order_number,
            'status' => $so->status,
            'grand_total' => $so->grand_total,
            'user' => $user->email,
            'role' => 'Sales Manager',
        ]);
    }

    public function createDeliveryChallan(Request $request): JsonResponse
    {
        [$user, $tenant, $company, $branch, $warehouse, $product, $variant, $supplier, $customer] = $this->resolveContext();

        if (! $user->hasAnyRole(['Warehouse Manager', 'Tenant Owner', 'Tenant Admin'])) {
            return response()->json(['error' => 'Forbidden: Only Warehouse Manager can issue Delivery Challans.'], Response::HTTP_FORBIDDEN);
        }

        /** @var SalesOrder $so */
        $so = SalesOrder::where('tenant_id', $tenant->id)
            ->where('company_id', $company->id)
            ->where('status', 'confirmed')
            ->latest()
            ->firstOrFail();

        $soLine = $so->lines()->firstOrFail();

        $challan = $this->salesService->createDeliveryChallan(new CreateDeliveryChallanDTO(
            companyId: $company->id,
            branchId: $branch->id,
            warehouseId: $warehouse->id,
            customerId: $customer->id,
            salesOrderId: $so->id,
            challanDate: Carbon::today()->toDateString(),
            vehicleNumber: 'DM-CHA-5566',
            driverName: 'Rafiqul Islam',
            driverPhone: '01711223344',
            createdBy: $user->id,
            lines: [
                new CreateDeliveryChallanLineDTO(
                    salesOrderLineId: $soLine->id,
                    productId: $product->id,
                    variantId: $variant->id,
                    quantity: '50.0000',
                    batchNumber: 'BATCH-MEGHNA-2026-01'
                ),
            ]
        ));

        $challan = $this->salesService->confirmDeliveryChallan($challan->id);

        return response()->json([
            'message' => 'Delivery Challan issued & stock dispatched by Warehouse Manager.',
            'challan_id' => $challan->id,
            'challan_number' => $challan->challan_number,
            'status' => $challan->status,
            'user' => $user->email,
            'role' => 'Warehouse Manager',
        ]);
    }

    public function createSalesInvoice(Request $request): JsonResponse
    {
        [$user, $tenant, $company, $branch, $warehouse, $product, $variant, $supplier, $customer, $period] = $this->resolveContext();

        if (! $user->hasAnyRole(['Accountant', 'Tenant Owner', 'Tenant Admin'])) {
            return response()->json(['error' => 'Forbidden: Only Accountant can issue Sales Invoices.'], Response::HTTP_FORBIDDEN);
        }

        /** @var DeliveryChallan $challan */
        $challan = DeliveryChallan::where('tenant_id', $tenant->id)
            ->where('company_id', $company->id)
            ->where('status', 'confirmed')
            ->latest()
            ->firstOrFail();

        $today = Carbon::today()->toDateString();

        $invoice = $this->salesService->createInvoiceFromDelivery(new CreateInvoiceFromDeliveryDTO(
            companyId: $company->id,
            branchId: $branch->id,
            deliveryChallanId: $challan->id,
            invoiceDate: $today,
            createdBy: $user->id,
            periodId: $period->id
        ));

        return response()->json([
            'message' => 'Sales Invoice posted to GL and AR Subledger by Lead Accountant.',
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'total_amount' => $invoice->grand_total,
            'gl_journal_id' => $invoice->gl_journal_entry_id,
            'user' => $user->email,
            'role' => 'Accountant',
        ]);
    }
}
