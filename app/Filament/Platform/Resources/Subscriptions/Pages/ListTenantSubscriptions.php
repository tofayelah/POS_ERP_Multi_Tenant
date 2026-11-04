<?php

namespace App\Filament\Platform\Resources\Subscriptions\Pages;

use App\Filament\Platform\Resources\Subscriptions\TenantSubscriptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTenantSubscriptions extends ListRecords
{
    protected static string $resource = TenantSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Subscription'),
        ];
    }
}
