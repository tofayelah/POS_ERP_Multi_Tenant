<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Models\Company;
use App\Models\Tenant;
use Filament\Forms\Components\Select;
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

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string | UnitEnum | null $navigationGroup = 'Organization Company';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Company Profile & Identity')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                Select::make('tenant_id')
                                    ->label('Tenant Account')
                                    ->options(fn () => Tenant::query()->pluck('name', 'id'))
                                    ->required()
                                    ->searchable(),
                                TextInput::make('name')
                                    ->label('Company Name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('legal_name')
                                    ->label('Registered Legal Name')
                                    ->maxLength(255),
                                TextInput::make('bin_number')
                                    ->label('13-digit NBR BIN')
                                    ->placeholder('000123456-0101')
                                    ->maxLength(32),
                                TextInput::make('tin_number')
                                    ->label('TIN Number')
                                    ->maxLength(32),
                                Select::make('currency')
                                    ->label('Base Currency')
                                    ->options([
                                        'BDT' => 'BDT (৳) - Bangladeshi Taka',
                                        'USD' => 'USD ($) - US Dollar',
                                    ])
                                    ->default('BDT')
                                    ->required(),
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
                    ->label('Company Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('bin_number')
                    ->label('NBR BIN')
                    ->badge()
                    ->color('info')
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('currency')
                    ->label('Currency'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
