<?php

namespace App\Filament\Platform\Resources\Subscriptions\Pages;

use App\Filament\Platform\Resources\Subscriptions\TenantSubscriptionResource;
use App\Models\TenantSubscription;
use App\Modules\Billing\Contracts\ModuleEntitlementServiceContract;
use Filament\Resources\Pages\CreateRecord;

class CreateTenantSubscription extends CreateRecord
{
    protected static string $resource = TenantSubscriptionResource::class;

    protected function afterCreate(): void
    {
        /** @var TenantSubscription $record */
        $record = $this->getRecord();

        // Sync module entitlements for the tenant
        app(ModuleEntitlementServiceContract::class)->syncFromSubscription($record);

        // Update tenant subscription_plan
        if ($record->tenant && $record->plan) {
            $record->tenant->update([
                'subscription_plan' => $record->plan->code,
            ]);
        }
    }
}
