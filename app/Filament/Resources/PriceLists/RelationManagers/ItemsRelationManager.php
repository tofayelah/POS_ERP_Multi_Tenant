<?php

namespace App\Filament\Resources\PriceLists\RelationManagers;

use App\Models\PriceList;
use App\Models\ProductVariant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Price List Items / আইটেম মূল্যসমূহ';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('variant_id')
                    ->label('Product Variant / ভ্যারিয়েন্ট')
                    ->options(function () {
                        /** @var PriceList $owner */
                        $owner = $this->getOwnerRecord();

                        return ProductVariant::where('tenant_id', $owner->tenant_id)
                            ->where('company_id', $owner->company_id)
                            ->pluck('name_en', 'id');
                    })
                    ->required()
                    ->searchable(),

                TextInput::make('unit_price')
                    ->label('Unit Selling Price / বিক্রয় মূল্য (৳)')
                    ->numeric()
                    ->required(),

                TextInput::make('min_quantity')
                    ->label('Min Quantity / সর্বনিম্ন পরিমাণ')
                    ->numeric()
                    ->default('1.0000')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('unit_price')
            ->columns([
                TextColumn::make('variant.sku')
                    ->label('SKU / এসকেইউ')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('variant.name_en')
                    ->label('Name / পণ্যের নাম')
                    ->searchable(),

                TextColumn::make('variant.mrp')
                    ->label('MRP / সর্বোচ্চ খুচরা মূল্য')
                    ->prefix('৳ '),

                TextColumn::make('unit_price')
                    ->label('Selling Price / বিক্রয় মূল্য')
                    ->prefix('৳ ')
                    ->sortable(),

                TextColumn::make('min_quantity')
                    ->label('Min Qty / নূন্যতম পরিমাণ'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        /** @var PriceList $owner */
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
