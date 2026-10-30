<?php

namespace App\Filament\Resources\TaxCodes;

use App\Filament\Resources\TaxCodes\Pages\CreateTaxCode;
use App\Filament\Resources\TaxCodes\Pages\EditTaxCode;
use App\Filament\Resources\TaxCodes\Pages\ListTaxCodes;
use App\Filament\Resources\TaxCodes\RelationManagers\RatesRelationManager;
use App\Models\Company;
use App\Models\TaxCode;
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

class TaxCodeResource extends Resource
{
    protected static ?string $model = TaxCode::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Tax Codes / মূসক কোড';

    protected static \UnitEnum|string|null $navigationGroup = 'Tax & VAT Compliance';

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
                    ->label('Tax Code / মূসক কোড')
                    ->required()
                    ->maxLength(50),

                TextInput::make('name_en')
                    ->label('English Name / ইংরেজি নাম')
                    ->required()
                    ->maxLength(255),

                TextInput::make('name_bn')
                    ->label('Bangla Name / বাংলা নাম')
                    ->maxLength(255),

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
            RatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxCodes::route('/'),
            'create' => CreateTaxCode::route('/create'),
            'edit' => EditTaxCode::route('/{record}/edit'),
        ];
    }
}
