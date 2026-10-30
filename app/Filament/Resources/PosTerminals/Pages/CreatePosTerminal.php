<?php

namespace App\Filament\Resources\PosTerminals\Pages;

use App\Filament\Resources\PosTerminals\PosTerminalResource;
use App\Models\Branch;
use Filament\Resources\Pages\CreateRecord;

class CreatePosTerminal extends CreateRecord
{
    protected static string $resource = PosTerminalResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $branch = Branch::find($data['branch_id']);
        if ($branch) {
            $data['company_id'] = $branch->company_id;
        }

        return $data;
    }
}
