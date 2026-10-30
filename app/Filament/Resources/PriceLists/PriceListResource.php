<?php

namespace App\Filament\Resources\PriceLists;

use App\Filament\Resources\PriceLists\Pages\CreatePriceList;
use App\Filament\Resources\PriceLists\Pages\EditPriceList;
use App\Filament\Resources\PriceLists\Pages\ListPriceLists;
use App\Filament\Resources\PriceLists\RelationManagers\ItemsRelationManager;
use App\Models\Company;
use App\Models\PriceList;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PriceListResource extends Resource
{
    protected static ?string $model = PriceList::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-bangladeshi';

    protected static ?string $navigationLabel = 'Price Lists / মূল্য তালিকা';

    protected static \UnitEnum|string|null $navigationGroup = 'CRM & Sales';

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

                TextInput::make('code')
                    ->label('Price List Code / কোড')
                    ->required()
                    ->maxLength(50),

                TextInput::make('name_en')
                    ->label('English Name / ইংরেজি নাম')
                    ->required()
                    ->maxLength(255),

                TextInput::make('name_bn')
                    ->label('Bangla Name / বাংলা নাম')
                    ->maxLength(255),

                TextInput::make('currency')
                    ->label('Currency / মুদ্রা')
                    ->default('BDT')
                    ->required(),

                Toggle::make('is_tax_inclusive')
                    ->label('Tax Inclusive? / মূল্য কি কর-অন্তর্ভুক্ত?')
                    ->default(false),

                DatePicker::make('effective_from')
                    ->label('Effective From / হতে')
                    ->nullable(),

                DatePicker::make('effective_to')
                    ->label('Effective To / পর্যন্ত')
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

                IconColumn::make('is_tax_inclusive')
                    ->label('Tax Inclusive? / কর অন্তর্ভুক্ত?')
                    ->boolean(),

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
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceLists::route('/'),
            'create' => CreatePriceList::route('/create'),
            'edit' => EditPriceList::route('/{record}/edit'),
        ];
    }
}
