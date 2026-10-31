<?php

namespace App\Filament\Resources\StockTransfers;

use App\Filament\Resources\StockTransfers\Pages\CreateStockTransfer;
use App\Filament\Resources\StockTransfers\Pages\EditStockTransfer;
use App\Filament\Resources\StockTransfers\Pages\ListStockTransfers;
use App\Models\StockTransfer;
use App\Models\Tenant;
use App\Models\Warehouse;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockTransferResource extends Resource
{
    protected static ?string $model = StockTransfer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Stock Transfers / স্থানান্তর';

    protected static \UnitEnum|string|null $navigationGroup = 'Inventory';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('source_warehouse_id')
                    ->label('Source Warehouse / প্রেরক গুদাম')
                    ->options(function () {
                        /** @var Tenant|null $tenant */
                        $tenant = Filament::getTenant();

                        return $tenant ? Warehouse::where('tenant_id', $tenant->id)->pluck('name', 'id') : [];
                    })
                    ->required(),

                Select::make('dest_warehouse_id')
                    ->label('Destination Warehouse / প্রাপক গুদাম')
                    ->options(function () {
                        /** @var Tenant|null $tenant */
                        $tenant = Filament::getTenant();

                        return $tenant ? Warehouse::where('tenant_id', $tenant->id)->pluck('name', 'id') : [];
                    })
                    ->required(),

                TextInput::make('vehicle_details')
                    ->label('Vehicle Details / গাড়ি বিবরণ')
                    ->maxLength(255),

                TextInput::make('driver_phone')
                    ->label('Driver Phone / ড্রাইভারের ফোন')
                    ->maxLength(50),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transfer_number')
                    ->label('Transfer #')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('sourceWarehouse.name')
                    ->label('Source')
                    ->sortable(),

                TextColumn::make('destWarehouse.name')
                    ->label('Destination')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'in_transit' => 'warning',
                        'received' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('shipped_at')
                    ->label('Shipped')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('received_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockTransfers::route('/'),
            'create' => CreateStockTransfer::route('/create'),
            'edit' => EditStockTransfer::route('/{record}/edit'),
        ];
    }
}
