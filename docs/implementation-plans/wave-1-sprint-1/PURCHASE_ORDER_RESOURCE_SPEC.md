# PURCHASE ORDER RESOURCE SPECIFICATION
## KC Retail ERP | Filament v5 Back-Office Module

- **Resource Class:** `App\Filament\Resources\PurchaseOrders\PurchaseOrderResource`
- **Model:** `App\Models\PurchaseOrder` (Lines: `App\Models\PurchaseOrderLine`)
- **Navigation Group:** `Procurement`
- **Navigation Label:** `Purchase Orders / ক্রয়াদেশ`
- **Icon:** `heroicon-o-shopping-bag`
- **Sort Priority:** 10
- **Document Status:** IMPLEMENTATION PLANNING ONLY (Awaiting Human Approval)

---

## 1. Overview & Business Objectives

The `PurchaseOrderResource` provides an authoritative commercial interface for creating, managing, and confirming supplier purchase orders. It ensures that procurement requests are properly documented, authorized by role-scoped personnel, and tracked against supplier fulfillment without directly impacting inventory levels.

### Key Business Invariants
1. **No Direct Inventory Impact**: Creating or confirming a Purchase Order **never** alters physical stock balances or posts stock movements. Stock changes occur exclusively upon Goods Receipt confirmation.
2. **Immutability of Confirmed Orders**: Once an order transitions to `confirmed`, its line items, quantities, and pricing are frozen. If terms change, the order must be cancelled (if unfulfilled) or supplemented with an amendment.
3. **Automated Number Series**: Order numbers are generated gaplessly per company/branch/fiscal-year via `NumberSeriesContract` using prefix `PO-` (e.g. `PO-2026-0001`).
4. **Tenant & Company Scoping**: All queries and selections are strictly bound to the active tenant and the user's authorized company/branch location scopes.

---

## 2. Role-Based Access Control (RBAC) & Policies

### 2.1 Policy Definition (`PurchaseOrderPolicy`)

```php
namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Policies\Concerns\EnforcesLocationScope;

class PurchaseOrderPolicy
{
    use EnforcesLocationScope;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Procurement Manager',
            'Accountant',
            'Auditor',
        ]);
    }

    public function view(User $user, PurchaseOrder $po): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->canAccessLocation($user, $po->company_id, $po->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Procurement Manager',
        ]);
    }

    public function update(User $user, PurchaseOrder $po): bool
    {
        if ($po->status !== 'draft') {
            return false; // Confirmed orders are immutable
        }

        if (! $user->hasAnyRole(['Tenant Owner', 'Tenant Admin', 'Procurement Manager'])) {
            return false;
        }

        return $this->canAccessLocation($user, $po->company_id, $po->branch_id);
    }

    public function confirm(User $user, PurchaseOrder $po): bool
    {
        if ($po->status !== 'draft') {
            return false;
        }

        return $this->update($user, $po);
    }

    public function cancel(User $user, PurchaseOrder $po): bool
    {
        if (in_array($po->status, ['received', 'closed', 'cancelled'])) {
            return false;
        }

        // Check if any receipts exist
        if ($po->lines()->where('received_quantity', '>', 0)->exists()) {
            return false; // Cannot cancel partially received order
        }

        return $this->update($user, $po);
    }

    public function delete(User $user, PurchaseOrder $po): bool
    {
        return $po->status === 'draft' && $this->update($user, $po);
    }
}
```

---

## 3. UI Schema & Component Architecture

### 3.1 Form Schema (`form(Schema $schema): Schema`)

The form utilizes Filament v5 Schema components grouped into logical cards:

#### Card 1: Header Information
- **`company_id`** (`Select`):
  - Label: `Company / কোম্পানি`
  - Options: `Company::where('tenant_id', Filament::getTenant()->id)->pluck('legal_name', 'id')`
  - Default: User's primary company.
  - Required, reactive, disables after creation.
- **`branch_id`** (`Select`):
  - Label: `Branch / Outlet / শাখা`
  - Options: Scoped by selected `company_id`.
  - Required.
- **`supplier_id`** (`Select`):
  - Label: `Supplier / সরবরাহকারী`
  - Options: `Supplier::where('tenant_id', ...)->where('is_active', true)->pluck('name', 'id')`
  - Searchable, preload, required.
- **`order_number`** (`TextInput`):
  - Label: `PO Number / আদেশ নং`
  - Disabled, placeholder: `Auto-generated (PO-YYYY-XXXX)`
- **`order_date`** (`DatePicker`):
  - Label: `Order Date / আদেশের তারিখ`
  - Default: `today()`, required.
- **`expected_delivery_date`** (`DatePicker`):
  - Label: `Expected Delivery / সম্ভাব্য সরবরাহের তারিখ`
  - Min date: `order_date`.
- **`status`** (`TextInput` / `Badge`):
  - Label: `Status / অবস্থা`
  - Disabled, default: `draft`.

#### Card 2: Line Items (`Repeater::make('lines')`)
- **`product_id`** (`Select`):
  - Label: `Product / পণ্য`
  - Options: `Product::where('company_id', $get('../../company_id'))->pluck('name_en', 'id')`
  - Searchable, reactive, required.
- **`variant_id`** (`Select`):
  - Label: `Packaging / Variant / ভ্যারিয়েন্ট`
  - Options: Scoped by selected `product_id`. Auto-populates `unit_price` from `ProductVariant::cost_price`.
  - Required, reactive.
- **`ordered_quantity`** (`TextInput`):
  - Label: `Ordered Qty / আদেশের পরিমাণ`
  - Numeric, min: `0.0001`, default: `1.0000`, required, reactive.
- **`unit_price`** (`TextInput`):
  - Label: `Unit Cost / একক দর (৳)`
  - Numeric, min: `0.0000`, required, reactive.
- **`tax_amount`** (`TextInput`):
  - Label: `VAT Amount / মূসক (৳)`
  - Numeric, default: `0.0000`, reactive.
- **`subtotal`** (`TextInput`):
  - Label: `Subtotal / উপমোট (৳)`
  - Disabled, computed: $\text{qty} \times \text{unit\_price}$.
- **`total_amount`** (`TextInput`):
  - Label: `Line Total / সর্বমোট (৳)`
  - Disabled, computed: $\text{subtotal} + \text{tax\_amount}$.

#### Card 3: Financial Summary & Notes
- **`subtotal`** (`TextInput`): Disabled, reactive sum of line subtotals.
- **`tax_amount`** (`TextInput`): Disabled, reactive sum of line taxes.
- **`grand_total`** (`TextInput`): Disabled, grand sum ($\sum \text{total\_amount}$).
- **`notes`** (`Textarea`): Remarks, delivery terms, or instructions.

---

### 3.2 Table Schema (`table(Table $table): Table`)

#### Columns
1. **`order_number`** (`TextColumn`): Searchable, sortable, copyable.
2. **`order_date`** (`TextColumn`): Date format (`Y-m-d`), sortable.
3. **`supplier.name`** (`TextColumn`): Searchable, sortable.
4. **`branch.name`** (`TextColumn`): Sortable.
5. **`grand_total`** (`TextColumn`): Money formatted (`৳ 1,250.00`), sortable.
6. **`status`** (`TextColumn`): Badge display:
   - `draft`: `gray`
   - `confirmed`: `info`
   - `partially_received`: `warning`
   - `received`: `success`
   - `closed`: `primary`
   - `cancelled`: `danger`
7. **`created_at`** (`TextColumn`): DateTime format, toggleable hidden by default.

#### Filters
- **Status Filter**: Multi-select (`draft`, `confirmed`, `partially_received`, `received`, `closed`, `cancelled`).
- **Supplier Filter**: Select searchable.
- **Branch Filter**: Scoped by user's active permissions.
- **Date Range Filter**: `order_date` from and to.

---

## 4. Lifecycle Actions & Service Invocation

### 4.1 Record Actions

```php
ActionGroup::make([
    ViewAction::make(),
    EditAction::make()
        ->visible(fn (PurchaseOrder $record): bool => $record->status === 'draft'),

    Action::make('confirm')
        ->label('Confirm Order / আদেশ নিশ্চিত করুন')
        ->icon('heroicon-o-check-circle')
        ->color('success')
        ->requiresConfirmation()
        ->modalHeading('Confirm Purchase Order')
        ->modalDescription('Are you sure you want to confirm this order? Pricing and quantities will be locked.')
        ->visible(fn (PurchaseOrder $record): bool => $record->status === 'draft')
        ->action(function (PurchaseOrder $record, ProcurementDocumentChainContract $service) {
            $service->confirmPurchaseOrder($record->id);
            Notification::make()->title('Purchase Order Confirmed')->success()->send();
        }),

    Action::make('create_grn')
        ->label('Receive Goods / মালামাল গ্রহণ')
        ->icon('heroicon-o-truck')
        ->color('primary')
        ->visible(fn (PurchaseOrder $record): bool => in_array($record->status, ['confirmed', 'partially_received']))
        ->url(fn (PurchaseOrder $record): string => GoodsReceiptResource::getUrl('create', ['purchase_order_id' => $record->id])),

    Action::make('cancel')
        ->label('Cancel Order / আদেশ বাতিল করুন')
        ->icon('heroicon-o-x-circle')
        ->color('danger')
        ->requiresConfirmation()
        ->visible(fn (PurchaseOrder $record): bool => $record->status === 'draft' || ($record->status === 'confirmed' && ! $record->lines()->where('received_quantity', '>', 0)->exists()))
        ->action(function (PurchaseOrder $record, ProcurementDocumentChainContract $service) {
            $service->cancelPurchaseOrder($record->id);
            Notification::make()->title('Purchase Order Cancelled')->warning()->send();
        }),
])
```

### 4.2 Page Implementation: `CreatePurchaseOrder`

To adhere strictly to Architectural Non-Negotiable #1, `CreatePurchaseOrder` overrides `handleRecordCreation`:

```php
namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Modules\Procurement\Contracts\ProcurementDocumentChainContract;
use App\Modules\Procurement\DTOs\CreatePurchaseOrderDTO;
use App\Modules\Procurement\DTOs\CreatePurchaseOrderLineDTO;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function handleRecordCreation(array $data): PurchaseOrder
    {
        $lines = array_map(function (array $line): CreatePurchaseOrderLineDTO {
            return new CreatePurchaseOrderLineDTO(
                productId: $line['product_id'],
                variantId: $line['variant_id'],
                orderedQuantity: (string) $line['ordered_quantity'],
                unitPrice: (string) $line['unit_price'],
                taxAmount: (string) ($line['tax_amount'] ?? '0.0000')
            );
        }, $data['lines'] ?? []);

        $dto = new CreatePurchaseOrderDTO(
            companyId: $data['company_id'],
            branchId: $data['branch_id'],
            supplierId: $data['supplier_id'],
            orderDate: $data['order_date'],
            expectedDeliveryDate: $data['expected_delivery_date'] ?? null,
            createdBy: Auth::id(),
            lines: $lines
        );

        /** @var ProcurementDocumentChainContract $service */
        $service = app(ProcurementDocumentChainContract::class);

        return $service->createPurchaseOrder($dto);
    }
}
```

---

## 5. Security & Verification Strategy

1. **Composite Key Verification**: Every created `purchase_orders` record maintains `UNIQUE (tenant_id, company_id, id)`.
2. **Pessimistic Number Locking**: `NumberSeriesService` locks the sequence counter to prevent duplicate PO numbers.
3. **Automated Feature Tests**:
   - `test_procurement_manager_can_create_purchase_order_via_filament()`
   - `test_unauthorized_user_cannot_confirm_purchase_order()`
   - `test_confirmed_purchase_order_cannot_be_mutated()`
   - `test_purchase_order_does_not_modify_stock_balance()`
