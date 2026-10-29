<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use App\Models\Company;
use Filament\Resources\Pages\CreateRecord;

class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!empty($data['company_id'])) {
            $company = Company::find($data['company_id']);
            if ($company) {
                $data['tenant_id'] = $company->tenant_id;
            }
        }

        return $data;
    }
}
