<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Tenant;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    /** @var Tenant|null $tenant */
                    $tenant = Filament::getTenant();
                    if ($tenant) {
                        $data['tenant_id'] = $tenant->id;
                    }

                    return $data;
                }),
        ];
    }
}
