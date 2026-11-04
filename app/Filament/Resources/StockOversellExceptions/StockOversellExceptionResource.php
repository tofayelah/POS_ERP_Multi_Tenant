<?php

namespace App\Filament\Resources\StockOversellExceptions;

use App\Filament\Resources\StockOversellExceptions\Pages\EditStockOversellException;
use App\Filament\Resources\StockOversellExceptions\Pages\ListStockOversellExceptions;
use App\Models\StockOversellException;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockOversellExceptionResource extends Resource
{
    protected static ?string $model = StockOversellException::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Oversell Exceptions / অতিরিক্ত বিক্রয় কিউ';

    protected static \UnitEnum|string|null $navigationGroup = 'Inventory';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('quantity_oversold')
                    ->label('Oversold Quantity / অতিরিক্ত পরিমাণ')
                    ->disabled(),

                TextInput::make('cost_basis_used')
                    ->label('Cost Basis Used (BDT)')
                    ->disabled(),

                Select::make('status')
                    ->label('Resolution Status / অবস্থা')
                    ->options([
                        'OPEN' => 'OPEN',
                        'RESOLVED_BY_GRN' => 'RESOLVED_BY_GRN',
                        'RESOLVED_BY_TRANSFER' => 'RESOLVED_BY_TRANSFER',
                        'RESOLVED_BY_ADJUSTMENT' => 'RESOLVED_BY_ADJUSTMENT',
                        'ESCALATED' => 'ESCALATED',
                    ])
                    ->required(),

                Textarea::make('notes')
                    ->label('Supervisor Notes')
                    ->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Detected')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->sortable(),

                TextColumn::make('productVariant.sku')
                    ->label('SKU')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('quantity_oversold')
                    ->label('Oversold Deficit')
                    ->numeric(decimalPlaces: 4)
                    ->color('danger')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'OPEN' => 'danger',
                        'ESCALATED' => 'warning',
                        'RESOLVED_BY_GRN', 'RESOLVED_BY_TRANSFER', 'RESOLVED_BY_ADJUSTMENT' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('resolved_at')
                    ->label('Resolved At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockOversellExceptions::route('/'),
            'edit' => EditStockOversellException::route('/{record}/edit'),
        ];
    }
}
