<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\DeliveryChallan;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Accounting\Subledger\Contracts\SubledgerReconciliationContract;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\DTOs\StockMovementDTO;
use App\Modules\Sales\Contracts\SalesDocumentChainContract;
use App\Modules\Sales\DTOs\CreateDeliveryChallanDTO;
use App\Modules\Sales\DTOs\CreateDeliveryChallanLineDTO;
use App\Modules\Sales\DTOs\CreateInvoiceFromDeliveryDTO;
use App\Modules\Sales\DTOs\CreateQuotationDTO;
use App\Modules\Sales\DTOs\CreateQuotationLineDTO;
use App\Modules\Sales\DTOs\CreateSalesOrderDTO;
use App\Modules\Sales\DTOs\CreateSalesOrderLineDTO;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesDocumentChainFeatureTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected Warehouse $warehouse;

    protected Customer $customer;

    protected User $user;

    protected FiscalYear $fiscalYear;

    protected AccountingPeriod $period;

    protected ChartOfAccount $arAccount;

    protected ChartOfAccount $revenueAccount;

    protected Product $product;

    protected ProductVariant $variant;

    protected TaxCode $taxCode;

    protected SalesDocumentChainContract $salesService;

    protected StockMovementServiceContract $stockService;

    protected SubledgerReconciliationContract $reconciliationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sales Chain Tenant',
            'subdomain' => 'sales-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Sales Chain Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Main Sales Branch',
            'code' => 'MSB-01',
        ]);

        $this->warehouse = Warehouse::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Finished Goods Warehouse',
            'code' => 'FG-WH-01',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Apex Enterprises',
            'contact_number' => '01711223344',
            'credit_limit' => '200000.0000',
            'current_balance' => '0.0000',
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'O2C Manager',
            'email' => 'o2c-'.Str::random(6).'@example.com',
            'password' => 'secret123',
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

        // General Ledger Accounts
        $this->arAccount = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1030',
            'account_name' => 'Accounts Receivable (Debtors)',
            'account_type' => 'asset',
        ]);

        $this->revenueAccount = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '4010',
            'account_name' => 'Sales Revenue',
            'account_type' => 'revenue',
        ]);

        $this->taxCode = TaxCode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'EXEMPT',
            'name_en' => 'Exempt Sales',
            'description' => 'Exempt from VAT',
        ]);

        $this->product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'P-CABLE-100',
            'name_en' => 'Industrial Cable Reel',
            'base_uom' => 'Reel',
        ]);

        $this->variant = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'tax_code_id' => $this->taxCode->id,
            'sku' => 'SKU-CABLE-100M',
            'name_en' => '100m Drum',
        ]);

        // Services
        $this->salesService = app(SalesDocumentChainContract::class);
        $this->stockService = app(StockMovementServiceContract::class);
        $this->reconciliationService = app(SubledgerReconciliationContract::class);

        // Initial inventory: 100 units available in Finished Goods Warehouse
        DB::transaction(function () {
            $this->stockService->recordMovement(new StockMovementDTO(
                companyId: $this->company->id,
                warehouseId: $this->warehouse->id,
                stockLocationId: null,
                productVariantId: $this->variant->id,
                movementType: 'INBOUND_INITIAL',
                quantity: '100.0000',
                unitCost: '600.0000',
                referenceType: 'initial_stock',
                referenceId: (string) Str::uuid(),
                batchNumber: 'BATCH-2026-01',
                movementDate: Carbon::today()->toDateString()
            ));
        });
    }

    /**
     * Runs full Order-to-Cash document chain with partial deliveries,
     * verifying stock movements, GL posting, and subledger reconciliation (NEW-34).
     */
    public function test_order_to_cash_document_chain_end_to_end_with_partial_delivery(): void
    {
        TenantContext::set($this->tenant);

        // 1. Step 1: Draft Quotation (100 units @ ৳1,000)
        $quotation = $this->salesService->createQuotation(new CreateQuotationDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            customerId: $this->customer->id,
            quotationDate: Carbon::today()->toDateString(),
            validUntil: Carbon::today()->addDays(14)->toDateString(),
            createdBy: $this->user->id,
            lines: [
                new CreateQuotationLineDTO(
                    productId: $this->product->id,
                    variantId: $this->variant->id,
                    quantity: '100.0000',
                    unitPrice: '1000.0000',
                    taxAmount: '0.0000'
                ),
            ]
        ));

        $this->assertStringStartsWith('QT-', $quotation->quotation_number);
        $this->assertEquals('100000.0000', $quotation->grand_total);
        $this->assertEquals('draft', $quotation->status);

        // 2. Step 2: Convert Quotation to Sales Order & Confirm
        $salesOrder = $this->salesService->convertQuotationToSalesOrder($quotation->id, new CreateSalesOrderDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            customerId: $this->customer->id,
            quotationId: $quotation->id,
            orderDate: Carbon::today()->toDateString(),
            expectedDeliveryDate: Carbon::today()->addDays(7)->toDateString(),
            createdBy: $this->user->id,
            lines: [
                new CreateSalesOrderLineDTO(
                    quotationLineId: $quotation->lines->first()->id,
                    productId: $this->product->id,
                    variantId: $this->variant->id,
                    orderedQuantity: '100.0000',
                    unitPrice: '1000.0000',
                    taxAmount: '0.0000'
                ),
            ]
        ));

        $salesOrder = $this->salesService->confirmSalesOrder($salesOrder->id);

        $this->assertStringStartsWith('SO-', $salesOrder->order_number);
        $this->assertEquals('confirmed', $salesOrder->status);
        $this->assertEquals('100.0000', $salesOrder->lines->first()->ordered_quantity);
        $this->assertEquals('0.0000', $salesOrder->lines->first()->delivered_quantity);
        $this->assertEquals('0.0000', $salesOrder->lines->first()->invoiced_quantity);

        // 3. Step 3: Partial Delivery 1 (60 units)
        $challan1 = $this->salesService->createDeliveryChallan(new CreateDeliveryChallanDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            warehouseId: $this->warehouse->id,
            customerId: $this->customer->id,
            salesOrderId: $salesOrder->id,
            challanDate: Carbon::today()->toDateString(),
            vehicleNumber: 'DM-CHA-1234',
            driverName: 'Abdul Karim',
            driverPhone: '01700112233',
            createdBy: $this->user->id,
            lines: [
                new CreateDeliveryChallanLineDTO(
                    salesOrderLineId: $salesOrder->lines->first()->id,
                    productId: $this->product->id,
                    variantId: $this->variant->id,
                    quantity: '60.0000',
                    batchNumber: 'BATCH-2026-01'
                ),
            ]
        ));

        $challan1 = $this->salesService->confirmDeliveryChallan($challan1->id);

        $this->assertStringStartsWith('DC-', $challan1->challan_number);
        $this->assertEquals('confirmed', $challan1->status);

        // Verify stock movement ledger: 60 units dispatched, 40 left in warehouse
        $stockBalance = StockBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();
        $this->assertNotNull($stockBalance);
        $this->assertEquals('40.0000', $stockBalance->quantity_on_hand);

        // Verify Sales Order updated to partially_delivered
        $salesOrder->refresh();
        $this->assertEquals('partially_delivered', $salesOrder->status);
        $this->assertEquals('60.0000', $salesOrder->lines->first()->delivered_quantity);
        $this->assertEquals('0.0000', $salesOrder->lines->first()->invoiced_quantity);

        // 4. Step 4: Issue Sales Invoice for Delivery Challan 1 (60 units = ৳60,000)
        $invoice1 = $this->salesService->createInvoiceFromDelivery(new CreateInvoiceFromDeliveryDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            deliveryChallanId: $challan1->id,
            invoiceDate: Carbon::today()->toDateString(),
            createdBy: $this->user->id,
            periodId: $this->period->id
        ));

        $this->assertStringStartsWith('INV-', $invoice1->invoice_number);
        $this->assertEquals('60000.0000', $invoice1->total_payable);

        // Verify Sales Order invoiced_quantity
        $salesOrder->refresh();
        $this->assertEquals('60.0000', $salesOrder->lines->first()->invoiced_quantity);

        // Verify GL Double Entry for Invoice 1
        $reconAfterInv1 = $this->reconciliationService->reconcileReceivables($this->company->id);
        $this->assertTrue($reconAfterInv1->isReconciled);
        $this->assertEquals('60000.00', (string) $reconAfterInv1->subledgerTotal);
        $this->assertEquals('60000.00', (string) $reconAfterInv1->glControlTotal);

        // 5. Step 5: Partial Delivery 2 (Remaining 40 units)
        $challan2 = $this->salesService->createDeliveryChallan(new CreateDeliveryChallanDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            warehouseId: $this->warehouse->id,
            customerId: $this->customer->id,
            salesOrderId: $salesOrder->id,
            challanDate: Carbon::today()->toDateString(),
            createdBy: $this->user->id,
            lines: [
                new CreateDeliveryChallanLineDTO(
                    salesOrderLineId: $salesOrder->lines->first()->id,
                    productId: $this->product->id,
                    variantId: $this->variant->id,
                    quantity: '40.0000',
                    batchNumber: 'BATCH-2026-01'
                ),
            ]
        ));

        $challan2 = $this->salesService->confirmDeliveryChallan($challan2->id);

        // Verify warehouse stock is now exactly 0
        $stockBalance->refresh();
        $this->assertEquals('0.0000', $stockBalance->quantity_on_hand);

        // Verify Sales Order updated to delivered
        $salesOrder->refresh();
        $this->assertEquals('delivered', $salesOrder->status);
        $this->assertEquals('100.0000', $salesOrder->lines->first()->delivered_quantity);

        // 6. Step 6: Issue Sales Invoice for Delivery Challan 2 (40 units = ৳40,000)
        $invoice2 = $this->salesService->createInvoiceFromDelivery(new CreateInvoiceFromDeliveryDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            deliveryChallanId: $challan2->id,
            invoiceDate: Carbon::today()->toDateString(),
            createdBy: $this->user->id,
            periodId: $this->period->id
        ));

        $this->assertEquals('40000.0000', $invoice2->total_payable);

        // Verify Sales Order status transitions to closed (all delivered & all invoiced)
        $salesOrder->refresh();
        $this->assertEquals('closed', $salesOrder->status);
        $this->assertEquals('100.0000', $salesOrder->lines->first()->invoiced_quantity);

        // 7. Step 7: Final End-to-End Triple Reconciliation Assertions
        $finalReconciliation = $this->reconciliationService->reconcileReceivables($this->company->id);
        $this->assertTrue($finalReconciliation->isReconciled);
        $this->assertEquals('100000.00', (string) $finalReconciliation->subledgerTotal);
        $this->assertEquals('100000.00', (string) $finalReconciliation->glControlTotal);

        // Total movements dispatched from stock
        $dispatchedQty = StockMovement::where('warehouse_id', $this->warehouse->id)
            ->where('movement_type', 'DELIVERY_CHALLAN')
            ->sum('quantity');
        $this->assertEquals('-100.0000', $dispatchedQty);
    }
}
