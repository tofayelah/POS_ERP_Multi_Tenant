<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
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

    protected function resolveContext(): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated user.');
        }

        $tenant = Tenant::where('subdomain', 'acme')->firstOrFail();
        TenantContext::set($tenant);
        setPermissionsTeamId($tenant->id);

        $company = Company::where('tenant_id', $tenant->id)->where('legal_name', 'Acme Retail Ltd')->firstOrFail();
        $warehouse = Warehouse::where('tenant_id', $tenant->id)->where('company_id', $company->id)->where('code', 'WH-DHK-01')->firstOrFail();
        $variant = ProductVariant::where('tenant_id', $tenant->id)->where('company_id', $company->id)->where('sku', 'OIL-MUST-1L')->firstOrFail();
        $supplier = Supplier::where('tenant_id', $tenant->id)->where('company_id', $company->id)->where('code', 'SUP-MEGHNA-01')->firstOrFail();
        $customer = Customer::where('tenant_id', $tenant->id)->where('company_id', $company->id)->where('name', 'Rahman Grocery & Departmental Store')->firstOrFail();

        return [$user, $tenant, $company, $warehouse, $variant, $supplier, $customer];
    }

    public function createPurchaseOrder(Request $request): JsonResponse
    {
        [$user, $tenant, $company, $warehouse, $variant, $supplier] = $this->resolveContext();

        if (! $user->hasAnyRole(['Procurement Manager', 'Tenant Owner', 'Tenant Admin'])) {
            return response()->json(['error' => 'Forbidden: Only Procurement Manager can create Purchase Orders.'], Response::HTTP_FORBIDDEN);
        }

        $po = $this->procurementService->createPurchaseOrder(new CreatePurchaseOrderDTO(
            companyId: $company->id,
            supplierId: $supplier->id,
            warehouseId: $warehouse->id,
            orderDate: Carbon::now()->toDateString(),
            createdBy: $user->id,
            lines: [
                new CreatePurchaseOrderLineDTO(
                    productVariantId: $variant->id,
                    quantity: '100.0000',
                    unitPrice: '200.00',
                    taxCodeId: $variant->tax_code_id,
                ),
            ]
        ));

        return response()->json([
            'message' => 'Purchase Order successfully created by Procurement Manager.',
            'po_id' => $po->id,
            'po_number' => $po->po_number,
            'status' => $po->status,
            'total_amount' => $po->total_amount,
            'user' => $user->email,
            'role' => 'Procurement Manager',
        ]);
    }

    public function createGoodsReceipt(Request $request): JsonResponse
    {
        [$user, $tenant, $company, $warehouse, $variant, $supplier] = $this->resolveContext();

        if (! $user->hasAnyRole(['Warehouse Manager', 'Tenant Owner', 'Tenant Admin'])) {
            return response()->json(['error' => 'Forbidden: Only Warehouse Manager can receive goods.'], Response::HTTP_FORBIDDEN);
        }

        $po = PurchaseOrder::where('tenant_id', $tenant->id)
            ->where('company_id', $company->id)
            ->where('status', 'confirmed')
            ->latest()
            ->firstOrFail();

        $poLine = $po->lines()->firstOrFail();

        $grn = $this->procurementService->createGoodsReceipt(new CreateGoodsReceiptDTO(
            companyId: $company->id,
            purchaseOrderId: $po->id,
            warehouseId: $warehouse->id,
            receiptDate: Carbon::now()->toDateString(),
            createdBy: $user->id,
            lines: [
                new CreateGoodsReceiptLineDTO(
                    purchaseOrderLineId: $poLine->id,
                    productVariantId: $variant->id,
                    quantityReceived: '100.0000',
                    batchNumber: 'BATCH-MEGHNA-2026-01',
                    lotCost: '200.00',
                ),
            ]
        ));

        return response()->json([
            'message' => 'Goods Receipt successfully processed by Warehouse Manager. Stock updated.',
            'grn_id' => $grn->id,
            'grn_number' => $grn->grn_number,
            'status' => $grn->status,
            'user' => $user->email,
            'role' => 'Warehouse Manager',
        ]);
    }

    public function createVendorBill(Request $request): JsonResponse
    {
        [$user, $tenant, $company, $warehouse, $variant, $supplier] = $this->resolveContext();

        if (! $user->hasAnyRole(['Accountant', 'Tenant Owner', 'Tenant Admin'])) {
            return response()->json(['error' => 'Forbidden: Only Accountant can create and approve Vendor Bills.'], Response::HTTP_FORBIDDEN);
        }

        $grn = GoodsReceipt::where('tenant_id', $tenant->id)
            ->where('company_id', $company->id)
            ->where('status', 'received')
            ->latest()
            ->firstOrFail();

        $today = Carbon::now()->toDateString();
        $dueDate = Carbon::now()->addDays(30)->toDateString();

        $bill = $this->procurementService->createVendorBillFromReceipt(new CreateVendorBillFromReceiptDTO(
            companyId: $company->id,
            goodsReceiptId: $grn->id,
            billDate: $today,
            dueDate: $dueDate,
            approvedBy: $user->id,
            lines: [
                [
                    'goods_receipt_line_id' => $grn->lines()->firstOrFail()->id,
                    'billed_quantity' => '100.0000',
                    'unit_price' => '200.00',
                ],
            ]
        ));

        return response()->json([
            'message' => 'Vendor Bill posted to GL and Subledger by Lead Accountant.',
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
        [$user, $tenant, $company, $warehouse, $variant, $supplier, $customer] = $this->resolveContext();

        if (! $user->hasAnyRole(['Sales Manager', 'Tenant Owner', 'Tenant Admin'])) {
            return response()->json(['error' => 'Forbidden: Only Sales Manager can create Quotations and Sales Orders.'], Response::HTTP_FORBIDDEN);
        }

        $today = Carbon::now()->toDateString();
        $validUntil = Carbon::now()->addDays(15)->toDateString();

        // 1. Create Quotation
        $quotation = $this->salesService->createQuotation(new CreateQuotationDTO(
            companyId: $company->id,
            customerId: $customer->id,
            quotationDate: $today,
            validUntil: $validUntil,
            createdBy: $user->id,
            lines: [
                new CreateQuotationLineDTO(
                    productVariantId: $variant->id,
                    quantity: '50.0000',
                    unitPrice: '250.00',
                    taxCodeId: $variant->tax_code_id,
                ),
            ]
        ));

        // 2. Convert to Sales Order
        $so = $this->salesService->createSalesOrder(new CreateSalesOrderDTO(
            companyId: $company->id,
            customerId: $customer->id,
            quotationId: $quotation->id,
            orderDate: $today,
            createdBy: $user->id,
            lines: [
                new CreateSalesOrderLineDTO(
                    productVariantId: $variant->id,
                    quantity: '50.0000',
                    unitPrice: '250.00',
                    quotationLineId: $quotation->lines()->firstOrFail()->id,
                    taxCodeId: $variant->tax_code_id,
                ),
            ]
        ));

        return response()->json([
            'message' => 'Quotation and Sales Order successfully created by Sales Manager.',
            'quotation_id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'sales_order_id' => $so->id,
            'sales_order_number' => $so->order_number,
            'status' => $so->status,
            'total_amount' => $so->total_amount,
            'user' => $user->email,
            'role' => 'Sales Manager',
        ]);
    }

    public function createDeliveryChallan(Request $request): JsonResponse
    {
        [$user, $tenant, $company, $warehouse, $variant, $supplier, $customer] = $this->resolveContext();

        if (! $user->hasAnyRole(['Warehouse Manager', 'Tenant Owner', 'Tenant Admin'])) {
            return response()->json(['error' => 'Forbidden: Only Warehouse Manager can issue Delivery Challans.'], Response::HTTP_FORBIDDEN);
        }

        $so = SalesOrder::where('tenant_id', $tenant->id)
            ->where('company_id', $company->id)
            ->where('status', 'confirmed')
            ->latest()
            ->firstOrFail();

        $soLine = $so->lines()->firstOrFail();

        $challan = $this->salesService->createDeliveryChallan(new CreateDeliveryChallanDTO(
            companyId: $company->id,
            salesOrderId: $so->id,
            warehouseId: $warehouse->id,
            deliveryDate: Carbon::now()->toDateString(),
            createdBy: $user->id,
            lines: [
                new CreateDeliveryChallanLineDTO(
                    salesOrderLineId: $soLine->id,
                    productVariantId: $variant->id,
                    quantityDelivered: '50.0000',
                    batchNumber: 'BATCH-MEGHNA-2026-01',
                ),
            ]
        ));

        return response()->json([
            'message' => 'Delivery Challan issued by Warehouse Manager. Stock reduced.',
            'challan_id' => $challan->id,
            'challan_number' => $challan->challan_number,
            'status' => $challan->status,
            'user' => $user->email,
            'role' => 'Warehouse Manager',
        ]);
    }

    public function createSalesInvoice(Request $request): JsonResponse
    {
        [$user, $tenant, $company, $warehouse, $variant, $supplier, $customer] = $this->resolveContext();

        if (! $user->hasAnyRole(['Accountant', 'Tenant Owner', 'Tenant Admin'])) {
            return response()->json(['error' => 'Forbidden: Only Accountant can issue Sales Invoices.'], Response::HTTP_FORBIDDEN);
        }

        $challan = \App\Models\DeliveryChallan::where('tenant_id', $tenant->id)
            ->where('company_id', $company->id)
            ->where('status', 'confirmed')
            ->latest()
            ->firstOrFail();

        $today = Carbon::now()->toDateString();
        $dueDate = Carbon::now()->addDays(30)->toDateString();

        $invoice = $this->salesService->createInvoiceFromDelivery(new CreateInvoiceFromDeliveryDTO(
            companyId: $company->id,
            deliveryChallanId: $challan->id,
            invoiceDate: $today,
            dueDate: $dueDate,
            approvedBy: $user->id,
            lines: [
                [
                    'delivery_challan_line_id' => $challan->lines()->firstOrFail()->id,
                    'invoiced_quantity' => '50.0000',
                    'unit_price' => '250.00',
                ],
            ]
        ));

        return response()->json([
            'message' => 'Sales Invoice posted to GL and Subledger by Lead Accountant.',
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'total_amount' => $invoice->total_amount,
            'gl_journal_id' => $invoice->gl_journal_entry_id,
            'user' => $user->email,
            'role' => 'Accountant',
        ]);
    }
}
