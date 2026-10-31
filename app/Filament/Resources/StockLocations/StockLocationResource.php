<?php

namespace App\Filament\Resources\StockLocations;

use App\Filament\Resources\StockLocations\Pages\CreateStockLocation;
use App\Filament\Resources\StockLocations\Pages\EditStockLocation;
use App\Filament\Resources\StockLocations\Pages\ListStockLocations;
use App\Models\StockLocation;
use App\Models\Tenant;
use App\Models\Warehouse;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockLocationResource extends Resource
{
    protected static ?string $model = StockLocation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Stock Locations / বিন ও র্যাক';

    protected static \UnitEnum|string|null $navigationGroup = 'Inventory';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('warehouse_id')
                    ->label('Warehouse / গুদাম')
                    ->options(function () {
                        /** @var Tenant|null $tenant */
                        $tenant = Filament::getTenant();

                        return $tenant ? Warehouse::where('tenant_id', $tenant->id)->pluck('name', 'id') : [];
                    })
                    ->required(),

                TextInput::make('code')
                    ->label('Location Code (e.g. AISLE-1-BIN-4)')
                    ->required()
                    ->maxLength(50),

                TextInput::make('name')
                    ->label('Location Name')
                    ->required()
                    ->maxLength(255),

                Toggle::make('is_active')
                    ->label('Active / সক্রিয়')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('code')
                    ->label('Code')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
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
            'index' => ListStockLocations::route('/'),
            'create' => CreateStockLocation::route('/create'),
            'edit' => EditStockLocation::route('/{record}/edit'),
        ];
    }
}
