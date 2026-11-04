<?php

namespace App\Filament\Resources\TaxCodes\Pages;

use App\Filament\Resources\TaxCodes\TaxCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTaxCodes extends ListRecords
{
    protected static string $resource = TaxCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
