<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BrandResource\Pages;
use App\Models\Brand;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-tag';

    protected static string | UnitEnum | null $navigationGroup = 'Product Master';

    protected static ?string $navigationLabel = 'Brands';

    protected static ?string $modelLabel = 'Brand';

    protected static ?string $pluralModelLabel = 'Brands';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Brand Information')
                    ->description('Manage product manufacturers, importers, and brand labels.')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextInput::make('name')
                                    ->label('Brand Name')
                                    ->placeholder('e.g. Pran, Fresh, Aarong, Unilever')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('code')
                                    ->label('Brand Code')
                                    ->placeholder('e.g. BR-PRAN, BR-FRSH')
                                    ->maxLength(64),
                                Select::make('origin_country')
                                    ->label('Country of Origin')
                                    ->options([
                                        'BD' => 'Bangladesh (BD)',
                                        'IN' => 'India (IN)',
                                        'CN' => 'China (CN)',
                                        'TH' => 'Thailand (TH)',
                                        'MY' => 'Malaysia (MY)',
                                        'VN' => 'Vietnam (VN)',
                                        'US' => 'United States (US)',
                                        'GB' => 'United Kingdom (GB)',
                                        'DE' => 'Germany (DE)',
                                        'JP' => 'Japan (JP)',
                                    ])
                                    ->default('BD')
                                    ->searchable(),
                                Toggle::make('is_active')
                                    ->label('Active Status')
                                    ->default(true),
                            ]),
                        Textarea::make('description')
                            ->label('Brand Description / Profile')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Brand Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('code')
                    ->label('Brand Code')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('origin_country')
                    ->label('Origin')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Total Products')
                    ->counts('products')
                    ->badge()
                    ->color('success'),
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
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBrands::route('/'),
            'create' => Pages\CreateBrand::route('/create'),
            'edit' => Pages\EditBrand::route('/{record}/edit'),
        ];
    }
}
