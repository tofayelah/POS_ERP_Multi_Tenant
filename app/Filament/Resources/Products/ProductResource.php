<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Tenant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Products / পণ্যসমূহ';

    protected static \UnitEnum|string|null $navigationGroup = 'Product Master';

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
                    ->default(function () {
                        /** @var Tenant|null $tenant */
                        $tenant = Filament::getTenant();

                        return $tenant?->companies()->first()?->id;
                    })
                    ->required(),

                Select::make('category_id')
                    ->label('Category / শ্রেণী')
                    ->options(function () {
                        /** @var Tenant|null $tenant */
                        $tenant = Filament::getTenant();

                        return $tenant ? Category::where('tenant_id', $tenant->id)->pluck('name_en', 'id') : [];
                    })
                    ->nullable()
                    ->searchable(),

                TextInput::make('code')
                    ->label('Product Code / কোড')
                    ->required()
                    ->maxLength(50),

                TextInput::make('name_en')
                    ->label('English Name / ইংরেজি নাম')
                    ->required()
                    ->maxLength(255),

                TextInput::make('name_bn')
                    ->label('Bangla Name / বাংলা নাম')
                    ->maxLength(255),

                TextInput::make('base_uom')
                    ->label('Base UOM / মৌলিক একক')
                    ->default('Bottle')
                    ->required(),

                Textarea::make('description')
                    ->label('Description / বিবরণ')
                    ->nullable(),

                Toggle::make('is_active')
                    ->label('Active / সক্রিয়')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code / কোড')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name_en')
                    ->label('English Name / ইংরেজি নাম')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name_bn')
                    ->label('Bangla Name / বাংলা নাম')
                    ->searchable(),

                TextColumn::make('category.name_en')
                    ->label('Category / শ্রেণী')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('base_uom')
                    ->label('Base UOM / একক'),

                IconColumn::make('is_active')
                    ->label('Active / সক্রিয়')
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

    public static function getRelations(): array
    {
        return [
            VariantsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
