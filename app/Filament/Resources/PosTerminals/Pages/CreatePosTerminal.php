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
        /** @var Branch|null $branch */
        $branch = Branch::where('id', $data['branch_id'])->first();
        if ($branch instanceof Branch) {
            $data['company_id'] = $branch->company_id;
        }

        return $data;
    }
}
