<?php

namespace App\Filament\Widgets;

use App\Models\SupplierQuotation;
use Brick\Math\BigDecimal;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class SupplierQuotationComparisonWidget extends Widget
{
    protected string $view = 'filament.widgets.supplier-quotation-comparison-widget';

    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    public function getComparisonData(): array
    {
        if (! $this->record instanceof SupplierQuotation) {
            return [
                'has_bids' => false,
                'rfq_number' => '',
                'bids' => [],
                'variant_comparison' => [],
            ];
        }

        $rfqNumber = $this->record->rfq_number;
        $quotations = SupplierQuotation::where('tenant_id', $this->record->tenant_id)
            ->where('company_id', $this->record->company_id)
            ->where('rfq_number', $rfqNumber)
            ->with(['supplier', 'lines.product', 'lines.variant'])
            ->get();

        if ($quotations->isEmpty()) {
            return [
                'has_bids' => false,
                'rfq_number' => $rfqNumber,
                'bids' => [],
                'variant_comparison' => [],
            ];
        }

        $bids = [];
        $variantMap = [];

        foreach ($quotations as $quote) {
            $bids[] = [
                'id' => $quote->id,
                'supplier_name' => $quote->supplier->name,
                'quote_reference' => (string) ($quote->supplier_quote_reference ?? 'N/A'),
                'quote_date' => $quote->quote_date->toDateString(),
                'grand_total' => $quote->grand_total,
                'status' => $quote->status,
                'is_current' => $quote->id === $this->record->id,
            ];

            foreach ($quote->lines as $line) {
                $variantId = $line->variant_id;
                if (! isset($variantMap[$variantId])) {
                    $variantMap[$variantId] = [
                        'variant_name' => $line->variant->name_en,
                        'sku' => $line->variant->sku,
                        'quotes' => [],
                        'lowest_price' => null,
                        'lowest_supplier_id' => null,
                    ];
                }

                $price = BigDecimal::of($line->quoted_unit_price);
                $variantMap[$variantId]['quotes'][$quote->id] = [
                    'price' => (string) $price,
                    'lead_time_days' => $line->lead_time_days,
                ];

                $currentLowest = $variantMap[$variantId]['lowest_price'];
                if ($currentLowest === null || $price->isLessThan(BigDecimal::of($currentLowest))) {
                    $variantMap[$variantId]['lowest_price'] = (string) $price;
                    $variantMap[$variantId]['lowest_supplier_id'] = $quote->id;
                }
            }
        }

        return [
            'has_bids' => true,
            'rfq_number' => $rfqNumber,
            'bids' => $bids,
            'variant_comparison' => array_values($variantMap),
        ];
    }
}
