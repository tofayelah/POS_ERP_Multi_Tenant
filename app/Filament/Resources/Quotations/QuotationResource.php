<?php

namespace App\Filament\Resources\Quotations;

use App\Filament\Resources\Quotations\Pages\CreateQuotation;
use App\Filament\Resources\Quotations\Pages\EditQuotation;
use App\Filament\Resources\Quotations\Pages\ListQuotations;
use App\Filament\Resources\Quotations\Pages\ViewQuotation;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\Tenant;
use App\Modules\Sales\Contracts\SalesDocumentChainContract;
use App\Modules\Sales\DTOs\CreateSalesOrderDTO;
use App\Modules\Sales\DTOs\CreateSalesOrderLineDTO;
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

class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Quotations / বিক্রয় উদ্ধৃতি';

    protected static \UnitEnum|string|null $navigationGroup = 'CRM & Sales';

    protected static ?int $navigationSort = 15;

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
                    ->required()
                    ->live(),

                Select::make('branch_id')
                    ->label('Sales Branch / বিক্রয় শাখা')
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
                    ->required(),

                Select::make('customer_id')
                    ->label('Customer / ক্রেতা')
                    ->options(function ($get) {
                        $companyId = $get('company_id');
                        /** @var Tenant|null $tenant */
                        $tenant = Filament::getTenant();

                        if (! $tenant || ! $companyId) {
                            return [];
                        }

                        return Customer::where('tenant_id', $tenant->id)
                            ->where('company_id', $companyId)
                            ->where('is_active', true)
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->required(),

                TextInput::make('quotation_number')
                    ->label('Quotation # / উদ্ধৃতি নং')
                    ->placeholder('Auto-generated (QT-YYYY-XXXX)')
                    ->disabled()
                    ->dehydrated(false),

                DatePicker::make('quotation_date')
                    ->label('Quotation Date / উদ্ধৃতির তারিখ')
                    ->default(now()->toDateString())
                    ->required(),

                DatePicker::make('valid_until')
                    ->label('Valid Until / মেয়াদের শেষ তারিখ')
                    ->default(now()->addDays(15)->toDateString())
                    ->required()
                    ->minDate(fn ($get) => $get('quotation_date') ?? now()->toDateString()),

                TextInput::make('status')
                    ->label('Status / অবস্থা')
                    ->default('draft')
                    ->disabled()
                    ->dehydrated(false),

                Repeater::make('lines')
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
                            ->label('Variant / Packaging / ভ্যারিয়েন্ট')
                            ->options(function ($get) {
                                $productId = $get('product_id');
                                if (! $productId) {
                                    return [];
                                }

                                return ProductVariant::where('product_id', $productId)->pluck('name_en', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state) {
                                    $variant = ProductVariant::find($state);
                                    if ($variant) {
                                        $set('unit_price', (string) $variant->mrp);
                                    }
                                }
                            }),

                        TextInput::make('quantity')
                            ->label('Quantity / পরিমাণ')
                            ->numeric()
                            ->default('1.0000')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($set, $get) {
                                $qty = (float) ($get('quantity') ?? 0);
                                $price = (float) ($get('unit_price') ?? 0);
                                $tax = (float) ($get('tax_amount') ?? 0);
                                $sub = $qty * $price;
                                $set('subtotal', number_format($sub, 4, '.', ''));
                                $set('total_amount', number_format($sub + $tax, 4, '.', ''));
                            }),

                        TextInput::make('unit_price')
                            ->label('Unit Price / একক দর (৳)')
                            ->numeric()
                            ->default('0.0000')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($set, $get) {
                                $qty = (float) ($get('quantity') ?? 0);
                                $price = (float) ($get('unit_price') ?? 0);
                                $tax = (float) ($get('tax_amount') ?? 0);
                                $sub = $qty * $price;
                                $set('subtotal', number_format($sub, 4, '.', ''));
                                $set('total_amount', number_format($sub + $tax, 4, '.', ''));
                            }),

                        TextInput::make('tax_amount')
                            ->label('VAT / মূসক (৳)')
                            ->numeric()
                            ->default('0.0000')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($set, $get) {
                                $qty = (float) ($get('quantity') ?? 0);
                                $price = (float) ($get('unit_price') ?? 0);
                                $tax = (float) ($get('tax_amount') ?? 0);
                                $sub = $qty * $price;
                                $set('total_amount', number_format($sub + $tax, 4, '.', ''));
                            }),

                        TextInput::make('subtotal')
                            ->label('Subtotal / উপমোট (৳)')
                            ->readOnly(),

                        TextInput::make('total_amount')
                            ->label('Total / সর্বমোট (৳)')
                            ->readOnly(),

                        TextInput::make('notes')
                            ->label('Notes / বিবরণ')
                            ->maxLength(255),
                    ])
                    ->columns(4)
                    ->columnSpanFull()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('quotation_number')
                    ->label('Quote #')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('quotation_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->date()
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('Branch')
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
                        'converted' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'sent' => 'Sent',
                        'converted' => 'Converted to SO',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn (Quotation $record): bool => $record->status === 'draft'),

                    Action::make('convert_to_so')
                        ->label('Convert to Sales Order / বিক্রয় আদেশে রূপান্তর')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Convert to Sales Order')
                        ->modalDescription('This will create an official Sales Order linked to this quotation and lock the quotation.')
                        ->visible(fn (Quotation $record): bool => in_array($record->status, ['draft', 'sent'], true) && ! $record->valid_until->isPast())
                        ->action(function (Quotation $record, SalesDocumentChainContract $service) {
                            $lines = [];
                            foreach ($record->lines as $line) {
                                $lines[] = new CreateSalesOrderLineDTO(
                                    quotationLineId: $line->id,
                                    productId: $line->product_id,
                                    variantId: $line->variant_id,
                                    orderedQuantity: (string) $line->quantity,
                                    unitPrice: (string) $line->unit_price,
                                    taxAmount: (string) $line->tax_amount
                                );
                            }

                            $dto = new CreateSalesOrderDTO(
                                companyId: $record->company_id,
                                branchId: $record->branch_id,
                                customerId: $record->customer_id,
                                quotationId: $record->id,
                                orderDate: now()->toDateString(),
                                expectedDeliveryDate: now()->addDays(7)->toDateString(),
                                createdBy: Auth::id() ?? (string) \Illuminate\Support\Str::uuid(),
                                lines: $lines
                            );

                            $so = $service->convertQuotationToSalesOrder($record->id, $dto);

                            Notification::make()
                                ->title('Quotation Converted to SO')
                                ->body("Created Sales Order #{$so->order_number}")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuotations::route('/'),
            'create' => CreateQuotation::route('/create'),
            'view' => ViewQuotation::route('/{record}'),
            'edit' => EditQuotation::route('/{record}/edit'),
        ];
    }
}
