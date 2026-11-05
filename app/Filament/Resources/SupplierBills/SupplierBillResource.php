<?php

namespace App\Filament\Resources\SupplierBills;

use App\Filament\Resources\SupplierBills\Pages\CreateSupplierBill;
use App\Filament\Resources\SupplierBills\Pages\ListSupplierBills;
use App\Filament\Resources\SupplierBills\Pages\ViewSupplierBill;
use App\Filament\Widgets\ThreeWayMatchingWidget;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SupplierBillResource extends Resource
{
    protected static ?string $model = SupplierBill::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-bangladeshi';

    protected static string|\UnitEnum|null $navigationGroup = 'Procurement & Purchasing';

    protected static ?string $navigationLabel = 'Supplier Bills / সরবরাহকারী বিল';

    protected static ?int $navigationSort = 3;

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

                TextInput::make('bill_number')
                    ->label('Bill Number / বিল নং')
                    ->placeholder('Auto-generated (BILL-YYYY-XXXX)')
                    ->disabled()
                    ->dehydrated(false),

                DatePicker::make('bill_date')
                    ->label('Bill Date / বিলের তারিখ')
                    ->default(now()->toDateString())
                    ->required(),

                DatePicker::make('due_date')
                    ->label('Due Date / পরিশোধের তারিখ')
                    ->default(now()->addDays(30)->toDateString())
                    ->required(),

                Select::make('purchase_order_id')
                    ->label('Linked PO / সংশ্লিষ্ট ক্রয় আদেশ')
                    ->options(function ($get) {
                        /** @var Tenant|null $tenant */
                        $tenant = Filament::getTenant();
                        $supplierId = $get('supplier_id');

                        if (! $tenant) {
                            return [];
                        }

                        $query = PurchaseOrder::where('tenant_id', $tenant->id);
                        if ($supplierId) {
                            $query->where('supplier_id', $supplierId);
                        }

                        return $query->pluck('order_number', 'id');
                    })
                    ->searchable(),

                Select::make('goods_receipt_id')
                    ->label('Linked GRN / সংশ্লিষ্ট পণ্য গ্রহণ')
                    ->options(function ($get) {
                        /** @var Tenant|null $tenant */
                        $tenant = Filament::getTenant();
                        $poId = $get('purchase_order_id');

                        if (! $tenant) {
                            return [];
                        }

                        $query = GoodsReceipt::where('tenant_id', $tenant->id);
                        if ($poId) {
                            $query->where('purchase_order_id', $poId);
                        }

                        return $query->pluck('grn_number', 'id');
                    })
                    ->searchable(),

                TextInput::make('status')
                    ->label('Status / অবস্থা')
                    ->default('draft')
                    ->disabled()
                    ->dehydrated(false),

                Repeater::make('lines')
                    ->schema([
                        Hidden::make('goods_receipt_line_id'),
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
                            ->required(),

                        TextInput::make('quantity')
                            ->label('Billed Qty / পরিমাণ')
                            ->numeric()
                            ->default(1)
                            ->required(),

                        TextInput::make('unit_price')
                            ->label('Unit Price / একক দর')
                            ->numeric()
                            ->prefix('৳')
                            ->required(),

                        TextInput::make('tax_amount')
                            ->label('Tax / ভ্যাট')
                            ->numeric()
                            ->default('0.0000')
                            ->prefix('৳'),
                    ])
                    ->columns(5)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bill_number')
                    ->label('Bill Number')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('bill_date')
                    ->label('Bill Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('purchaseOrder.order_number')
                    ->label('Linked PO')
                    ->badge()
                    ->color('info')
                    ->placeholder('None'),

                TextColumn::make('goodsReceipt.grn_number')
                    ->label('Linked GRN')
                    ->badge()
                    ->color('gray')
                    ->placeholder('None'),

                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('BDT')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'approved' => 'success',
                        'paid' => 'info',
                        'void' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'approved' => 'Approved',
                        'paid' => 'Paid',
                        'void' => 'Void',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),

                    Action::make('approve_bill')
                        ->label('Approve Bill / বিল অনুমোদন')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn (SupplierBill $record): bool => $record->status === 'draft')
                        ->action(function (SupplierBill $record, array $data) {
                            $widget = new ThreeWayMatchingWidget;
                            $widget->record = $record;
                            $reconciliation = $widget->getReconciliationData();

                            if ($reconciliation['has_exceptions']) {
                                /** @var User|null $user */
                                $user = Auth::user();

                                if (! $user) {
                                    return;
                                }

                                // Check if user has permission to override
                                $canOverride = $user->hasRole('Financial Controller') || $user->hasRole('Tenant Admin') || $user->hasRole('Tenant Owner');

                                if (! $canOverride) {
                                    Notification::make()
                                        ->title('Three-Way Matching Blocked')
                                        ->body('Cannot approve bill with unresolved quantity or price variances. Only a Financial Controller or Tenant Admin can approve with an override.')
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                $reason = $data['override_reason'] ?? 'Managerial override of 3-way match exceptions';

                                // Audit log immutable recording
                                AuditLog::create([
                                    'id' => (string) Str::uuid(),
                                    'tenant_id' => $record->tenant_id,
                                    'table_name' => 'supplier_bills',
                                    'record_id' => $record->id,
                                    'action' => 'MATCH_OVERRIDE',
                                    'actor_id' => $user->id,
                                    'actor_type' => get_class($user),
                                    'new_values' => [
                                        'reason' => $reason,
                                        'exceptions' => $reconciliation['exceptions'],
                                        'net_ppv' => $reconciliation['net_ppv'],
                                    ],
                                    'payload_hash' => hash('sha256', (string) json_encode([
                                        'bill_id' => $record->id,
                                        'reason' => $reason,
                                        'actor_id' => $user->id,
                                    ])),
                                    'status' => 'pending_hash',
                                ]);

                                $record->status = 'approved';
                                $record->approved_by = $user->id;
                                $record->approved_at = now();
                                $record->save();

                                Notification::make()
                                    ->title('Bill Approved with Managerial Override')
                                    ->body("Exceptions overridden by {$user->name}. Logged to audit trail.")
                                    ->warning()
                                    ->send();

                                return;
                            }

                            // Clean approval with zero exceptions
                            $record->status = 'approved';
                            $record->approved_by = (string) Auth::id();
                            $record->approved_at = now();
                            $record->save();

                            Notification::make()
                                ->title('Supplier Bill Approved')
                                ->body('Three-way match verified. Bill approved.')
                                ->success()
                                ->send();
                        })
                        ->form(function (SupplierBill $record) {
                            /** @var User|null $user */
                            $user = Auth::user();
                            $canOverride = $user && ($user->hasRole('Financial Controller') || $user->hasRole('Tenant Admin') || $user->hasRole('Tenant Owner'));

                            $widget = new ThreeWayMatchingWidget;
                            $widget->record = $record;
                            $reconciliation = $widget->getReconciliationData();

                            if ($reconciliation['has_exceptions'] && $canOverride) {
                                return [
                                    Textarea::make('override_reason')
                                        ->label('Managerial Override Justification / বিশেষ অনুমোদনের কারণ')
                                        ->helperText('Three-way matching exceptions detected. A written justification is mandatory for audit compliance.')
                                        ->required(),
                                ];
                            }

                            return [];
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierBills::route('/'),
            'create' => CreateSupplierBill::route('/create'),
            'view' => ViewSupplierBill::route('/{record}'),
        ];
    }
}
