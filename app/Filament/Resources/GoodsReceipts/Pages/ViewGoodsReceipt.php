<?php

namespace App\Filament\Resources\GoodsReceipts\Pages;

use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Models\GoodsReceipt;
use App\Modules\Procurement\Contracts\ProcurementDocumentChainContract;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewGoodsReceipt extends ViewRecord
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirm_grn')
                ->label('Confirm & Post Stock / গ্রহণ ও স্টক নিশ্চিত করুন')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Post Goods Receipt to Inventory')
                ->modalDescription('This will permanently increment warehouse stock and update weighted average cost. This action cannot be undone.')
                ->visible(fn (GoodsReceipt $record): bool => $record->status === 'draft')
                ->action(function (GoodsReceipt $record, ProcurementDocumentChainContract $service) {
                    $service->confirmGoodsReceipt($record->id);
                    Notification::make()->title('Goods Receipt Posted: Stock Successfully Updated')->success()->send();
                }),
        ];
    }
}
