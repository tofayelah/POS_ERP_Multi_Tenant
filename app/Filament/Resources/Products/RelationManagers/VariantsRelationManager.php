<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Product;
use App\Models\TaxCode;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Product Variants & Packaging / পণ্যের ভ্যারিয়েন্ট ও প্যাকেজিং';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sku')
                    ->label('SKU / এসকেইউ')
                    ->required()
                    ->maxLength(100),

                TextInput::make('name_en')
                    ->label('English Name / ইংরেজি নাম')
                    ->required()
                    ->maxLength(255),

                TextInput::make('name_bn')
                    ->label('Bangla Name / বাংলা নাম')
                    ->maxLength(255),

                Select::make('tax_code_id')
                    ->label('VAT Code / মূসক কোড')
                    ->options(function () {
                        /** @var Product $owner */
                        $owner = $this->getOwnerRecord();

                        return TaxCode::where('tenant_id', $owner->tenant_id)
                            ->where('company_id', $owner->company_id)
                            ->where('is_active', true)
                            ->pluck('name_en', 'id');
                    })
                    ->required(),

                TextInput::make('base_uom')
                    ->label('Base UOM / মৌলিক একক')
                    ->default('Bottle')
                    ->required(),

                TextInput::make('mrp')
                    ->label('Maximum Retail Price (MRP) / সর্বোচ্চ খুচরা মূল্য (৳)')
                    ->numeric()
                    ->required()
                    ->default('0.00'),

                TextInput::make('cost_price')
                    ->label('Cost Price / ক্রয় মূল্য (৳)')
                    ->numeric()
                    ->required()
                    ->default('0.00'),

                Toggle::make('is_active')
                    ->label('Active / সক্রিয়')
                    ->default(true),

                Repeater::make('barcodes')
                    ->label('Packaging Barcodes / প্যাকেজিং বারকোড (Piece, Carton, etc.)')
                    ->relationship('barcodes')
                    ->defaultItems(0)
                    ->schema([
                        TextInput::make('barcode')
                            ->label('Barcode / বারকোড')
                            ->required(),

                        TextInput::make('uom')
                            ->label('UOM / একক (e.g. Piece, Carton)')
                            ->required(),

                        TextInput::make('multiplier')
                            ->label('Multiplier / গুণক (যেমন ১ কার্টন = ১২)')
                            ->numeric()
                            ->default('1.0000')
                            ->required(),

                        Toggle::make('is_primary')
                            ->label('Primary / প্রাথমিক')
                            ->default(false),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU / এসকেইউ')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name_en')
                    ->label('English Name / ইংরেজি নাম')
                    ->searchable(),

                TextColumn::make('name_bn')
                    ->label('Bangla Name / বাংলা নাম')
                    ->searchable(),

                TextColumn::make('mrp')
                    ->label('MRP / সর্বোচ্চ খুচরা মূল্য')
                    ->prefix('৳ ')
                    ->sortable(),

                TextColumn::make('taxCode.name_en')
                    ->label('Tax Code / মূসক কোড'),

                TextColumn::make('barcodes.barcode')
                    ->label('Barcodes / বারকোড')
                    ->badge()
                    ->separator(','),

                IconColumn::make('is_active')
                    ->label('Active / সক্রিয়')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        /** @var Product $owner */
                        $owner = $this->getOwnerRecord();
                        $data['tenant_id'] = $owner->tenant_id;
                        $data['company_id'] = $owner->company_id;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
