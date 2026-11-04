<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Modules\Procurement\Contracts\ProcurementDocumentChainContract;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['lines'] = $this->record->lines->toArray();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (PurchaseOrder $record): bool => $record->status === 'draft'),

            Action::make('confirm')
                ->label('Confirm Order')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (PurchaseOrder $record): bool => $record->status === 'draft')
                ->action(function (PurchaseOrder $record, ProcurementDocumentChainContract $service) {
                    $service->confirmPurchaseOrder($record->id);
                    Notification::make()->title('Purchase Order Confirmed')->success()->send();
                }),

            Action::make('cancel')
                ->label('Cancel Order')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(function (PurchaseOrder $record): bool {
                    if (! in_array($record->status, ['draft', 'confirmed'], true)) {
                        return false;
                    }

                    return ! $record->lines()->where('received_quantity', '>', 0)->exists();
                })
                ->action(function (PurchaseOrder $record, ProcurementDocumentChainContract $service) {
                    $service->cancelPurchaseOrder($record->id);
                    Notification::make()->title('Purchase Order Cancelled')->warning()->send();
                }),
        ];
    }
}
