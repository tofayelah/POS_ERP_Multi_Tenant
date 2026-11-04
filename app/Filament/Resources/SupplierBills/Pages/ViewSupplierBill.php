<?php

namespace App\Filament\Resources\SupplierBills\Pages;

use App\Filament\Resources\SupplierBills\SupplierBillResource;
use App\Filament\Widgets\ThreeWayMatchingWidget;
use App\Models\AuditLog;
use App\Models\SupplierBill;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ViewSupplierBill extends ViewRecord
{
    protected static string $resource = SupplierBillResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['lines'] = $this->record->lines->toArray();

        return $data;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ThreeWayMatchingWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve_bill')
                ->label('Approve Bill / বিল অনুমোদন')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (SupplierBill $record): bool => $record->status === 'draft')
                ->action(function (SupplierBill $record, array $data) {
                    $widget = new ThreeWayMatchingWidget();
                    $widget->record = $record;
                    $reconciliation = $widget->getReconciliationData();

                    if ($reconciliation['has_exceptions']) {
                        /** @var \App\Models\User $user */
                        $user = Auth::user();

                        // Check if user has permission to override
                        $canOverride = $user && ($user->hasRole('Financial Controller') || $user->hasRole('Tenant Admin') || $user->hasRole('Tenant Owner'));

                        if (! $canOverride) {
                            Notification::make()
                                ->title('Three-Way Matching Blocked')
                                ->body('Cannot approve bill with unresolved quantity or price variances. Only a Financial Controller or Tenant Admin can approve with an override.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $reason = $data['override_reason'] ?? 'Managerial override of 3-way match exceptions';

                        // Audit log immutable recording
                        AuditLog::create([
                            'id' => (string) Str::uuid(),
                            'tenant_id' => $record->tenant_id,
                            'table_name' => 'supplier_bills',
                            'record_id' => $record->id,
                            'action' => 'THREE_WAY_MATCH_OVERRIDE',
                            'actor_id' => $user->id,
                            'actor_type' => get_class($user),
                            'new_values' => [
                                'reason' => $reason,
                                'exceptions' => $reconciliation['exceptions'],
                                'net_ppv' => $reconciliation['net_ppv'],
                            ],
                            'payload_hash' => hash('sha256', json_encode([
                                'bill_id' => $record->id,
                                'reason' => $reason,
                                'actor_id' => $user->id,
                            ])),
                            'status' => 'pending',
                        ]);

                        $record->status = 'approved';
                        $record->approved_by = $user->id;
                        $record->approved_at = now();
                        $record->save();

                        Notification::make()
                            ->title('Bill Approved with Managerial Override')
                            ->body("Exceptions overridden by {$user->name}. Logged to audit trail.")
                            ->warning()
                            ->send();

                        return;
                    }

                    // Clean approval with zero exceptions
                    $record->status = 'approved';
                    $record->approved_by = Auth::id();
                    $record->approved_at = now();
                    $record->save();

                    Notification::make()
                        ->title('Supplier Bill Approved')
                        ->body('Three-way match verified. Bill approved.')
                        ->success()
                        ->send();
                })
                ->form(function (SupplierBill $record) {
                    $widget = new ThreeWayMatchingWidget();
                    $widget->record = $record;
                    $reconciliation = $widget->getReconciliationData();

                    if ($reconciliation['has_exceptions']) {
                        return [
                            Textarea::make('override_reason')
                                ->label('Managerial Override Justification / বিশেষ অনুমোদনের কারণ')
                                ->helperText('Three-way matching exceptions detected. A written justification is mandatory for audit compliance.')
                                ->required(),
                        ];
                    }

                    return [];
                }),
        ];
    }
}
