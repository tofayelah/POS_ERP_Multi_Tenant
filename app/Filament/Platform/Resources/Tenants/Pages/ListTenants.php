<?php

namespace App\Filament\Platform\Resources\Tenants\Pages;

use App\Filament\Platform\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Tenant'),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Tenants')
                ->badge(Tenant::count()),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'active'))
                ->badge(Tenant::where('status', 'active')->count())
                ->badgeColor('success'),
            'in_arrears' => Tab::make('In Arrears')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'in_arrears'))
                ->badge(Tenant::where('status', 'in_arrears')->count())
                ->badgeColor('warning'),
            'suspended' => Tab::make('Suspended')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'suspended'))
                ->badge(Tenant::where('status', 'suspended')->count())
                ->badgeColor('danger'),
        ];
    }
}
