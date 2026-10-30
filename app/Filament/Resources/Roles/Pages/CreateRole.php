<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var Tenant|null $tenant */
        $tenant = Filament::getTenant();
        if ($tenant) {
            $data['tenant_id'] = $tenant->id;
        }

        return $data;
    }
}
