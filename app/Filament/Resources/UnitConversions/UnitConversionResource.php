<?php

namespace App\Filament\Resources\UnitConversions;

use App\Filament\Resources\UnitConversions\Pages\CreateUnitConversion;
use App\Filament\Resources\UnitConversions\Pages\EditUnitConversion;
use App\Filament\Resources\UnitConversions\Pages\ListUnitConversions;
use App\Models\Company;
use App\Models\UnitConversion;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UnitConversionResource extends Resource
{
    protected static ?string $model = UnitConversion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Unit Conversions / একক রূপান্তর';

    protected static \UnitEnum|string|null $navigationGroup = 'Product Master';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->label('Company / কোম্পানি')
                    ->options(function () {
                        $tenant = Filament::getTenant();

                        return $tenant ? Company::where('tenant_id', $tenant->id)->pluck('legal_name', 'id') : [];
                    })
                    ->default(fn () => Filament::getTenant()?->companies()->first()?->id)
                    ->required(),

                TextInput::make('from_uom')
                    ->label('From Unit / উৎস একক (e.g. Carton)')
                    ->required()
                    ->maxLength(50),

                TextInput::make('to_uom')
                    ->label('To Unit / গন্তব্য একক (e.g. Piece)')
                    ->required()
                    ->maxLength(50),

                TextInput::make('multiplier')
                    ->label('Multiplier / গুণক (যেমন ১ কার্টন = ১২ পিস)')
                    ->numeric()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_uom')
                    ->label('From Unit / উৎস একক')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('to_uom')
                    ->label('To Unit / গন্তব্য একক')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('multiplier')
                    ->label('Multiplier / গুণক')
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnitConversions::route('/'),
            'create' => CreateUnitConversion::route('/create'),
            'edit' => EditUnitConversion::route('/{record}/edit'),
        ];
    }
}
