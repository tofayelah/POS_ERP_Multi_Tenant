# GOODS RECEIPT RESOURCE SPECIFICATION (GRN)
## KC Retail ERP | Filament v5 Warehouse Intake Module

- **Resource Class:** `App\Filament\Resources\GoodsReceipts\GoodsReceiptResource`
- **Model:** `App\Models\GoodsReceipt` (Lines: `App\Models\GoodsReceiptLine`)
- **Navigation Group:** `Procurement`
- **Navigation Label:** `Goods Receipts (GRN) / পণ্য গ্রহণ চালান`
- **Icon:** `heroicon-o-archive-box-arrow-down`
- **Sort Priority:** 20
- **Document Status:** IMPLEMENTATION PLANNING ONLY (Awaiting Human Approval)

---

## 1. Overview & Business Objectives

The `GoodsReceiptResource` (Goods Receipt Note - GRN) governs physical warehouse stock intake across retail outlets, central distribution centers (CDCs), and factory warehouses. It acts as the **exclusive gateway** through which inventory enters active stock valuation, bridging commercial commitments (Purchase Orders) with physical stock ledgers (`StockBalance` and `StockMovement`).

### Key Business Invariants
1. **Exclusive Authority for Inbound Stock Movement**: Physical stock levels and weighted average cost (AVCO) are adjusted **only** when a Goods Receipt is explicitly confirmed by an authorized Warehouse Manager. Purchase orders, quotations, and bills never alter stock.
2. **Partial Receipt Support**: Supports multi-stage delivery where a single PO is fulfilled across multiple GRNs. Each receipt atomically increments `received_quantity` on the parent PO line and checks remaining fulfillment caps.
3. **Segregation of Damaged & Rejected Quantities**: Damaged or substandard goods received from suppliers must never be mixed with sellable stock. Accepted quantities increase active stock; rejected and damaged quantities are quarantined and logged for supplier debit notes or return challans.
4. **Mandatory Batch & Expiry Tracking**: Supports batch numbers and expiration dates required for FMCG, dairy, and edible oil retail compliance.
5. **Bangladesh VAT Mushak Input Audit**: Requires supplier delivery challan number (`challan_number`) matching statutory transport documents.

---

## 2. Role-Based Access Control (RBAC) & Policies

### 2.1 Policy Definition (`GoodsReceiptPolicy`)

```php
namespace App\Policies;

use App\Models\GoodsReceipt;
use App\Models\User;
use App\Policies\Concerns\EnforcesLocationScope;

class GoodsReceiptPolicy
{
    use EnforcesLocationScope;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Warehouse Manager',
            'Procurement Manager',
            'Accountant',
            'Auditor',
        ]);
    }

    public function view(User $user, GoodsReceipt $grn): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->canAccessLocation($user, $grn->company_id, $grn->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Warehouse Manager',
        ]);
    }

    public function update(User $user, GoodsReceipt $grn): bool
    {
        if ($grn->status !== 'draft') {
            return false; // Confirmed GRNs are immutable
        }

        return $user->hasAnyRole(['Tenant Owner', 'Tenant Admin', 'Warehouse Manager'])
            && $this->canAccessLocation($user, $grn->company_id, $grn->branch_id);
    }

    public function confirm(User $user, GoodsReceipt $grn): bool
    {
        if ($grn->status !== 'draft') {
            return false;
        }

        return $this->update($user, $grn);
    }

    public function delete(User $user, GoodsReceipt $grn): bool
    {
        return $grn->status === 'draft' && $this->update($user, $grn);
    }
}
```

---

## 3. UI Schema & Component Architecture

### 3.1 Form Schema (`form(Schema $schema): Schema`)

#### Card 1: Intake Metadata
- **`company_id`** (`Select`):
  - Label: `Company / কোম্পানি`
  - Required, reactive, default to active company.
- **`branch_id`** (`Select`):
  - Label: `Receiving Branch / গ্রহণকারী শাখা`
  - Scoped by user location scopes and selected company.
- **`warehouse_id`** (`Select`):
  - Label: `Destination Warehouse / গুদাম`
  - Required. Options: `Warehouse::where('branch_id', $branchId)->where('is_active', true)`.
- **`supplier_id`** (`Select`):
  - Label: `Supplier / সরবরাহকারী`
  - Required, searchable, auto-populated when PO is selected.
- **`purchase_order_id`** (`Select`):
  - Label: `Purchase Order / ক্রয়াদেশ (ঐচ্ছিক)`
  - Searchable, nullable (supports direct non-PO intake if authorized).
  - Reactive: When selected, automatically pulls remaining unfulfilled line items into the lines repeater.
- **`grn_number`** (`TextInput`):
  - Label: `GRN Number / চালান নম্বর`
  - Disabled, placeholder: `Auto-generated (GRN-YYYY-XXXX)`.
- **`receipt_date`** (`DatePicker`):
  - Label: `Receipt Date / গ্রহণের তারিখ`
  - Default: `today()`, required.
- **`challan_number`** (`TextInput`):
  - Label: `Supplier Challan # / সরবরাহকারীর চালান নং`
  - Required for Mushak verification. Max 100 chars.
- **`status`** (`TextInput`):
  - Disabled, default: `draft`.

#### Card 2: Received Items & Quality Inspection (`Repeater::make('lines')`)
- **`purchase_order_line_id`** (`Hidden`): Links back to parent PO line.
- **`product_id`** (`Select`): Searchable, required, disabled if tied to PO line.
- **`variant_id`** (`Select`): Required, displays SKU, variant name, and packaging unit.
- **`ordered_quantity`** (`TextInput`): Read-only context display from PO.
- **`previously_received`** (`TextInput`): Read-only context display from PO line.
- **`received_quantity`** (`TextInput`):
  - Label: `Accepted Quantity / গৃহীত পরিমাণ`
  - Numeric, required, min: `0.0001`.
  - Validation: Cannot exceed remaining unfulfilled order quantity:
    $$Q_{\text{received}} \le Q_{\text{ordered}} - Q_{\text{previously\_received}}$$
- **`damaged_quantity`** (`TextInput`):
  - Label: `Damaged Qty / ক্ষতিগ্রস্ত পরিমাণ`
  - Numeric, default: `0.0000`. Does not enter sellable stock.
- **`rejected_quantity`** (`TextInput`):
  - Label: `Rejected Qty / প্রত্যাখ্যাত পরিমাণ`
  - Numeric, default: `0.0000`. Logged for return challan.
- **`rejection_reason`** (`TextInput`):
  - Label: `Defect / Rejection Reason / প্রত্যাখ্যানের কারণ`
  - Visible if `rejected_quantity > 0` or `damaged_quantity > 0`.
- **`unit_cost`** (`TextInput`):
  - Label: `Unit Cost / একক মূল্য (৳)`
  - Auto-populated from PO or variant cost price.
- **`batch_number`** (`TextInput`):
  - Label: `Batch / Lot # / ব্যাচ নম্বর`
  - Required for batch-tracked items.
- **`expiry_date`** (`DatePicker`):
  - Label: `Expiry Date / মেয়াদোত্তীর্ণ তারিখ`
  - Required if product category is perishable.

---

### 3.2 Table Schema (`table(Table $table): Table`)

#### Columns
1. **`grn_number`** (`TextColumn`): Searchable, sortable, copyable.
2. **`receipt_date`** (`TextColumn`): Date formatted, sortable.
3. **`purchaseOrder.order_number`** (`TextColumn`): Searchable, links to PO view.
4. **`supplier.name`** (`TextColumn`): Searchable, sortable.
5. **`warehouse.name`** (`TextColumn`): Sortable.
6. **`challan_number`** (`TextColumn`): Searchable.
7. **`status`** (`TextColumn`): Badge display:
   - `draft`: `gray`
   - `received`: `success` (Stock posted)
   - `billed`: `primary` (Vendor bill issued)
8. **`lines_count`** (`TextColumn`): Count of line items.

#### Filters
- **Status Filter**: `draft`, `received`, `billed`.
- **Warehouse Filter**: Dropdown of authorized warehouses.
- **Supplier Filter**: Searchable select.
- **Date Range Filter**: `receipt_date` picker.

---

## 4. Lifecycle Actions & Stock Movement Triggering

### 4.1 "Confirm & Post to Stock" Action

The confirmation action executes inside a database transaction:
1. Validates that current user holds `Warehouse Manager`, `Tenant Owner`, or `Tenant Admin`.
2. Validates lock on PO lines preventing race conditions during concurrent receipts.
3. Dispatches `StockMovementServiceContract::recordMovement` with:
   - `movement_type = GOODS_RECEIPT`
   - `quantity = $line->received_quantity`
   - `unit_cost = $line->unit_cost`
   - `warehouse_id = $grn->warehouse_id`
   - `batch_number = $line->batch_number`
   - `expiry_date = $line->expiry_date`
4. Updates parent PO lines `received_quantity`.
5. Updates parent PO status (`partially_received` vs `received`).
6. Transitions GRN status to `received`.

```php
Action::make('confirm_grn')
    ->label('Confirm & Post to Stock / গ্রহণ ও স্টক নিশ্চিত করুন')
    ->icon('heroicon-o-check-badge')
    ->color('success')
    ->requiresConfirmation()
    ->modalHeading('Post Goods Receipt to Inventory')
    ->modalDescription('This will permanently increment warehouse stock and update weighted average cost. This action cannot be undone.')
    ->visible(fn (GoodsReceipt $record): bool => $record->status === 'draft')
    ->action(function (GoodsReceipt $record, ProcurementDocumentChainContract $service) {
        $service->confirmGoodsReceipt($record->id);
        Notification::make()->title('Goods Receipt Posted: Stock Successfully Updated')->success()->send();
    })
```

---

## 5. Quality Inspection & Database Impact Assessment

### 5.1 Damaged / Rejected Quantity Schema Impact

In baseline migration `2026_11_12_000005_create_goods_receipts_table.php`, `goods_receipt_lines` contains:
```sql
received_quantity DECIMAL(18, 4),
billed_quantity   DECIMAL(18, 4) DEFAULT 0.0000,
unit_cost         DECIMAL(18, 4),
batch_number      VARCHAR(100),
expiry_date       DATE
```

#### Proposed Schema Extension (To be applied in Implementation Phase upon Approval):
```sql
ALTER TABLE goods_receipt_lines 
    ADD COLUMN rejected_quantity DECIMAL(18, 4) DEFAULT 0.0000,
    ADD COLUMN damaged_quantity DECIMAL(18, 4) DEFAULT 0.0000,
    ADD COLUMN rejection_reason VARCHAR(255) NULL;
```
*Note: In the read-only planning phase, zero migrations are altered. In Sprint 1 implementation, an additive migration (`2026_11_20_000001_add_inspection_columns_to_goods_receipt_lines.php`) will be created without touching baseline migrations.*

---

## 6. Security & Automated Test Strategy

1. **Over-Delivery Guard Test**:
   - Verify that submitting a GRN with $Q_{\text{received}} > Q_{\text{ordered}} - Q_{\text{received\_prior}}$ throws `ReceivedQuantityExceedsOrderException` and rolls back stock.
2. **Double Posting Prevention**:
   - Verify that re-executing `confirmGoodsReceipt` on an already received GRN is a no-op and does not duplicate stock entries.
3. **Warehouse Location Scope Test**:
   - Verify that a Warehouse Manager assigned strictly to `WH-DHA-01` cannot view or confirm goods for `WH-CTG-01`.
4. **Pest Feature Test Class**:
   - `tests/Feature/Filament/GoodsReceiptResourceTest.php`
