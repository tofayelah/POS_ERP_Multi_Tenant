<?php

namespace App\Modules\Procurement\Services;

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SupplierBill;
use App\Models\SupplierBillLine;
use App\Models\SupplierQuotation;
use App\Models\SupplierQuotationLine;
use App\Modules\Accounting\Contracts\AccountingPostingServiceContract;
use App\Modules\Accounting\Contracts\NumberSeriesContract;
use App\Modules\Accounting\DTOs\JournalEntryDTO;
use App\Modules\Accounting\DTOs\JournalLineDTO;
use App\Modules\Accounting\Subledger\Contracts\SubledgerContract;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\DTOs\StockMovementDTO;
use App\Modules\Procurement\Contracts\ProcurementDocumentChainContract;
use App\Modules\Procurement\DTOs\CreateGoodsReceiptDTO;
use App\Modules\Procurement\DTOs\CreatePurchaseOrderDTO;
use App\Modules\Procurement\DTOs\CreateSupplierQuotationDTO;
use App\Modules\Procurement\DTOs\CreateVendorBillFromReceiptDTO;
use App\Modules\Procurement\Exceptions\BilledQuantityExceedsReceiptException;
use App\Modules\Procurement\Exceptions\ReceivedQuantityExceedsOrderException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ProcurementDocumentChainService implements ProcurementDocumentChainContract
{
    public function __construct(
        protected NumberSeriesContract $numberSeries,
        protected StockMovementServiceContract $stockService,
        protected AccountingPostingServiceContract $accountingService,
        protected ?SubledgerContract $subledger = null,
    ) {}

    public function createPurchaseOrder(CreatePurchaseOrderDTO $dto): PurchaseOrder
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('TenantContext required.');
            }

            $fiscalYear = (string) Carbon::parse($dto->orderDate)->year;
            $orderNumber = $this->numberSeries->getNextNumber($dto->companyId, $dto->branchId, 'PO', $fiscalYear);

            $subtotalSum = BigDecimal::zero();
            $taxSum = BigDecimal::zero();
            $lineData = [];

            foreach ($dto->lines as $line) {
                $qty = BigDecimal::of($line->orderedQuantity);
                $price = BigDecimal::of($line->unitPrice);
                $lineSub = $qty->multipliedBy($price);
                $lineTax = BigDecimal::of($line->taxAmount);
                $lineTot = $lineSub->plus($lineTax);

                $subtotalSum = $subtotalSum->plus($lineSub);
                $taxSum = $taxSum->plus($lineTax);

                $lineData[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'company_id' => $dto->companyId,
                    'product_id' => $line->productId,
                    'variant_id' => $line->variantId,
                    'ordered_quantity' => (string) $qty->toScale(4, RoundingMode::HalfUp),
                    'received_quantity' => '0.0000',
                    'billed_quantity' => '0.0000',
                    'unit_price' => (string) $price->toScale(4, RoundingMode::HalfUp),
                    'subtotal' => (string) $lineSub->toScale(4, RoundingMode::HalfUp),
                    'tax_amount' => (string) $lineTax->toScale(4, RoundingMode::HalfUp),
                    'total_amount' => (string) $lineTot->toScale(4, RoundingMode::HalfUp),
                ];
            }

            $grandTotal = $subtotalSum->plus($taxSum);

            $purchaseOrder = PurchaseOrder::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'company_id' => $dto->companyId,
                'branch_id' => $dto->branchId,
                'supplier_id' => $dto->supplierId,
                'order_number' => $orderNumber,
                'order_date' => $dto->orderDate,
                'expected_delivery_date' => $dto->expectedDeliveryDate,
                'subtotal' => (string) $subtotalSum->toScale(4, RoundingMode::HalfUp),
                'tax_amount' => (string) $taxSum->toScale(4, RoundingMode::HalfUp),
                'grand_total' => (string) $grandTotal->toScale(4, RoundingMode::HalfUp),
                'status' => 'draft',
                'created_by' => $dto->createdBy,
            ]);

            foreach ($lineData as $ld) {
                $ld['purchase_order_id'] = $purchaseOrder->id;
                PurchaseOrderLine::create($ld);
            }

            return $purchaseOrder->load('lines');
        });
    }

    public function confirmPurchaseOrder(string $purchaseOrderId): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrderId) {
            $po = PurchaseOrder::with('lines')->findOrFail($purchaseOrderId);
            $po->status = 'confirmed';
            $po->save();

            return $po;
        });
    }

    public function createGoodsReceipt(CreateGoodsReceiptDTO $dto): GoodsReceipt
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('TenantContext required.');
            }

            $fiscalYear = (string) Carbon::parse($dto->receiptDate)->year;
            $grnNumber = $this->numberSeries->getNextNumber($dto->companyId, $dto->branchId, 'GRN', $fiscalYear);

            $grn = GoodsReceipt::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'company_id' => $dto->companyId,
                'branch_id' => $dto->branchId,
                'warehouse_id' => $dto->warehouseId,
                'supplier_id' => $dto->supplierId,
                'purchase_order_id' => $dto->purchaseOrderId,
                'grn_number' => $grnNumber,
                'receipt_date' => $dto->receiptDate,
                'challan_number' => $dto->challanNumber,
                'status' => 'draft',
                'created_by' => $dto->createdBy,
            ]);

            foreach ($dto->lines as $line) {
                GoodsReceiptLine::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'company_id' => $dto->companyId,
                    'goods_receipt_id' => $grn->id,
                    'purchase_order_line_id' => $line->purchaseOrderLineId,
                    'product_id' => $line->productId,
                    'variant_id' => $line->variantId,
                    'received_quantity' => (string) BigDecimal::of($line->receivedQuantity)->toScale(4, RoundingMode::HalfUp),
                    'billed_quantity' => '0.0000',
                    'unit_cost' => (string) BigDecimal::of($line->unitCost)->toScale(4, RoundingMode::HalfUp),
                    'batch_number' => $line->batchNumber,
                    'expiry_date' => $line->expiryDate,
                ]);
            }

            return $grn->load('lines');
        });
    }

    public function confirmGoodsReceipt(string $goodsReceiptId): GoodsReceipt
    {
        return DB::transaction(function () use ($goodsReceiptId) {
            /** @var GoodsReceipt $grn */
            $grn = GoodsReceipt::with(['lines', 'purchaseOrder.lines'])->findOrFail($goodsReceiptId);

            if ($grn->status === 'received') {
                return $grn;
            }

            foreach ($grn->lines as $gLine) {
                // If linked to PO line, validate delivery limit
                if ($gLine->purchase_order_line_id) {
                    /** @var PurchaseOrderLine|null $poLine */
                    $poLine = PurchaseOrderLine::lockForUpdate()->find($gLine->purchase_order_line_id);
                    if ($poLine) {
                        $newReceived = BigDecimal::of($poLine->received_quantity)->plus(BigDecimal::of($gLine->received_quantity));
                        $ordered = BigDecimal::of($poLine->ordered_quantity);

                        if ($newReceived->isGreaterThan($ordered)) {
                            throw new ReceivedQuantityExceedsOrderException(
                                "Received quantity {$gLine->received_quantity} exceeds remaining order quantity ({$ordered->minus($poLine->received_quantity)})."
                            );
                        }

                        $poLine->received_quantity = (string) $newReceived->toScale(4, RoundingMode::HalfUp);
                        $poLine->save();
                    }
                }

                // Inbound stock movement (positive quantity)
                $this->stockService->recordMovement(new StockMovementDTO(
                    companyId: $grn->company_id,
                    warehouseId: $grn->warehouse_id,
                    stockLocationId: null,
                    productVariantId: $gLine->variant_id,
                    movementType: 'GOODS_RECEIPT',
                    quantity: $gLine->received_quantity,
                    unitCost: $gLine->unit_cost,
                    referenceType: 'goods_receipts',
                    referenceId: $grn->id,
                    batchNumber: $gLine->batch_number,
                    expiryDate: $gLine->expiry_date?->toDateString(),
                    movementDate: $grn->receipt_date->toDateString()
                ));
            }

            $grn->status = 'received';
            $grn->save();

            // Refresh parent purchase order status
            if ($grn->purchase_order_id) {
                /** @var PurchaseOrder $po */
                $po = PurchaseOrder::with('lines')->findOrFail($grn->purchase_order_id);
                $allReceived = true;
                $anyReceived = false;

                foreach ($po->lines as $l) {
                    $rec = BigDecimal::of($l->received_quantity);
                    $ord = BigDecimal::of($l->ordered_quantity);
                    if ($rec->isLessThan($ord)) {
                        $allReceived = false;
                    }
                    if ($rec->isPositive()) {
                        $anyReceived = true;
                    }
                }

                if ($allReceived) {
                    $po->status = 'received';
                } elseif ($anyReceived) {
                    $po->status = 'partially_received';
                }
                $po->save();
            }

            return $grn->load('lines');
        });
    }

    public function createVendorBillFromReceipt(CreateVendorBillFromReceiptDTO $dto): SupplierBill
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('TenantContext required.');
            }

            /** @var GoodsReceipt $grn */
            $grn = GoodsReceipt::with(['lines', 'purchaseOrder.lines'])->findOrFail($dto->goodsReceiptId);

            $fiscalYear = (string) Carbon::parse($dto->billDate)->year;
            $billNumber = $this->numberSeries->getNextNumber($dto->companyId, $dto->branchId, 'BILL', $fiscalYear);

            $subtotalSum = BigDecimal::zero();
            $billLinesData = [];

            foreach ($grn->lines as $gLine) {
                $remQty = BigDecimal::of($gLine->received_quantity)->minus(BigDecimal::of($gLine->billed_quantity));
                if ($remQty->isLessThanOrEqualTo(BigDecimal::zero())) {
                    continue;
                }

                $unitPrice = $gLine->unit_cost;
                $poLineId = $gLine->purchase_order_line_id;

                if ($poLineId) {
                    $poLine = PurchaseOrderLine::lockForUpdate()->find($poLineId);
                    if ($poLine) {
                        $unitPrice = $poLine->unit_price;
                        $newBilled = BigDecimal::of($poLine->billed_quantity)->plus($remQty);
                        $ordered = BigDecimal::of($poLine->ordered_quantity);

                        if ($newBilled->isGreaterThan($ordered)) {
                            throw new BilledQuantityExceedsReceiptException('Billed quantity exceeds ordered quantity.');
                        }

                        $poLine->billed_quantity = (string) $newBilled->toScale(4, RoundingMode::HalfUp);
                        $poLine->save();
                    }
                }

                $price = BigDecimal::of($unitPrice);
                $lineSub = $remQty->multipliedBy($price);
                $subtotalSum = $subtotalSum->plus($lineSub);

                // Update GRN line billed quantity
                $gLine->billed_quantity = (string) BigDecimal::of($gLine->billed_quantity)->plus($remQty)->toScale(4, RoundingMode::HalfUp);
                $gLine->save();

                $billLinesData[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'company_id' => $dto->companyId,
                    'goods_receipt_line_id' => $gLine->id,
                    'purchase_order_line_id' => $gLine->purchase_order_line_id,
                    'product_id' => $gLine->product_id,
                    'variant_id' => $gLine->variant_id,
                    'quantity' => (string) $remQty->toScale(4, RoundingMode::HalfUp),
                    'unit_price' => (string) $price->toScale(4, RoundingMode::HalfUp),
                    'subtotal' => (string) $lineSub->toScale(4, RoundingMode::HalfUp),
                    'tax_amount' => '0.0000',
                    'total_amount' => (string) $lineSub->toScale(4, RoundingMode::HalfUp),
                ];
            }

            $grn->status = 'billed';
            $grn->save();

            $totalAmount = $subtotalSum;

            // Create SupplierBill in draft status so lines can be attached
            $bill = SupplierBill::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'company_id' => $dto->companyId,
                'branch_id' => $dto->branchId,
                'supplier_id' => $grn->supplier_id,
                'purchase_order_id' => $grn->purchase_order_id,
                'goods_receipt_id' => $grn->id,
                'bill_number' => $billNumber,
                'vendor_invoice_number' => $dto->vendorInvoiceNumber ?? $billNumber,
                'bill_date' => $dto->billDate,
                'due_date' => $dto->dueDate,
                'subtotal' => (string) $subtotalSum->toScale(4, RoundingMode::HalfUp),
                'tax_amount' => '0.0000',
                'total_amount' => (string) $totalAmount->toScale(4, RoundingMode::HalfUp),
                'status' => 'draft',
                'created_by' => $dto->createdBy,
            ]);

            foreach ($billLinesData as $bld) {
                $bld['supplier_bill_id'] = $bill->id;
                SupplierBillLine::create($bld);
            }

            // Transition to approved/posted
            $bill->status = 'posted';
            $bill->approved_by = $dto->createdBy;
            $bill->approved_at = Carbon::now();
            $bill->save();

            // Check if PurchaseOrder is fully received and billed -> transition to closed
            if ($grn->purchase_order_id) {
                /** @var PurchaseOrder $po */
                $po = PurchaseOrder::with('lines')->findOrFail($grn->purchase_order_id);
                $isClosed = true;

                foreach ($po->lines as $l) {
                    $rec = BigDecimal::of($l->received_quantity);
                    $bil = BigDecimal::of($l->billed_quantity);
                    $ord = BigDecimal::of($l->ordered_quantity);

                    if ($rec->isLessThan($ord) || $bil->isLessThan($ord)) {
                        $isClosed = false;
                        break;
                    }
                }

                if ($isClosed) {
                    $po->status = 'closed';
                    $po->save();
                }
            }

            // Post General Ledger Double-Entry Journal
            $expenseAccount = ChartOfAccount::where('tenant_id', $tenantId)
                ->where('company_id', $dto->companyId)
                ->where('account_code', '5010')
                ->firstOrFail();

            $apAccount = ChartOfAccount::where('tenant_id', $tenantId)
                ->where('company_id', $dto->companyId)
                ->where('account_code', '2010')
                ->firstOrFail();

            $periodId = $dto->periodId;
            if (! $periodId) {
                $period = AccountingPeriod::where('tenant_id', $tenantId)
                    ->where('company_id', $dto->companyId)
                    ->where('status', 'open')
                    ->where('start_date', '<=', $dto->billDate)
                    ->where('end_date', '>=', $dto->billDate)
                    ->firstOrFail();
                $periodId = $period->id;
            }

            $amountMoney = Money::of((string) $totalAmount->toScale(2, RoundingMode::HalfUp));

            $entry = $this->accountingService->post(new JournalEntryDTO(
                companyId: $dto->companyId,
                periodId: $periodId,
                documentType: 'purchase_bill',
                postingDate: $dto->billDate,
                businessDate: $dto->billDate,
                referenceType: 'supplier_bills',
                referenceId: $bill->id,
                postedBy: $dto->createdBy,
                branchId: $dto->branchId,
                narration: "Vendor Bill {$billNumber} for GRN {$grn->grn_number}",
                lines: [
                    new JournalLineDTO(
                        accountId: $expenseAccount->id,
                        debitAmount: $amountMoney,
                        creditAmount: Money::zero(),
                        taxCodeId: null,
                        branchId: $dto->branchId,
                        partnerId: $grn->supplier_id,
                        lineDescription: "Expense/Inventory - Bill {$billNumber}"
                    ),
                    new JournalLineDTO(
                        accountId: $apAccount->id,
                        debitAmount: Money::zero(),
                        creditAmount: $amountMoney,
                        taxCodeId: null,
                        branchId: $dto->branchId,
                        partnerId: $grn->supplier_id,
                        lineDescription: "Accounts Payable - Bill {$billNumber}"
                    ),
                ]
            ));

            $bill->gl_journal_entry_id = $entry->id;
            $bill->save();

            // Record Open Item in AP Subledger via SubledgerContract (ACC-06, NEW-16)
            $subledger = $this->subledger ?? app(SubledgerContract::class);
            $subledger->createPayable(
                tenantId: $tenantId,
                companyId: $dto->companyId,
                supplierId: $grn->supplier_id,
                billId: $bill->id,
                billNumber: $bill->bill_number,
                documentDate: $dto->billDate,
                dueDate: $dto->dueDate,
                amount: (string) $totalAmount->toScale(4, RoundingMode::HalfUp),
            );

            return $bill->load('lines');
        });
    }

    public function cancelPurchaseOrder(string $purchaseOrderId): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrderId) {
            $po = PurchaseOrder::with('lines')->findOrFail($purchaseOrderId);

            foreach ($po->lines as $l) {
                if (BigDecimal::of($l->received_quantity)->isPositive() || BigDecimal::of($l->billed_quantity)->isPositive()) {
                    throw new RuntimeException('Cannot cancel purchase order with existing receipts or bills. Issue debit notes or return challans instead.');
                }
            }

            $po->status = 'cancelled';
            $po->save();

            return $po;
        });
    }

    public function createSupplierQuotation(CreateSupplierQuotationDTO $dto): SupplierQuotation
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('TenantContext required.');
            }

            $subtotalSum = BigDecimal::zero();
            $taxSum = BigDecimal::zero();
            $lineData = [];

            foreach ($dto->lines as $line) {
                $qty = BigDecimal::of($line->requestedQuantity);
                $price = BigDecimal::of($line->quotedUnitPrice);
                $discount = BigDecimal::of($line->discountAmount);
                $taxRate = BigDecimal::of($line->taxRate);

                $lineSub = $qty->multipliedBy($price)->minus($discount);
                $lineTax = $lineSub->multipliedBy($taxRate);
                $lineTot = $lineSub->plus($lineTax);

                $subtotalSum = $subtotalSum->plus($lineSub);
                $taxSum = $taxSum->plus($lineTax);

                $lineData[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'company_id' => $dto->companyId,
                    'product_id' => $line->productId,
                    'variant_id' => $line->variantId,
                    'requested_quantity' => (string) $qty->toScale(4, RoundingMode::HalfUp),
                    'quoted_unit_price' => (string) $price->toScale(4, RoundingMode::HalfUp),
                    'discount_amount' => (string) $discount->toScale(4, RoundingMode::HalfUp),
                    'tax_rate' => (string) $taxRate->toScale(4, RoundingMode::HalfUp),
                    'tax_amount' => (string) $lineTax->toScale(4, RoundingMode::HalfUp),
                    'line_total' => (string) $lineTot->toScale(4, RoundingMode::HalfUp),
                    'lead_time_days' => $line->leadTimeDays,
                    'is_selected' => $line->isSelected,
                ];
            }

            $grandTotal = $subtotalSum->plus($taxSum);

            $quotation = SupplierQuotation::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'company_id' => $dto->companyId,
                'branch_id' => $dto->branchId,
                'supplier_id' => $dto->supplierId,
                'rfq_number' => $dto->rfqNumber,
                'supplier_quote_reference' => $dto->supplierQuoteReference,
                'quote_date' => $dto->quoteDate,
                'valid_until' => $dto->validUntil,
                'currency' => $dto->currency,
                'exchange_rate' => $dto->exchangeRate,
                'subtotal' => (string) $subtotalSum->toScale(4, RoundingMode::HalfUp),
                'tax_amount' => (string) $taxSum->toScale(4, RoundingMode::HalfUp),
                'grand_total' => (string) $grandTotal->toScale(4, RoundingMode::HalfUp),
                'status' => 'draft',
                'created_by' => $dto->createdBy,
            ]);

            foreach ($lineData as $ld) {
                $ld['supplier_quotation_id'] = $quotation->id;
                SupplierQuotationLine::create($ld);
            }

            return $quotation->load('lines');
        });
    }

    public function convertSupplierQuotationToPurchaseOrder(string $supplierQuotationId, string $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($supplierQuotationId, $userId) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('TenantContext required.');
            }

            /** @var SupplierQuotation $quote */
            $quote = SupplierQuotation::where('tenant_id', $tenantId)
                ->where('id', $supplierQuotationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($quote->status === 'converted_to_po' && $quote->converted_po_id) {
                throw new RuntimeException('Supplier quotation has already been converted to a purchase order.');
            }

            $fiscalYear = (string) Carbon::parse($quote->quote_date)->year;
            $orderNumber = $this->numberSeries->getNextNumber($quote->company_id, $quote->branch_id, 'PO', $fiscalYear);

            $maxLeadTime = 0;
            foreach ($quote->lines as $qLine) {
                if ($qLine->lead_time_days > $maxLeadTime) {
                    $maxLeadTime = $qLine->lead_time_days;
                }
            }

            $expectedDeliveryDate = Carbon::parse($quote->quote_date)->addDays(max(1, $maxLeadTime))->toDateString();

            $po = PurchaseOrder::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'company_id' => $quote->company_id,
                'branch_id' => $quote->branch_id,
                'supplier_id' => $quote->supplier_id,
                'order_number' => $orderNumber,
                'order_date' => $quote->quote_date,
                'expected_delivery_date' => $expectedDeliveryDate,
                'subtotal' => $quote->subtotal,
                'tax_amount' => $quote->tax_amount,
                'grand_total' => $quote->grand_total,
                'status' => 'draft',
                'created_by' => $userId,
            ]);

            foreach ($quote->lines as $qLine) {
                PurchaseOrderLine::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'company_id' => $quote->company_id,
                    'purchase_order_id' => $po->id,
                    'product_id' => $qLine->product_id,
                    'variant_id' => $qLine->variant_id,
                    'ordered_quantity' => $qLine->requested_quantity,
                    'received_quantity' => '0.0000',
                    'billed_quantity' => '0.0000',
                    'unit_price' => $qLine->quoted_unit_price,
                    'subtotal' => $qLine->line_total,
                    'tax_amount' => $qLine->tax_amount,
                    'total_amount' => $qLine->line_total,
                ]);
            }

            $quote->converted_po_id = $po->id;
            $quote->status = 'converted_to_po';
            $quote->save();

            return $po->load('lines');
        });
    }
}
