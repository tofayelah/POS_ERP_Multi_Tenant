<?php

namespace App\Filament\Resources\PurchaseOrders;

use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Modules\Procurement\Contracts\ProcurementDocumentChainContract;
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

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Purchase Orders / ক্রয়াদেশ';

    protected static \UnitEnum|string|null $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 10;

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

                TextInput::make('order_number')
                    ->label('Order Number / আদেশ নং')
                    ->placeholder('Auto-generated (PO-YYYY-XXXX)')
                    ->disabled()
                    ->dehydrated(false),

                DatePicker::make('order_date')
                    ->label('Order Date / আদেশের তারিখ')
                    ->default(now()->toDateString())
                    ->required(),

                DatePicker::make('expected_delivery_date')
                    ->label('Expected Delivery / সরবরাহের তারিখ')
                    ->minDate(fn ($get) => $get('order_date') ?? now()->toDateString()),

                TextInput::make('status')
                    ->label('Status / অবস্থা')
                    ->default('draft')
                    ->disabled()
                    ->dehydrated(false),

                Repeater::make('lines')
                    ->relationship('lines')
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

                                return ProductVariant::where('product_id', $productId)
                                    ->pluck('name_en', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state) {
                                    $variant = ProductVariant::find($state);
                                    if ($variant) {
                                        $set('unit_price', (string) $variant->cost_price);
                                    }
                                }
                            }),

                        TextInput::make('ordered_quantity')
                            ->label('Qty / পরিমাণ')
                            ->numeric()
                            ->default('1.0000')
                            ->required()
                            ->live(),

                        TextInput::make('unit_price')
                            ->label('Unit Price / একক দর (৳)')
                            ->numeric()
                            ->default('0.0000')
                            ->required()
                            ->live(),

                        TextInput::make('tax_amount')
                            ->label('VAT / মূসক (৳)')
                            ->numeric()
                            ->default('0.0000')
                            ->live(),

                        TextInput::make('subtotal')
                            ->label('Subtotal / উপমোট (৳)')
                            ->disabled()
                            ->dehydrated(false)
                            ->state(function ($get): string {
                                $qty = (float) ($get('ordered_quantity') ?? 0);
                                $price = (float) ($get('unit_price') ?? 0);

                                return number_format($qty * $price, 4, '.', '');
                            }),

                        TextInput::make('total_amount')
                            ->label('Total / সর্বমোট (৳)')
                            ->disabled()
                            ->dehydrated(false)
                            ->state(function ($get): string {
                                $qty = (float) ($get('ordered_quantity') ?? 0);
                                $price = (float) ($get('unit_price') ?? 0);
                                $tax = (float) ($get('tax_amount') ?? 0);

                                return number_format(($qty * $price) + $tax, 4, '.', '');
                            }),
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
                TextColumn::make('order_number')
                    ->label('PO #')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('order_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('supplier.name')
                    ->label('Supplier')
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
                        'confirmed' => 'info',
                        'partially_received' => 'warning',
                        'received' => 'success',
                        'closed' => 'primary',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('lines_count')
                    ->label('Lines')
                    ->counts('lines'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'confirmed' => 'Confirmed',
                        'partially_received' => 'Partially Received',
                        'received' => 'Received',
                        'closed' => 'Closed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn (PurchaseOrder $record): bool => $record->status === 'draft'),
                    Action::make('confirm')
                        ->label('Confirm Order')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (PurchaseOrder $record): bool => $record->status === 'draft')
                        ->action(function (PurchaseOrder $record, ProcurementDocumentChainContract $service) {
                            $service->confirmPurchaseOrder($record->id);
                            Notification::make()->title('Purchase Order Confirmed')->success()->send();
                        }),
                    Action::make('cancel')
                        ->label('Cancel Order')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(function (PurchaseOrder $record): bool {
                            if (! in_array($record->status, ['draft', 'confirmed'], true)) {
                                return false;
                            }

                            return ! $record->lines()->where('received_quantity', '>', 0)->exists();
                        })
                        ->action(function (PurchaseOrder $record, ProcurementDocumentChainContract $service) {
                            $service->cancelPurchaseOrder($record->id);
                            Notification::make()->title('Purchase Order Cancelled')->warning()->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'view' => ViewPurchaseOrder::route('/{record}'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
