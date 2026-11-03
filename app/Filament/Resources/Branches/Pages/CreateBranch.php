<?php

namespace App\Filament\Resources\Branches\Pages;

use App\Filament\Resources\Branches\BranchResource;
use App\Models\Branch;
use Filament\Resources\Pages\CreateRecord;

class CreateBranch extends CreateRecord
{
    protected static string $resource = BranchResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['code'])) {
            $companyId = isset($data['company_id']) && is_string($data['company_id']) ? $data['company_id'] : null;
            $data['code'] = Branch::generateNextCode(companyId: $companyId);
        }

        return $data;
    }
}
