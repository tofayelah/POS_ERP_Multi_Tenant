<?php

namespace App\Filament\Resources\SupplierQuotations;

use App\Filament\Resources\SupplierQuotations\Pages\CreateSupplierQuotation;
use App\Filament\Resources\SupplierQuotations\Pages\EditSupplierQuotation;
use App\Filament\Resources\SupplierQuotations\Pages\ListSupplierQuotations;
use App\Filament\Resources\SupplierQuotations\Pages\ViewSupplierQuotation;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\Tenant;
use App\Modules\Procurement\Contracts\ProcurementDocumentChainContract;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SupplierQuotationResource extends Resource
{
    protected static ?string $model = SupplierQuotation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Supplier Quotations / সরবরাহকারীর দরপত্র';

    protected static ?string $modelLabel = 'Supplier Quotation';

    protected static ?string $pluralModelLabel = 'Supplier Quotations';

    protected static string|\UnitEnum|null $navigationGroup = 'Procurement & Purchasing';

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
                    ->searchable()
                    ->required()
                    ->live(),

                Select::make('branch_id')
                    ->label('Branch / শাখা')
                    ->options(function ($get) {
                        $companyId = $get('company_id');
                        /** @var Tenant|null $tenant */
                        $tenant = Filament::getTenant();

                        if (! $tenant || ! $companyId) {
                            return [];
                        }

                        return Branch::where('tenant_id', $tenant->id)
                            ->where('company_id', $companyId)
                            ->where('is_active', true)
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->required(),

                Select::make('supplier_id')
                    ->label('Supplier / সরবরাহকারী')
                    ->options(function ($get) {
                        $companyId = $get('company_id');
                        /** @var Tenant|null $tenant */
                        $tenant = Filament::getTenant();

                        if (! $tenant || ! $companyId) {
                            return [];
                        }

                        return Supplier::where('tenant_id', $tenant->id)
                            ->where('company_id', $companyId)
                            ->where('is_active', true)
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->required(),

                TextInput::make('rfq_number')
                    ->label('RFQ Number / দরপত্র আহ্বান নং')
                    ->placeholder('e.g. RFQ-2026-0001')
                    ->required()
                    ->maxLength(100),

                TextInput::make('supplier_quote_reference')
                    ->label('Supplier Ref # / সরবরাহকারীর উদ্ধৃতি নং')
                    ->placeholder('e.g. SQ-DELTA-102')
                    ->maxLength(100),

                DatePicker::make('quote_date')
                    ->label('Quote Date / উদ্ধৃতি তারিখ')
                    ->default(now()->toDateString())
                    ->required(),

                DatePicker::make('valid_until')
                    ->label('Valid Until / মেয়াদ উত্তীর্ণের তারিখ')
                    ->default(now()->addDays(30)->toDateString())
                    ->required(),

                TextInput::make('currency')
                    ->label('Currency')
                    ->default('BDT')
                    ->disabled(),

                Repeater::make('lines')
                    ->label('Quotation Line Items / দরপত্রের পণ্যসমূহ')
                    ->schema([
                        Select::make('product_id')
                            ->label('Product / পণ্য')
                            ->options(function () {
                                /** @var Tenant|null $tenant */
                                $tenant = Filament::getTenant();

                                return $tenant ? Product::where('tenant_id', $tenant->id)->pluck('name_en', 'id') : [];
                            })
                            ->searchable()
                            ->required()
                            ->live(),

                        Select::make('variant_id')
                            ->label('Variant / ভেরিয়েন্ট')
                            ->options(function ($get) {
                                $productId = $get('product_id');
                                /** @var Tenant|null $tenant */
                                $tenant = Filament::getTenant();

                                if (! $tenant || ! $productId) {
                                    return [];
                                }

                                return ProductVariant::where('tenant_id', $tenant->id)
                                    ->where('product_id', $productId)
                                    ->pluck('name_en', 'id');
                            })
                            ->searchable()
                            ->required(),

                        TextInput::make('requested_quantity')
                            ->label('Quantity / পরিমাণ')
                            ->numeric()
                            ->default('1.0000')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $get, $set) {
                                self::updateLineTotal($get, $set);
                            }),

                        TextInput::make('quoted_unit_price')
                            ->label('Unit Price / একক দর')
                            ->numeric()
                            ->default('0.0000')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $get, $set) {
                                self::updateLineTotal($get, $set);
                            }),

                        TextInput::make('lead_time_days')
                            ->label('Lead Time (Days) / সরবরাহের সময় (দিন)')
                            ->numeric()
                            ->default(7)
                            ->required(),

                        TextInput::make('line_total')
                            ->label('Total / মোট')
                            ->numeric()
                            ->readOnly()
                            ->dehydrated(),
                    ])
                    ->columns(6)
                    ->columnSpanFull()
                    ->defaultItems(1),
            ]);
    }

    protected static function updateLineTotal(callable $get, callable $set): void
    {
        $qty = BigDecimal::of((string) ($get('requested_quantity') ?: '0.0000'));
        $price = BigDecimal::of((string) ($get('quoted_unit_price') ?: '0.0000'));
        $total = $qty->multipliedBy($price);

        $set('line_total', (string) $total->toScale(4, RoundingMode::HalfUp));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rfq_number')
                    ->label('RFQ #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('quote_date')
                    ->label('Quote Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->date()
                    ->sortable(),

                TextColumn::make('grand_total')
                    ->label('Grand Total')
                    ->money('BDT')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'sent' => 'info',
                        'evaluated' => 'warning',
                        'awarded' => 'success',
                        'rejected' => 'danger',
                        'converted_to_po' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('convertedPurchaseOrder.order_number')
                    ->label('Converted PO')
                    ->badge()
                    ->color('primary')
                    ->placeholder('None'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'sent' => 'Sent to Suppliers',
                        'evaluated' => 'Evaluated',
                        'awarded' => 'Awarded',
                        'rejected' => 'Rejected',
                        'converted_to_po' => 'Converted to PO',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    Action::make('award_bid')
                        ->label('Award Bid / দরপত্র অনুমোদন')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (SupplierQuotation $record): bool => in_array($record->status, ['draft', 'sent', 'evaluated'], true))
                        ->action(function (SupplierQuotation $record) {
                            $record->status = 'awarded';
                            $record->awarded_at = now();
                            $record->save();

                            Notification::make()
                                ->title('Supplier Quotation Awarded')
                                ->success()
                                ->send();
                        }),

                    Action::make('convert_to_purchase_order')
                        ->label('Convert to PO / ক্রয় আদেশে রূপান্তর')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('primary')
                        ->visible(fn (SupplierQuotation $record): bool => in_array($record->status, ['awarded', 'evaluated'], true))
                        ->action(function (SupplierQuotation $record) {
                            /** @var ProcurementDocumentChainContract $service */
                            $service = app(ProcurementDocumentChainContract::class);
                            $po = $service->convertSupplierQuotationToPurchaseOrder($record->id, (string) (Auth::id() ?? Str::uuid()));

                            Notification::make()
                                ->title('Purchase Order Created')
                                ->body("Converted to PO {$po->order_number}")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierQuotations::route('/'),
            'create' => CreateSupplierQuotation::route('/create'),
            'view' => ViewSupplierQuotation::route('/{record}'),
            'edit' => EditSupplierQuotation::route('/{record}/edit'),
        ];
    }
}
