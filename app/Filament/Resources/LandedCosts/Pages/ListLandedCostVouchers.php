<?php

namespace App\Filament\Resources\LandedCosts\Pages;

use App\Filament\Resources\LandedCosts\LandedCostVoucherResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLandedCostVouchers extends ListRecords
{
    protected static string $resource = LandedCostVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
