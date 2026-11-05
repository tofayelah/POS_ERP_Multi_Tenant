<?php

namespace App\Filament\Resources\SupplierQuotations\Pages;

use App\Filament\Resources\SupplierQuotations\SupplierQuotationResource;
use App\Filament\Widgets\SupplierQuotationComparisonWidget;
use App\Models\SupplierQuotation;
use App\Modules\Procurement\Contracts\ProcurementDocumentChainContract;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ViewSupplierQuotation extends ViewRecord
{
    protected static string $resource = SupplierQuotationResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            SupplierQuotationComparisonWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (SupplierQuotation $record): bool => in_array($record->status, ['draft', 'sent', 'evaluated'], true)),

            Action::make('award_bid')
                ->label('Award Bid / দরপত্র অনুমোদন')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (SupplierQuotation $record): bool => in_array($record->status, ['draft', 'sent', 'evaluated'], true))
                ->action(function (SupplierQuotation $record) {
                    $record->status = 'awarded';
                    $record->awarded_at = now();
                    $record->save();

                    Notification::make()
                        ->title('Supplier Quotation Awarded')
                        ->success()
                        ->send();
                }),

            Action::make('convert_to_purchase_order')
                ->label('Convert to PO / ক্রয় আদেশে রূপান্তর')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('primary')
                ->visible(fn (SupplierQuotation $record): bool => in_array($record->status, ['awarded', 'evaluated'], true))
                ->action(function (SupplierQuotation $record) {
                    /** @var ProcurementDocumentChainContract $service */
                    $service = app(ProcurementDocumentChainContract::class);
                    $po = $service->convertSupplierQuotationToPurchaseOrder($record->id, (string) (Auth::id() ?? Str::uuid()));

                    Notification::make()
                        ->title('Purchase Order Created')
                        ->body("Converted to PO {$po->order_number}")
                        ->success()
                        ->send();
                }),
        ];
    }
}
