<?php

namespace App\Filament\Resources\ChartOfAccounts;

use App\Filament\Resources\ChartOfAccounts\Pages\CreateChartOfAccount;
use App\Filament\Resources\ChartOfAccounts\Pages\EditChartOfAccount;
use App\Filament\Resources\ChartOfAccounts\Pages\ListChartOfAccounts;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Tenant;
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

class ChartOfAccountResource extends Resource
{
    protected static ?string $model = ChartOfAccount::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Chart of Accounts / হিসাব তালিকা';

    protected static \UnitEnum|string|null $navigationGroup = 'Finance Accounting';

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

                TextInput::make('account_code')
                    ->label('Account Code / হিসাব কোড')
                    ->required()
                    ->maxLength(50),

                TextInput::make('account_name')
                    ->label('Account Name (English) / হিসাবের নাম')
                    ->required()
                    ->maxLength(255),

                TextInput::make('account_name_bn')
                    ->label('Account Name (Bangla) / হিসাবের নাম (বাংলা)')
                    ->maxLength(255),

                Select::make('account_type')
                    ->label('Type / শ্রেণি')
                    ->options([
                        'asset' => 'Asset / সম্পদ',
                        'liability' => 'Liability / দায়',
                        'equity' => 'Equity / মালিকানা স্বত্ব',
                        'revenue' => 'Revenue / আয়',
                        'expense' => 'Expense / ব্যয়',
                    ])
                    ->required(),

                Toggle::make('is_reconcilable')
                    ->label('Reconcilable / ব্যাংক মিলকরণযোগ্য')
                    ->default(false),

                Toggle::make('is_active')
                    ->label('Active / সক্রিয়')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('account_code')
                    ->label('Code / কোড')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('account_name')
                    ->label('Account Name / হিসাবের নাম')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('account_name_bn')
                    ->label('বাংলা নাম')
                    ->searchable(),

                TextColumn::make('account_type')
                    ->label('Type / শ্রেণি')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'asset' => 'info',
                        'liability' => 'warning',
                        'equity' => 'primary',
                        'revenue' => 'success',
                        'expense' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                IconColumn::make('is_reconcilable')
                    ->label('Reconcilable')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('account_code')
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChartOfAccounts::route('/'),
            'create' => CreateChartOfAccount::route('/create'),
            'edit' => EditChartOfAccount::route('/{record}/edit'),
        ];
    }
}
