<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitOfMeasureResource\Pages;
use App\Models\UnitOfMeasure;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;

class UnitOfMeasureResource extends Resource
{
    protected static ?string $model = UnitOfMeasure::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-scale';

    protected static string | UnitEnum | null $navigationGroup = 'Product Master';

    protected static ?string $navigationLabel = 'Units of Measure (UOM)';

    protected static ?string $modelLabel = 'Unit of Measure';

    protected static ?string $pluralModelLabel = 'Units of Measure';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Unit Configuration')
                    ->description('Define measurement units for inventory, purchasing, and sales.')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextInput::make('name')
                                    ->label('Unit Name')
                                    ->placeholder('e.g. Kilogram, Pieces, Liter')
                                    ->required()
                                    ->maxLength(128),
                                TextInput::make('code')
                                    ->label('Unit Code / Shortcode')
                                    ->placeholder('e.g. KG, PCS, LTR, BOX')
                                    ->required()
                                    ->maxLength(32),
                                TextInput::make('symbol')
                                    ->label('Symbol')
                                    ->placeholder('e.g. kg, pcs, L')
                                    ->maxLength(16),
                                Toggle::make('allow_decimal')
                                    ->label('Allow Decimal Quantities')
                                    ->helperText('Enable for weight/volume (e.g. 1.250 kg), disable for discrete items (e.g. 1 pcs)')
                                    ->default(false),
                                Toggle::make('is_active')
                                    ->label('Active Status')
                                    ->default(true),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Unit Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('symbol')
                    ->label('Symbol'),
                Tables\Columns\IconColumn::make('allow_decimal')
                    ->label('Decimals Allowed')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('d M Y, h:i A')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnitOfMeasures::route('/'),
            'create' => Pages\CreateUnitOfMeasure::route('/create'),
            'edit' => Pages\EditUnitOfMeasure::route('/{record}/edit'),
        ];
    }
}
