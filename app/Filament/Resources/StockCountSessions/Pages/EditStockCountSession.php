<?php

namespace App\Filament\Resources\StockCountSessions\Pages;

use App\Filament\Resources\StockCountSessions\StockCountSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStockCountSession extends EditRecord
{
    protected static string $resource = StockCountSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
