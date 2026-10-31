<?php

namespace App\Filament\Resources\StockCountSessions\Pages;

use App\Filament\Resources\StockCountSessions\StockCountSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStockCountSessions extends ListRecords
{
    protected static string $resource = StockCountSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
