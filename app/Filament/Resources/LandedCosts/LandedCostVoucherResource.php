<?php

namespace App\Filament\Resources\LandedCosts;

use App\Filament\Resources\LandedCosts\Pages\CreateLandedCostVoucher;
use App\Filament\Resources\LandedCosts\Pages\ListLandedCostVouchers;
use App\Filament\Resources\LandedCosts\Pages\ViewLandedCostVoucher;
use App\Models\Branch;
use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\LandedCostVoucher;
use App\Models\Tenant;
use App\Modules\Inventory\Contracts\LandedCostAllocationContract;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class LandedCostVoucherResource extends Resource
{
    protected static ?string $model = LandedCostVoucher::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Landed Cost Vouchers / আমদানি ব্যয় ভাউচার';

    protected static \UnitEnum|string|null $navigationGroup = 'Procurement & Purchasing';

    protected static ?int $navigationSort = 5;

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

                        return $tenant ? Company::where('tenant_id', $tenant->id)->first()?->id : null;
                    })
                    ->required()
                    ->live(),

                Select::make('branch_id')
                    ->label('Branch / শাখা')
                    ->options(function (callable $get) {
                        $companyId = $get('company_id');
                        if (! $companyId) {
                            return [];
                        }

                        return Branch::where('company_id', $companyId)->pluck('name', 'id');
                    })
                    ->required(),

                TextInput::make('voucher_number')
                    ->label('Voucher Number / ভাউচার নম্বর')
                    ->placeholder('Auto-generated (e.g. LCV-202611-0001)')
                    ->maxLength(50),

                DatePicker::make('voucher_date')
                    ->label('Voucher Date / ভাউচার তারিখ')
                    ->default(now())
                    ->required(),

                Select::make('allocation_method')
                    ->label('Allocation Method / বণ্টন পদ্ধতি')
                    ->options([
                        'by_value' => 'By Value / মূল্য অনুযায়ী (Standard)',
                        'by_quantity' => 'By Quantity / পরিমাণ অনুযায়ী',
                        'by_weight' => 'By Weight / ওজন অনুযায়ী',
                    ])
                    ->default('by_value')
                    ->required(),

                TextInput::make('currency')
                    ->label('Currency / মুদ্রা')
                    ->default('BDT')
                    ->required()
                    ->maxLength(3),

                TextInput::make('exchange_rate')
                    ->label('Exchange Rate / বিনিময় হার')
                    ->numeric()
                    ->default('1.000000')
                    ->required(),

                Select::make('goods_receipt_ids')
                    ->label('Linked Goods Receipts (GRN) / পণ্য চালান')
                    ->multiple()
                    ->options(function (callable $get) {
                        $companyId = $get('company_id');
                        if (! $companyId) {
                            return [];
                        }

                        return GoodsReceipt::where('company_id', $companyId)
                            ->where('status', 'confirmed')
                            ->pluck('grn_number', 'id');
                    })
                    ->required(),

                Repeater::make('lines')
                    ->label('Landed Cost Expense Lines / আমদানি ব্যয়ের বিবরণ')
                    ->schema([
                        Select::make('expense_type')
                            ->label('Expense Type / ব্যয়ের ধরণ')
                            ->options([
                                'freight' => 'Freight / পরিবহন খরচ',
                                'customs_duty' => 'Customs & Port Duties / শুল্ক ও কর',
                                'port_handling' => 'Port Demurrage & Handling / বন্দর হ্যান্ডলিং',
                                'insurance' => 'Marine / Transit Insurance / বীমা খরচ',
                                'cnf_commission' => 'C&F Commission / সিঅ্যান্ডএফ কমিশন',
                                'other' => 'Other Landed Cost / অন্যান্য ব্যয়',
                            ])
                            ->required(),

                        TextInput::make('description')
                            ->label('Description / বিবরণ')
                            ->required(),

                        TextInput::make('amount')
                            ->label('Amount / পরিমাণ (BDT)')
                            ->numeric()
                            ->required(),

                        TextInput::make('tax_amount')
                            ->label('Tax / মূসক (BDT)')
                            ->numeric()
                            ->default('0.0000'),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->required(),

                Textarea::make('notes')
                    ->label('Notes / মন্তব্য')
                    ->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('voucher_number')
                    ->label('Voucher Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('voucher_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('allocation_method')
                    ->label('Method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'by_value' => 'primary',
                        'by_quantity' => 'info',
                        'by_weight' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('total_cost')
                    ->label('Total Cost (BDT)')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'posted' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('posted_at')
                    ->label('Posted At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('post')
                    ->label('Post / পোস্টিং')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Post Landed Cost Voucher')
                    ->modalDescription('Posting this voucher will capitalize landed costs into inventory on-hand balances (AVCO), expense any sold portion to COGS, and generate a General Ledger journal entry. This action cannot be undone.')
                    ->visible(fn (LandedCostVoucher $record): bool => $record->status === 'draft')
                    ->action(function (LandedCostVoucher $record) {
                        /** @var LandedCostAllocationContract $service */
                        $service = app(LandedCostAllocationContract::class);
                        $userId = Auth::id() ?? $record->created_by ?? 'system';

                        $service->postVoucher($record->id, $userId);

                        Notification::make()
                            ->title('Landed Cost Voucher Posted')
                            ->body("Voucher {$record->voucher_number} successfully posted to inventory and general ledger.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLandedCostVouchers::route('/'),
            'create' => CreateLandedCostVoucher::route('/create'),
            'view' => ViewLandedCostVoucher::route('/{record}'),
        ];
    }
}
