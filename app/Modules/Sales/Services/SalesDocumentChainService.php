<?php

namespace App\Modules\Sales\Services;

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanLine;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\SubledgerOpenItem;
use App\Modules\Accounting\Contracts\AccountingPostingServiceContract;
use App\Modules\Accounting\Contracts\NumberSeriesContract;
use App\Modules\Accounting\DTOs\JournalEntryDTO;
use App\Modules\Accounting\DTOs\JournalLineDTO;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\DTOs\StockMovementDTO;
use App\Modules\Sales\Contracts\SalesDocumentChainContract;
use App\Modules\Sales\DTOs\CreateDeliveryChallanDTO;
use App\Modules\Sales\DTOs\CreateInvoiceFromDeliveryDTO;
use App\Modules\Sales\DTOs\CreateQuotationDTO;
use App\Modules\Sales\DTOs\CreateSalesOrderDTO;
use App\Modules\Sales\Exceptions\DeliveredQuantityExceedsOrderException;
use App\Modules\Sales\Exceptions\InvoicedQuantityExceedsChallanException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class SalesDocumentChainService implements SalesDocumentChainContract
{
    public function __construct(
        protected NumberSeriesContract $numberSeries,
        protected StockMovementServiceContract $stockService,
        protected AccountingPostingServiceContract $accountingService,
    ) {}

    public function createQuotation(CreateQuotationDTO $dto): Quotation
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('TenantContext required.');
            }

            $fiscalYear = (string) Carbon::parse($dto->quotationDate)->year;
            $quotationNumber = $this->numberSeries->getNextNumber($dto->companyId, $dto->branchId, 'QT', $fiscalYear);

            $subtotalSum = BigDecimal::zero();
            $taxSum = BigDecimal::zero();
            $lineData = [];

            foreach ($dto->lines as $line) {
                $qty = BigDecimal::of($line->quantity);
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
                    'quantity' => (string) $qty->toScale(4, RoundingMode::HalfUp),
                    'unit_price' => (string) $price->toScale(4, RoundingMode::HalfUp),
                    'subtotal' => (string) $lineSub->toScale(4, RoundingMode::HalfUp),
                    'tax_amount' => (string) $lineTax->toScale(4, RoundingMode::HalfUp),
                    'total_amount' => (string) $lineTot->toScale(4, RoundingMode::HalfUp),
                    'notes' => $line->notes,
                ];
            }

            $grandTotal = $subtotalSum->plus($taxSum);

            $quotation = Quotation::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'company_id' => $dto->companyId,
                'branch_id' => $dto->branchId,
                'customer_id' => $dto->customerId,
                'quotation_number' => $quotationNumber,
                'quotation_date' => $dto->quotationDate,
                'valid_until' => $dto->validUntil,
                'subtotal' => (string) $subtotalSum->toScale(4, RoundingMode::HalfUp),
                'tax_amount' => (string) $taxSum->toScale(4, RoundingMode::HalfUp),
                'grand_total' => (string) $grandTotal->toScale(4, RoundingMode::HalfUp),
                'status' => 'draft',
                'created_by' => $dto->createdBy,
            ]);

            foreach ($lineData as $ld) {
                $ld['quotation_id'] = $quotation->id;
                QuotationLine::create($ld);
            }

            return $quotation->load('lines');
        });
    }

    public function convertQuotationToSalesOrder(string $quotationId, CreateSalesOrderDTO $dto): SalesOrder
    {
        return DB::transaction(function () use ($quotationId, $dto) {
            $quotation = Quotation::findOrFail($quotationId);
            $quotation->status = 'converted';
            $quotation->save();

            return $this->createSalesOrder($dto);
        });
    }

    public function createSalesOrder(CreateSalesOrderDTO $dto): SalesOrder
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('TenantContext required.');
            }

            $fiscalYear = (string) Carbon::parse($dto->orderDate)->year;
            $orderNumber = $this->numberSeries->getNextNumber($dto->companyId, $dto->branchId, 'SO', $fiscalYear);

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
                    'quotation_line_id' => $line->quotationLineId,
                    'product_id' => $line->productId,
                    'variant_id' => $line->variantId,
                    'ordered_quantity' => (string) $qty->toScale(4, RoundingMode::HalfUp),
                    'delivered_quantity' => '0.0000',
                    'invoiced_quantity' => '0.0000',
                    'unit_price' => (string) $price->toScale(4, RoundingMode::HalfUp),
                    'subtotal' => (string) $lineSub->toScale(4, RoundingMode::HalfUp),
                    'tax_amount' => (string) $lineTax->toScale(4, RoundingMode::HalfUp),
                    'total_amount' => (string) $lineTot->toScale(4, RoundingMode::HalfUp),
                ];
            }

            $grandTotal = $subtotalSum->plus($taxSum);

            $salesOrder = SalesOrder::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'company_id' => $dto->companyId,
                'branch_id' => $dto->branchId,
                'customer_id' => $dto->customerId,
                'quotation_id' => $dto->quotationId,
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
                $ld['sales_order_id'] = $salesOrder->id;
                SalesOrderLine::create($ld);
            }

            return $salesOrder->load('lines');
        });
    }

    public function confirmSalesOrder(string $salesOrderId): SalesOrder
    {
        return DB::transaction(function () use ($salesOrderId) {
            $order = SalesOrder::with('lines')->findOrFail($salesOrderId);
            $order->status = 'confirmed';
            $order->save();

            return $order;
        });
    }

    public function createDeliveryChallan(CreateDeliveryChallanDTO $dto): DeliveryChallan
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('TenantContext required.');
            }

            $fiscalYear = (string) Carbon::parse($dto->challanDate)->year;
            $challanNumber = $this->numberSeries->getNextNumber($dto->companyId, $dto->branchId, 'DC', $fiscalYear);

            $challan = DeliveryChallan::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'company_id' => $dto->companyId,
                'branch_id' => $dto->branchId,
                'warehouse_id' => $dto->warehouseId,
                'customer_id' => $dto->customerId,
                'sales_order_id' => $dto->salesOrderId,
                'challan_number' => $challanNumber,
                'challan_date' => $dto->challanDate,
                'status' => 'draft',
                'vehicle_number' => $dto->vehicleNumber,
                'driver_name' => $dto->driverName,
                'driver_phone' => $dto->driverPhone,
                'created_by' => $dto->createdBy,
            ]);

            foreach ($dto->lines as $line) {
                DeliveryChallanLine::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'company_id' => $dto->companyId,
                    'delivery_challan_id' => $challan->id,
                    'sales_order_line_id' => $line->salesOrderLineId,
                    'product_id' => $line->productId,
                    'variant_id' => $line->variantId,
                    'quantity' => (string) BigDecimal::of($line->quantity)->toScale(4, RoundingMode::HalfUp),
                    'invoiced_quantity' => '0.0000',
                    'batch_number' => $line->batchNumber,
                    'notes' => $line->notes,
                ]);
            }

            return $challan->load('lines');
        });
    }

    public function confirmDeliveryChallan(string $challanId): DeliveryChallan
    {
        return DB::transaction(function () use ($challanId) {
            /** @var DeliveryChallan $challan */
            $challan = DeliveryChallan::with(['lines', 'salesOrder.lines'])->findOrFail($challanId);

            if ($challan->status === 'confirmed') {
                return $challan;
            }

            foreach ($challan->lines as $challanLine) {
                // If linked to a sales order line, validate delivery limit and update delivered quantity
                if ($challanLine->sales_order_line_id) {
                    /** @var SalesOrderLine|null $soLine */
                    $soLine = SalesOrderLine::lockForUpdate()->find($challanLine->sales_order_line_id);
                    if ($soLine) {
                        $newDelivered = BigDecimal::of($soLine->delivered_quantity)->plus(BigDecimal::of($challanLine->quantity));
                        $ordered = BigDecimal::of($soLine->ordered_quantity);

                        if ($newDelivered->isGreaterThan($ordered)) {
                            throw new DeliveredQuantityExceedsOrderException(
                                "Challan quantity {$challanLine->quantity} exceeds remaining unfulfilled order quantity ({$ordered->minus($soLine->delivered_quantity)})."
                            );
                        }

                        $soLine->delivered_quantity = (string) $newDelivered->toScale(4, RoundingMode::HalfUp);
                        $soLine->save();
                    }
                }

                // Disptach stock movement (outbound = negative quantity)
                $variant = ProductVariant::find($challanLine->variant_id);
                $unitCost = $variant ? $variant->cost_price : '0.0000';

                $this->stockService->recordMovement(new StockMovementDTO(
                    companyId: $challan->company_id,
                    warehouseId: $challan->warehouse_id,
                    stockLocationId: null,
                    productVariantId: $challanLine->variant_id,
                    movementType: 'DELIVERY_CHALLAN',
                    quantity: '-' . $challanLine->quantity,
                    unitCost: (string) $unitCost,
                    referenceType: 'delivery_challans',
                    referenceId: $challan->id,
                    batchNumber: $challanLine->batch_number,
                    movementDate: $challan->challan_date->toDateString()
                ));
            }

            $challan->status = 'confirmed';
            $challan->save();

            // Refresh parent sales order status
            if ($challan->sales_order_id) {
                /** @var SalesOrder $so */
                $so = SalesOrder::with('lines')->findOrFail($challan->sales_order_id);
                $allDelivered = true;
                $anyDelivered = false;

                foreach ($so->lines as $l) {
                    $del = BigDecimal::of($l->delivered_quantity);
                    $ord = BigDecimal::of($l->ordered_quantity);
                    if ($del->isLessThan($ord)) {
                        $allDelivered = false;
                    }
                    if ($del->isPositive()) {
                        $anyDelivered = true;
                    }
                }

                if ($allDelivered) {
                    $so->status = 'delivered';
                } elseif ($anyDelivered) {
                    $so->status = 'partially_delivered';
                }
                $so->save();
            }

            return $challan->load('lines');
        });
    }

    public function createInvoiceFromDelivery(CreateInvoiceFromDeliveryDTO $dto): SalesInvoice
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('TenantContext required.');
            }

            /** @var DeliveryChallan $challan */
            $challan = DeliveryChallan::with(['lines', 'salesOrder.lines'])->findOrFail($dto->deliveryChallanId);

            $fiscalYear = (string) Carbon::parse($dto->invoiceDate)->year;
            $invoiceNumber = $this->numberSeries->getNextNumber($dto->companyId, $dto->branchId, 'INV', $fiscalYear);

            $subtotalSum = BigDecimal::zero();
            $invoiceLinesData = [];

            foreach ($challan->lines as $cLine) {
                $remQty = BigDecimal::of($cLine->quantity)->minus(BigDecimal::of($cLine->invoiced_quantity));
                if ($remQty->isLessThanOrEqualTo(BigDecimal::zero())) {
                    continue;
                }

                // Determine unit price and tax code from sales order line or product variant
                $unitPrice = '0.0000';
                $variant = ProductVariant::findOrFail($cLine->variant_id);
                $taxCodeId = $variant->tax_code_id;

                if ($cLine->sales_order_line_id) {
                    $soLine = SalesOrderLine::lockForUpdate()->find($cLine->sales_order_line_id);
                    if ($soLine) {
                        $unitPrice = $soLine->unit_price;
                        $newInvoiced = BigDecimal::of($soLine->invoiced_quantity)->plus($remQty);
                        $ordered = BigDecimal::of($soLine->ordered_quantity);

                        if ($newInvoiced->isGreaterThan($ordered)) {
                            throw new InvoicedQuantityExceedsChallanException("Invoiced quantity exceeds ordered quantity.");
                        }

                        $soLine->invoiced_quantity = (string) $newInvoiced->toScale(4, RoundingMode::HalfUp);
                        $soLine->save();
                    }
                } else {
                    $unitPrice = (string) $variant->mrp;
                }

                $price = BigDecimal::of($unitPrice);
                $lineSub = $remQty->multipliedBy($price);
                $subtotalSum = $subtotalSum->plus($lineSub);

                // Update challan line invoiced quantity
                $cLine->invoiced_quantity = (string) BigDecimal::of($cLine->invoiced_quantity)->plus($remQty)->toScale(4, RoundingMode::HalfUp);
                $cLine->save();

                $invoiceLinesData[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'company_id' => $dto->companyId,
                    'sales_order_line_id' => $cLine->sales_order_line_id,
                    'delivery_challan_line_id' => $cLine->id,
                    'product_variant_id' => $cLine->variant_id,
                    'item_description' => $variant->name_en ?? 'Product',
                    'uom' => $variant->base_uom ?? 'Pcs',
                    'batch_number' => $cLine->batch_number,
                    'quantity' => (string) $remQty->toScale(4, RoundingMode::HalfUp),
                    'unit_price' => (string) $price->toScale(4, RoundingMode::HalfUp),
                    'pricing_basis' => 'tax_inclusive',
                    'line_discount_amount' => '0.0000',
                    'tax_code_id' => $taxCodeId,
                    'vat_rate' => '0.00',
                    'supplementary_duty_rate' => '0.00',
                    'supplementary_duty_amount' => '0.0000',
                    'tax_amount' => '0.0000',
                    'line_total' => (string) $lineSub->toScale(4, RoundingMode::HalfUp),
                ];
            }

            $challan->status = 'invoiced';
            $challan->save();

            $totalPayable = $subtotalSum;

            // Create SalesInvoice
            $invoice = SalesInvoice::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'company_id' => $dto->companyId,
                'branch_id' => $dto->branchId,
                'customer_id' => $challan->customer_id,
                'sales_order_id' => $challan->sales_order_id,
                'delivery_challan_id' => $challan->id,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $dto->invoiceDate,
                'business_date' => $dto->invoiceDate,
                'status' => 'issued',
                'subtotal' => (string) $subtotalSum->toScale(4, RoundingMode::HalfUp),
                'discount_amount' => '0.0000',
                'supplementary_duty_amount' => '0.0000',
                'tax_amount' => '0.0000',
                'total_payable' => (string) $totalPayable->toScale(4, RoundingMode::HalfUp),
                'payment_status' => 'unpaid',
            ]);

            foreach ($invoiceLinesData as $ild) {
                $ild['sales_invoice_id'] = $invoice->id;
                SalesInvoiceLine::create($ild);
            }

            // Check if SalesOrder is fully delivered and invoiced -> transition to closed
            if ($challan->sales_order_id) {
                /** @var SalesOrder $so */
                $so = SalesOrder::with('lines')->findOrFail($challan->sales_order_id);
                $isClosed = true;

                foreach ($so->lines as $l) {
                    $del = BigDecimal::of($l->delivered_quantity);
                    $inv = BigDecimal::of($l->invoiced_quantity);
                    $ord = BigDecimal::of($l->ordered_quantity);

                    if ($del->isLessThan($ord) || $inv->isLessThan($ord)) {
                        $isClosed = false;
                        break;
                    }
                }

                if ($isClosed) {
                    $so->status = 'closed';
                    $so->save();
                }
            }

            // Post General Ledger Double-Entry Journal
            $arAccount = ChartOfAccount::where('tenant_id', $tenantId)
                ->where('company_id', $dto->companyId)
                ->where('account_code', '1030')
                ->firstOrFail();

            $revAccount = ChartOfAccount::where('tenant_id', $tenantId)
                ->where('company_id', $dto->companyId)
                ->where('account_code', '4010')
                ->firstOrFail();

            $periodId = $dto->periodId;
            if (! $periodId) {
                $period = AccountingPeriod::where('tenant_id', $tenantId)
                    ->where('company_id', $dto->companyId)
                    ->where('status', 'open')
                    ->where('start_date', '<=', $dto->invoiceDate)
                    ->where('end_date', '>=', $dto->invoiceDate)
                    ->firstOrFail();
                $periodId = $period->id;
            }

            $amountMoney = Money::of((string) $totalPayable->toScale(2, RoundingMode::HalfUp));

            $this->accountingService->post(new JournalEntryDTO(
                companyId: $dto->companyId,
                periodId: $periodId,
                documentType: 'sales_invoice',
                postingDate: $dto->invoiceDate,
                businessDate: $dto->invoiceDate,
                referenceType: 'sales_invoices',
                referenceId: $invoice->id,
                postedBy: $dto->createdBy,
                branchId: $dto->branchId,
                narration: "Invoice {$invoiceNumber} for Challan {$challan->challan_number}",
                lines: [
                    new JournalLineDTO(
                        accountId: $arAccount->id,
                        debitAmount: $amountMoney,
                        creditAmount: Money::zero(),
                        taxCodeId: null,
                        branchId: $dto->branchId,
                        partnerId: $challan->customer_id,
                        lineDescription: "Accounts Receivable - Invoice {$invoiceNumber}"
                    ),
                    new JournalLineDTO(
                        accountId: $revAccount->id,
                        debitAmount: Money::zero(),
                        creditAmount: $amountMoney,
                        taxCodeId: null,
                        branchId: $dto->branchId,
                        partnerId: $challan->customer_id,
                        lineDescription: "Revenue - Invoice {$invoiceNumber}"
                    ),
                ]
            ));

            // Record Open Item in AR Subledger
            $dueDate = $dto->dueDate ?? Carbon::parse($dto->invoiceDate)->addDays(30)->toDateString();

            SubledgerOpenItem::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'company_id' => $dto->companyId,
                'party_type' => 'customer',
                'party_id' => $challan->customer_id,
                'source_type' => 'sales_invoice',
                'source_id' => $invoice->id,
                'document_number' => $invoice->invoice_number,
                'document_date' => $dto->invoiceDate,
                'due_date' => $dueDate,
                'direction' => 'debit',
                'gl_control_account_code' => '1030',
                'original_amount' => (string) $totalPayable->toScale(4, RoundingMode::HalfUp),
                'allocated_amount' => '0.0000',
                'remaining_amount' => (string) $totalPayable->toScale(4, RoundingMode::HalfUp),
                'status' => 'open',
            ]);

            return $invoice->load('lines');
        });
    }

    public function cancelSalesOrder(string $salesOrderId): SalesOrder
    {
        return DB::transaction(function () use ($salesOrderId) {
            $so = SalesOrder::with('lines')->findOrFail($salesOrderId);

            foreach ($so->lines as $l) {
                if (BigDecimal::of($l->delivered_quantity)->isPositive() || BigDecimal::of($l->invoiced_quantity)->isPositive()) {
                    throw new RuntimeException("Cannot cancel sales order with existing deliveries or invoices. Issue returns or credit notes instead.");
                }
            }

            $so->status = 'cancelled';
            $so->save();

            return $so;
        });
    }
}
