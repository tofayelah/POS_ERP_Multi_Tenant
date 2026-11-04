<?php

namespace App\Filament\Widgets;

use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrderLine;
use App\Models\SupplierBill;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Filament\Widgets\Widget;

class ThreeWayMatchingWidget extends Widget
{
    protected static ?bool $isLazyLoaded = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.three-way-matching-widget';

    public ?SupplierBill $record = null;

    /**
     * Reconcile line items across PO, GRN, and Supplier Bill.
     *
     * @return array{
     *   status: 'MATCHED'|'EXCEPTION'|'UNLINKED',
     *   total_po_expected: string,
     *   total_billed: string,
     *   net_ppv: string,
     *   has_exceptions: bool,
     *   exceptions: array<int, string>,
     *   lines: array<int, array<string, mixed>>
     * }
     */
    public function getReconciliationData(): array
    {
        if (! $this->record) {
            return [
                'status' => 'UNLINKED',
                'total_po_expected' => '0.0000',
                'total_billed' => '0.0000',
                'net_ppv' => '0.0000',
                'has_exceptions' => true,
                'exceptions' => ['No supplier bill record provided.'],
                'lines' => [],
            ];
        }

        if (! $this->record->goods_receipt_id && ! $this->record->purchase_order_id) {
            return [
                'status' => 'UNLINKED',
                'total_po_expected' => '0.0000',
                'total_billed' => (string) $this->record->total_amount,
                'net_ppv' => '0.0000',
                'has_exceptions' => true,
                'exceptions' => ['Bill is not linked to any Goods Receipt or Purchase Order.'],
                'lines' => [],
            ];
        }

        /** @var SupplierBill $bill */
        $bill = $this->record->loadMissing([
            'lines.product',
            'lines.variant',
            'goodsReceipt.lines',
            'purchaseOrder.lines',
        ]);

        $grnLines = $bill->goodsReceipt?->lines->keyBy('id') ?? collect();
        $poLines = $bill->purchaseOrder?->lines->keyBy('id') ?? collect();

        // Also build variant lookup index if line IDs are not direct
        $grnByVariant = $bill->goodsReceipt?->lines->keyBy('variant_id') ?? collect();
        $poByVariant = $bill->purchaseOrder?->lines->keyBy('variant_id') ?? collect();

        $lines = [];
        $netPpv = BigDecimal::zero();
        $expectedTotal = BigDecimal::zero();
        $allExceptions = [];
        $hasExceptions = false;

        foreach ($bill->lines as $bLine) {
            /** @var GoodsReceiptLine|null $grnLine */
            $grnLine = $bLine->goods_receipt_line_id
                ? $grnLines->get($bLine->goods_receipt_line_id)
                : $grnByVariant->get($bLine->variant_id);

            /** @var PurchaseOrderLine|null $poLine */
            $poLine = $bLine->purchase_order_line_id
                ? $poLines->get($bLine->purchase_order_line_id)
                : ($grnLine?->purchase_order_line_id ? $poLines->get($grnLine->purchase_order_line_id) : $poByVariant->get($bLine->variant_id));

            $billQty = BigDecimal::of($bLine->quantity);
            $billPrice = BigDecimal::of($bLine->unit_price);
            $grnQty = $grnLine ? BigDecimal::of($grnLine->received_quantity) : BigDecimal::zero();
            $poQty = $poLine ? BigDecimal::of($poLine->ordered_quantity) : BigDecimal::zero();
            $poPrice = $poLine ? BigDecimal::of($poLine->unit_price) : $billPrice;

            $lineExpected = $billQty->multipliedBy($poPrice);
            $expectedTotal = $expectedTotal->plus($lineExpected);

            // PPV = (Bill Price - PO Price) * Bill Qty
            $linePpv = $billPrice->minus($poPrice)->multipliedBy($billQty);
            $netPpv = $netPpv->plus($linePpv);

            $lineExceptions = [];

            // Rule 1: Billed quantity must never exceed GRN received quantity
            if ($billQty->isGreaterThan($grnQty)) {
                $lineExceptions[] = "Over-billing: Billed quantity ({$billQty}) exceeds GRN received quantity ({$grnQty}).";
            }

            // Rule 2: Purchase Price Variance detection
            if ($billPrice->isGreaterThan($poPrice)) {
                $lineExceptions[] = "Price Variance: Billed unit price ({$billPrice}) exceeds PO agreed price ({$poPrice}).";
            }

            // Rule 3: Billed quantity exceeding PO ordered quantity
            if ($poLine && $billQty->isGreaterThan($poQty)) {
                $lineExceptions[] = "Order Exceeded: Billed quantity ({$billQty}) exceeds original PO ordered quantity ({$poQty}).";
            }

            if (! empty($lineExceptions)) {
                $hasExceptions = true;
                foreach ($lineExceptions as $ex) {
                    $allExceptions[] = "[{$bLine->product->name_en}] {$ex}";
                }
            }

            $lines[] = [
                'product_name' => $bLine->product->name_en,
                'sku' => $bLine->variant->sku,
                'po_qty' => (string) $poQty->toScale(4, RoundingMode::HalfUp),
                'grn_qty' => (string) $grnQty->toScale(4, RoundingMode::HalfUp),
                'bill_qty' => (string) $billQty->toScale(4, RoundingMode::HalfUp),
                'po_price' => (string) $poPrice->toScale(4, RoundingMode::HalfUp),
                'bill_price' => (string) $billPrice->toScale(4, RoundingMode::HalfUp),
                'ppv' => (string) $linePpv->toScale(4, RoundingMode::HalfUp),
                'status' => empty($lineExceptions) ? 'MATCHED' : 'EXCEPTION',
                'exceptions' => $lineExceptions,
            ];
        }

        return [
            'status' => $hasExceptions ? 'EXCEPTION' : 'MATCHED',
            'total_po_expected' => (string) $expectedTotal->toScale(4, RoundingMode::HalfUp),
            'total_billed' => (string) $bill->total_amount,
            'net_ppv' => (string) $netPpv->toScale(4, RoundingMode::HalfUp),
            'has_exceptions' => $hasExceptions,
            'exceptions' => $allExceptions,
            'lines' => $lines,
        ];
    }
}
