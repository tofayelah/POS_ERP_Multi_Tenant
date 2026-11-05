<?php

namespace App\Filament\Resources\SupplierQuotations\Pages;

use App\Filament\Resources\SupplierQuotations\SupplierQuotationResource;
use App\Models\SupplierQuotation;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSupplierQuotation extends EditRecord
{
    protected static string $resource = SupplierQuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var SupplierQuotation $record */
        $record = $this->getRecord();
        $record->load('lines');

        $data['lines'] = $record->lines->map(function ($line) {
            return [
                'product_id' => $line->product_id,
                'variant_id' => $line->variant_id,
                'requested_quantity' => $line->requested_quantity,
                'quoted_unit_price' => $line->quoted_unit_price,
                'discount_amount' => $line->discount_amount,
                'tax_rate' => $line->tax_rate,
                'tax_amount' => $line->tax_amount,
                'lead_time_days' => $line->lead_time_days,
                'line_total' => $line->line_total,
                'is_selected' => $line->is_selected,
            ];
        })->toArray();

        return $data;
    }
}
