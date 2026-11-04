<?php

namespace App\Filament\Resources\StockMovements;

use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Models\StockMovement;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Stock Ledger / স্টক খতিয়ান';

    protected static \UnitEnum|string|null $navigationGroup = 'Inventory';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('productVariant.sku')
                    ->label('SKU')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('movement_type')
                    ->label('Movement Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PURCHASE_RECEIPT', 'TRANSFER_IN' => 'success',
                        'SALE', 'TRANSFER_OUT' => 'danger',
                        'OFFLINE_OVERSELL' => 'warning',
                        'STOCK_COUNT_ADJUSTMENT' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                TextColumn::make('reference_type')
                    ->label('Reference Doc')
                    ->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
        ];
    }
}
