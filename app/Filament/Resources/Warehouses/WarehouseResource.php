<?php

namespace App\Filament\Resources\Warehouses;

use App\Filament\Resources\Warehouses\Pages\CreateWarehouse;
use App\Filament\Resources\Warehouses\Pages\EditWarehouse;
use App\Filament\Resources\Warehouses\Pages\ListWarehouses;
use App\Models\Branch;
use App\Models\Company;
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

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationLabel = 'Warehouses / গুদাম';

    protected static \UnitEnum|string|null $navigationGroup = 'Inventory';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->label('Company / কোম্পানি')
                    ->options(function () {
                        /** @var Tenant|null $tenant */
                        $tenant = Filament::getTenant();

                        return $tenant ? Company::where('tenant_id', $tenant->id)->pluck('legal_name', 'id') : [];
                    })
                    ->default(fn () => Filament::getTenant()?->companies()->first()?->id)
                    ->required(),

                Select::make('branch_id')
                    ->label('Linked Branch / সংশ্লিষ্ট শাখা')
                    ->options(function () {
                        /** @var Tenant|null $tenant */
                        $tenant = Filament::getTenant();

                        return $tenant ? Branch::where('tenant_id', $tenant->id)->pluck('name', 'id') : [];
                    })
                    ->nullable(),

                TextInput::make('code')
                    ->label('Code / কোড')
                    ->required()
                    ->maxLength(50),

                TextInput::make('name')
                    ->label('Name / গুদামের নাম')
                    ->required()
                    ->maxLength(255),

                TextInput::make('address')
                    ->label('Address / ঠিকানা')
                    ->maxLength(500),

                Toggle::make('is_active')
                    ->label('Active / সক্রিয়')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Warehouse Name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('Company-Wide')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Created')
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
            'index' => ListWarehouses::route('/'),
            'create' => CreateWarehouse::route('/create'),
            'edit' => EditWarehouse::route('/{record}/edit'),
        ];
    }
}
