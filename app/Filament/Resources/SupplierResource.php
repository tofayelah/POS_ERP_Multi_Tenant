<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Company;
use App\Models\Supplier;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-truck';

    protected static string | UnitEnum | null $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Supplier Details')
                    ->tabs([
                        Tab::make('General Information')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                    ->schema([
                                        Select::make('company_id')
                                            ->label('Company / Organization')
                                            ->options(fn () => Company::query()->pluck('name', 'id'))
                                            ->required()
                                            ->searchable(),
                                        TextInput::make('name')
                                            ->label('Supplier / Vendor Name')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('code')
                                            ->label('Supplier Code')
                                            ->placeholder('e.g. SUP-001')
                                            ->maxLength(64),
                                        TextInput::make('contact_person')
                                            ->label('Contact Person')
                                            ->maxLength(128),
                                        TextInput::make('phone')
                                            ->label('Phone Number')
                                            ->tel()
                                            ->maxLength(32),
                                        TextInput::make('alternate_phone')
                                            ->label('Alternate / Emergency Phone')
                                            ->tel()
                                            ->maxLength(32),
                                        TextInput::make('email')
                                            ->label('Email Address')
                                            ->email()
                                            ->maxLength(128),
                                        TextInput::make('city')
                                            ->label('City / District')
                                            ->placeholder('e.g. Dhaka, Chittagong')
                                            ->maxLength(64),
                                        Select::make('country_code')
                                            ->label('Country')
                                            ->options([
                                                'BD' => 'Bangladesh',
                                                'IN' => 'India',
                                                'CN' => 'China',
                                                'US' => 'United States',
                                            ])
                                            ->default('BD'),
                                        Textarea::make('address')
                                            ->label('Full Physical Address')
                                            ->columnSpanFull()
                                            ->rows(2),
                                    ]),
                            ]),

                        Tab::make('Tax & VAT Compliance')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Grid::make(['default' => 1, 'sm' => 3])
                                    ->schema([
                                        TextInput::make('bin_number')
                                            ->label('VAT Reg / BIN Number')
                                            ->helperText('13-digit NBR Business Identification Number')
                                            ->placeholder('e.g. 000123456-0101')
                                            ->maxLength(32),
                                        TextInput::make('tax_number')
                                            ->label('Tax Identification (TIN)')
                                            ->placeholder('e.g. 123456789012')
                                            ->maxLength(64),
                                        TextInput::make('vat_reg_number')
                                            ->label('VAT Certificate No')
                                            ->maxLength(64),
                                    ]),
                            ]),

                        Tab::make('Banking & MFS Details')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Section::make('Commercial Bank Account')
                                    ->description('Official vendor corporate banking details for electronic fund transfers (BEFTN/RTGS/NPSB)')
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                            ->schema([
                                                TextInput::make('bank_name')
                                                    ->label('Bank Name')
                                                    ->placeholder('e.g. BRAC Bank PLC, City Bank')
                                                    ->maxLength(128),
                                                TextInput::make('bank_account_name')
                                                    ->label('Account Title / Name')
                                                    ->maxLength(128),
                                                TextInput::make('bank_account_number')
                                                    ->label('Account Number')
                                                    ->maxLength(64),
                                                TextInput::make('bank_branch_name')
                                                    ->label('Branch Name')
                                                    ->maxLength(128),
                                                TextInput::make('bank_routing_number')
                                                    ->label('Routing Number (9 Digits)')
                                                    ->maxLength(32),
                                            ]),
                                    ]),
                                Section::make('Mobile Financial Services (MFS)')
                                    ->description('Alternative digital payment channels')
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2])
                                            ->schema([
                                                Select::make('mfs_provider')
                                                    ->label('MFS Provider')
                                                    ->options([
                                                        'bKash' => 'bKash Merchant',
                                                        'Nagad' => 'Nagad Merchant',
                                                        'Rocket' => 'DBBL Rocket',
                                                        'Upay' => 'Upay',
                                                    ]),
                                                TextInput::make('mfs_account_number')
                                                    ->label('MFS Merchant / Wallet Number')
                                                    ->tel()
                                                    ->maxLength(32),
                                            ]),
                                    ]),
                            ]),

                        Tab::make('Internal Notes & Settings')
                            ->icon('heroicon-o-chat-bubble-bottom-center-text')
                            ->schema([
                                Grid::make(1)
                                    ->schema([
                                        Textarea::make('internal_notes')
                                            ->label('Internal Operational Notes')
                                            ->helperText('Visible only to procurement and finance staff')
                                            ->rows(3),
                                        Textarea::make('additional_notes')
                                            ->label('Additional / Contract Notes')
                                            ->rows(3),
                                        Toggle::make('is_active')
                                            ->label('Active Supplier Status')
                                            ->default(true),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Supplier Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('bin_number')
                    ->label('VAT / BIN')
                    ->badge()
                    ->color('info')
                    ->placeholder('N/A'),
                TextColumn::make('bank_name')
                    ->label('Bank')
                    ->placeholder('None')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('M j, Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->boolean(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
