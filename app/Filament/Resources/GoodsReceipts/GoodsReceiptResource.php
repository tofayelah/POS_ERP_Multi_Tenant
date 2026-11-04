<?php

namespace App\Filament\Resources\GoodsReceipts;

use App\Filament\Resources\GoodsReceipts\Pages\CreateGoodsReceipt;
use App\Filament\Resources\GoodsReceipts\Pages\ListGoodsReceipts;
use App\Filament\Resources\GoodsReceipts\Pages\ViewGoodsReceipt;
use App\Models\Branch;
use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Modules\Procurement\Contracts\ProcurementDocumentChainContract;
use Brick\Math\BigDecimal;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GoodsReceiptResource extends Resource
{
    protected static ?string $model = GoodsReceipt::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static ?string $navigationLabel = 'Goods Receipts (GRN) / পণ্য গ্রহণ চালান';

    protected static \UnitEnum|string|null $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 20;

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
                    ->label('Receiving Branch / গ্রহণকারী শাখা')
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
                    ->required()
                    ->live(),

                Select::make('warehouse_id')
                    ->label('Destination Warehouse / গুদাম')
                    ->options(function ($get) {
                        $branchId = $get('branch_id');
                        $companyId = $get('company_id');
                        /** @var Tenant|null $tenant */
                        $tenant = Filament::getTenant();

                        if (! $tenant || ! $companyId) {
                            return [];
                        }

                        $query = Warehouse::where('tenant_id', $tenant->id)
                            ->where('company_id', $companyId)
                            ->where('is_active', true);

                        if ($branchId) {
                            $query->where('branch_id', $branchId);
                        }

                        return $query->pluck('name', 'id');
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

                Select::make('purchase_order_id')
                    ->label('Purchase Order / ক্রয়াদেশ (ঐচ্ছিক)')
                    ->options(function ($get) {
                        $companyId = $get('company_id');
                        $supplierId = $get('supplier_id');
                        /** @var Tenant|null $tenant */
                        $tenant = Filament::getTenant();

                        if (! $tenant || ! $companyId) {
                            return [];
                        }

                        $query = PurchaseOrder::where('tenant_id', $tenant->id)
                            ->where('company_id', $companyId)
                            ->whereIn('status', ['confirmed', 'partially_received']);

                        if ($supplierId) {
                            $query->where('supplier_id', $supplierId);
                        }

                        return $query->pluck('order_number', 'id');
                    })
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        if ($state) {
                            $po = PurchaseOrder::with('lines.variant')->find($state);
                            if ($po) {
                                $set('supplier_id', $po->supplier_id);
                                $set('branch_id', $po->branch_id);

                                $lines = [];
                                foreach ($po->lines as $l) {
                                    $rem = BigDecimal::of($l->ordered_quantity)->minus(BigDecimal::of($l->received_quantity));
                                    if ($rem->isPositive()) {
                                        $lines[] = [
                                            'purchase_order_line_id' => $l->id,
                                            'product_id' => $l->product_id,
                                            'variant_id' => $l->variant_id,
                                            'received_quantity' => (string) $rem,
                                            'unit_cost' => (string) $l->unit_price,
                                            'batch_number' => null,
                                            'expiry_date' => null,
                                        ];
                                    }
                                }
                                $set('lines', $lines);
                            }
                        }
                    }),

                TextInput::make('grn_number')
                    ->label('GRN Number / চালান নম্বর')
                    ->placeholder('Auto-generated (GRN-YYYY-XXXX)')
                    ->disabled()
                    ->dehydrated(false),

                DatePicker::make('receipt_date')
                    ->label('Receipt Date / গ্রহণের তারিখ')
                    ->default(now()->toDateString())
                    ->required(),

                TextInput::make('challan_number')
                    ->label('Supplier Delivery Challan # / সরবরাহকারীর চালান নং')
                    ->required()
                    ->maxLength(100),

                TextInput::make('status')
                    ->label('Status / অবস্থা')
                    ->default('draft')
                    ->disabled()
                    ->dehydrated(false),

                Repeater::make('lines')
                    ->schema([
                        Hidden::make('purchase_order_line_id'),

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
                            ->label('Variant / ভ্যারিয়েন্ট')
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
                                        $set('unit_cost', (string) $variant->cost_price);
                                    }
                                }
                            }),

                        TextInput::make('received_quantity')
                            ->label('Received Qty / গৃহীত পরিমাণ')
                            ->numeric()
                            ->default('1.0000')
                            ->required(),

                        TextInput::make('unit_cost')
                            ->label('Unit Cost / একক দর (৳)')
                            ->numeric()
                            ->default('0.0000')
                            ->required(),

                        TextInput::make('batch_number')
                            ->label('Batch / Lot # / ব্যাচ নম্বর')
                            ->maxLength(100),

                        DatePicker::make('expiry_date')
                            ->label('Expiry Date / মেয়াদোত্তীর্ণ তারিখ'),
                    ])
                    ->columns(6)
                    ->columnSpanFull()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('grn_number')
                    ->label('GRN #')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('receipt_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('purchaseOrder.order_number')
                    ->label('PO #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->sortable(),

                TextColumn::make('challan_number')
                    ->label('Challan #')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'received' => 'success',
                        'billed' => 'primary',
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
                        'received' => 'Received (Stock Posted)',
                        'billed' => 'Billed',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('confirm_grn')
                        ->label('Confirm & Post Stock / গ্রহণ ও স্টক নিশ্চিত করুন')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Post Goods Receipt to Inventory')
                        ->modalDescription('This will permanently increment warehouse stock and update weighted average cost. This action cannot be undone.')
                        ->visible(fn (GoodsReceipt $record): bool => $record->status === 'draft')
                        ->action(function (GoodsReceipt $record, ProcurementDocumentChainContract $service) {
                            $service->confirmGoodsReceipt($record->id);
                            Notification::make()->title('Goods Receipt Posted: Stock Successfully Updated')->success()->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGoodsReceipts::route('/'),
            'create' => CreateGoodsReceipt::route('/create'),
            'view' => ViewGoodsReceipt::route('/{record}'),
        ];
    }
}
