<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-archive-box';

    protected static string | UnitEnum | null $navigationGroup = 'Product Master';

    protected static ?string $navigationLabel = 'Products & Items';

    protected static ?string $modelLabel = 'Product';

    protected static ?string $pluralModelLabel = 'Products';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Product Details')
                    ->tabs([
                        Tab::make('General Information')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Grid::make(['default' => 1, 'sm' => 2])
                                    ->schema([
                                        TextInput::make('name_en')
                                            ->label('Product Name (English)')
                                            ->placeholder('e.g. Arogya Neem Soap 125g')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('name_bn')
                                            ->label('পণ্যের নাম (বাংলা)')
                                            ->placeholder('যেমন: আরোগ্য নিম সাবান ১২৫ গ্রাম')
                                            ->maxLength(255),
                                        TextInput::make('code')
                                            ->label('Item / Product Code')
                                            ->placeholder('e.g. PRD-SOAP-01')
                                            ->maxLength(64),
                                        Toggle::make('has_variants')
                                            ->label('Has Multiple Variants / Sizes / Colors')
                                            ->helperText('Enable if this item has multiple package sizes, colors or weights')
                                            ->default(false),
                                        Toggle::make('is_active')
                                            ->label('Active Status')
                                            ->default(true),
                                    ]),
                                Textarea::make('description')
                                    ->label('Product Description')
                                    ->rows(3),
                            ]),

                        Tab::make('Classification & Supplier')
                            ->icon('heroicon-o-tag')
                            ->schema([
                                Grid::make(['default' => 1, 'sm' => 2])
                                    ->schema([
                                        Select::make('category_id')
                                            ->label('Category')
                                            ->relationship('category', 'name_en')
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Select Category'),
                                        Select::make('brand_id')
                                            ->label('Brand')
                                            ->relationship('brand', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Select Brand'),
                                        Select::make('uom_id')
                                            ->label('Default Unit of Measure (UOM)')
                                            ->relationship('uom', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Select Unit (e.g. PCS, KG)'),
                                        Select::make('supplier_id')
                                            ->label('Primary Supplier / Vendor')
                                            ->relationship('supplier', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Select Supplier'),
                                    ]),
                            ]),

                        Tab::make('Variants, Pricing & Barcodes')
                            ->icon('heroicon-o-currency-bangladeshi')
                            ->schema([
                                Repeater::make('variants')
                                    ->relationship('variants')
                                    ->defaultItems(1)
                                    ->itemLabel(fn (array $state): ?string => 
                                        ($state['sku'] ?? 'SKU') . ' | ' . 
                                        ($state['variant_name'] ?? 'Standard') . 
                                        ' (MRP: ৳' . number_format((float)($state['mrp_price'] ?? 0), 2) . ')'
                                    )
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 3])
                                            ->schema([
                                                TextInput::make('sku')
                                                    ->label('SKU Code')
                                                    ->placeholder('e.g. SO-NEEM-125G')
                                                    ->required()
                                                    ->maxLength(100),
                                                TextInput::make('barcode')
                                                    ->label('Barcode (EAN-13 / UPC)')
                                                    ->placeholder('e.g. 8941100234567')
                                                    ->maxLength(100),
                                                TextInput::make('variant_name')
                                                    ->label('Variant / Spec')
                                                    ->placeholder('e.g. Standard, 125g, 1L, Red/XL')
                                                    ->default('Standard')
                                                    ->maxLength(255),
                                            ]),

                                        Grid::make(['default' => 1, 'sm' => 3])
                                            ->schema([
                                                TextInput::make('purchase_price')
                                                    ->label('Purchase Cost')
                                                    ->prefix('৳')
                                                    ->numeric()
                                                    ->default(0.00),
                                                TextInput::make('mrp_price')
                                                    ->label('MRP / Retail Price')
                                                    ->prefix('৳')
                                                    ->numeric()
                                                    ->required()
                                                    ->default(0.00),
                                                TextInput::make('wholesale_price')
                                                    ->label('Wholesale Price')
                                                    ->prefix('৳')
                                                    ->numeric()
                                                    ->default(0.00),
                                            ]),

                                        Grid::make(['default' => 1, 'sm' => 4])
                                            ->schema([
                                                TextInput::make('tax_rate')
                                                    ->label('VAT Rate')
                                                    ->suffix('%')
                                                    ->numeric()
                                                    ->default(5.00),
                                                Toggle::make('is_tax_inclusive')
                                                    ->label('VAT-Inclusive (মুল্য সংযোজন কর সহ)')
                                                    ->default(true),
                                                TextInput::make('reorder_level')
                                                    ->label('Reorder Alert')
                                                    ->numeric()
                                                    ->default(10.00),
                                                Toggle::make('is_active')
                                                    ->label('Variant Active')
                                                    ->default(true),
                                            ]),
                                    ])
                                    ->collapsible()
                                    ->addActionLabel('+ Add Another Variant / SKU'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Item Code')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name_en')
                    ->label('Product Name')
                    ->description(fn (Product $record): ?string => $record->name_bn)
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('category.name_en')
                    ->label('Category')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Brand')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('uom.code')
                    ->label('UOM')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('variants_count')
                    ->label('SKUs')
                    ->counts('variants')
                    ->badge()
                    ->color('success'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('d M Y, h:i A')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name_en'),
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name'),
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
