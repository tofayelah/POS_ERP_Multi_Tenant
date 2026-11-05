<?php

namespace App\Filament\Resources\LandedCosts\Pages;

use App\Filament\Resources\LandedCosts\LandedCostVoucherResource;
use App\Models\LandedCostVoucher;
use App\Modules\Inventory\Contracts\LandedCostAllocationContract;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewLandedCostVoucher extends ViewRecord
{
    protected static string $resource = LandedCostVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('post')
                ->label('Post / পোস্টিং')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Post Landed Cost Voucher')
                ->modalDescription('Posting this voucher will capitalize landed costs into inventory on-hand balances (AVCO), expense any sold portion to COGS, and generate a General Ledger journal entry. This action cannot be undone.')
                ->visible(function (): bool {
                    /** @var LandedCostVoucher $record */
                    $record = $this->getRecord();

                    return $record->status === 'draft';
                })
                ->action(function () {
                    /** @var LandedCostVoucher $record */
                    $record = $this->getRecord();
                    /** @var LandedCostAllocationContract $service */
                    $service = app(LandedCostAllocationContract::class);
                    $userId = (string) (Auth::id() ?? $record->created_by ?? 'system');

                    $service->postVoucher($record->id, $userId);

                    Notification::make()
                        ->title('Landed Cost Voucher Posted')
                        ->body("Voucher {$record->voucher_number} successfully posted to inventory and general ledger.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'posted_at', 'posted_by', 'gl_journal_entry_id']);
                }),

            Action::make('cancel')
                ->label('Cancel / বাতিল')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(function (): bool {
                    /** @var LandedCostVoucher $record */
                    $record = $this->getRecord();

                    return $record->status === 'draft';
                })
                ->action(function () {
                    /** @var LandedCostVoucher $record */
                    $record = $this->getRecord();
                    /** @var LandedCostAllocationContract $service */
                    $service = app(LandedCostAllocationContract::class);
                    $userId = (string) (Auth::id() ?? $record->created_by ?? 'system');

                    $service->cancelVoucher($record->id, $userId);

                    Notification::make()
                        ->title('Landed Cost Voucher Cancelled')
                        ->body("Voucher {$record->voucher_number} has been cancelled.")
                        ->warning()
                        ->send();

                    $this->refreshFormData(['status', 'notes']);
                }),
        ];
    }
}
