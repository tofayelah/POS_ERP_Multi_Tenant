<?php

namespace App\Filament\Resources\StockBalances;

use App\Filament\Resources\StockBalances\Pages\ListStockBalances;
use App\Models\StockBalance;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockBalanceResource extends Resource
{
    protected static ?string $model = StockBalance::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationLabel = 'Stock Balances / বর্তমান স্টক';

    protected static \UnitEnum|string|null $navigationGroup = 'Inventory';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('stockLocation.name')
                    ->label('Location / Bin')
                    ->placeholder('General')
                    ->sortable(),

                TextColumn::make('productVariant.sku')
                    ->label('SKU')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('productVariant.name_en')
                    ->label('Product Variant')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('batch_number')
                    ->label('Batch')
                    ->placeholder('-'),

                TextColumn::make('quantity_on_hand')
                    ->label('On Hand')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                TextColumn::make('quantity_reserved')
                    ->label('Reserved')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                TextColumn::make('weighted_avg_cost')
                    ->label('WAC (BDT)')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockBalances::route('/'),
        ];
    }
}
