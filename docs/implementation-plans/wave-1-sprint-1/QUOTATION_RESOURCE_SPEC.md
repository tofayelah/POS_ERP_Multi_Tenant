# QUOTATION RESOURCE SPECIFICATION
## KC Retail ERP | Filament v5 Commercial Sales Module

- **Resource Class:** `App\Filament\Resources\Quotations\QuotationResource`
- **Model:** `App\Models\Quotation` (Lines: `App\Models\QuotationLine`)
- **Navigation Group:** `CRM & Sales`
- **Navigation Label:** `Quotations / বিক্রয় উদ্ধৃতি`
- **Icon:** `heroicon-o-document-text`
- **Sort Priority:** 15
- **Document Status:** IMPLEMENTATION PLANNING ONLY (Awaiting Human Approval)

---

## 1. Overview & Business Objectives

The `QuotationResource` provides a commercial interface for generating, tracking, and converting customer quotations (B2B corporate sales, institutional supply, wholesale orders, and project tenders). It forms the first phase of the **Order-to-Cash (O2C)** document chain:
$$\text{Quotation (QT)} \longrightarrow \text{Sales Order (SO)} \longrightarrow \text{Delivery Challan (DC)} \longrightarrow \text{Sales Invoice (INV)}$$

### Key Business Invariants
1. **Zero Stock Reservation or Movement**: Quotations represent prospective sales proposals and have **no effect** on physical inventory, allocated stock, or general ledger accounts.
2. **Price List & Credit Validation**: Quotation line pricing is governed by active Price Lists (Retail, Wholesale, Trade Distributor) while respecting statutory Maximum Retail Price (MRP) ceilings.
3. **Seamless Conversion to Sales Order**: An accepted quotation can be converted into a confirmed `SalesOrder` with a single click, atomically carrying forward lines, customer terms, and setting quotation status to `converted`.
4. **Validity Expiration Guard**: Quotations have a strict validity horizon (`valid_until`). Expired quotations cannot be converted into sales orders without manager re-validation.

---

## 2. Role-Based Access Control (RBAC) & Policies

### 2.1 Policy Definition (`QuotationPolicy`)

```php
namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;
use App\Policies\Concerns\EnforcesLocationScope;

class QuotationPolicy
{
    use EnforcesLocationScope;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Sales Manager',
            'Sales Representative',
            'Branch Manager',
            'Accountant',
            'Auditor',
        ]);
    }

    public function view(User $user, Quotation $quotation): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->canAccessLocation($user, $quotation->company_id, $quotation->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Sales Manager',
            'Sales Representative',
        ]);
    }

    public function update(User $user, Quotation $quotation): bool
    {
        if ($quotation->status !== 'draft') {
            return false; // Converted or cancelled quotations cannot be edited
        }

        return $user->hasAnyRole(['Tenant Owner', 'Tenant Admin', 'Sales Manager', 'Sales Representative'])
            && $this->canAccessLocation($user, $quotation->company_id, $quotation->branch_id);
    }

    public function convert(User $user, Quotation $quotation): bool
    {
        if ($quotation->status !== 'draft' && $quotation->status !== 'sent') {
            return false;
        }

        if ($quotation->valid_until->isPast()) {
            return false; // Cannot convert expired quotation
        }

        return $user->hasAnyRole(['Tenant Owner', 'Tenant Admin', 'Sales Manager'])
            && $this->canAccessLocation($user, $quotation->company_id, $quotation->branch_id);
    }

    public function cancel(User $user, Quotation $quotation): bool
    {
        if ($quotation->status === 'converted') {
            return false;
        }

        return $this->update($user, $quotation);
    }

    public function delete(User $user, Quotation $quotation): bool
    {
        return $quotation->status === 'draft' && $this->update($user, $quotation);
    }
}
```

---

## 3. UI Schema & Component Architecture

### 3.1 Form Schema (`form(Schema $schema): Schema`)

#### Card 1: Quotation Header
- **`company_id`** (`Select`):
  - Label: `Company / কোম্পানি`
  - Required, reactive, default to active company.
- **`branch_id`** (`Select`):
  - Label: `Sales Branch / বিক্রয় শাখা`
  - Scoped by user location scopes.
- **`customer_id`** (`Select`):
  - Label: `Customer / ক্রেতা`
  - Options: `Customer::where('tenant_id', ...)->where('is_active', true)->pluck('name', 'id')`.
  - Searchable, required.
- **`quotation_number`** (`TextInput`):
  - Label: `Quotation # / উদ্ধৃতি নং`
  - Disabled, placeholder: `Auto-generated (QT-YYYY-XXXX)`.
- **`quotation_date`** (`DatePicker`):
  - Label: `Quote Date / উদ্ধৃতির তারিখ`
  - Default: `today()`, required.
- **`valid_until`** (`DatePicker`):
  - Label: `Valid Until / মেয়াদের শেষ তারিখ`
  - Default: `today()->addDays(15)`, required. Min date: `quotation_date`.
- **`status`** (`TextInput`):
  - Disabled, default: `draft`.

#### Card 2: Quotation Line Items (`Repeater::make('lines')`)
- **`product_id`** (`Select`):
  - Searchable, required, reactive.
- **`variant_id`** (`Select`):
  - Required, reactive. Auto-populates `unit_price` based on customer price list or default wholesale/retail price.
- **`quantity`** (`TextInput`):
  - Label: `Quantity / পরিমাণ`
  - Numeric, min: `0.0001`, default: `1.0000`, required, reactive.
- **`unit_price`** (`TextInput`):
  - Label: `Unit Price / একক মূল্য (৳)`
  - Numeric, required, reactive.
- **`tax_amount`** (`TextInput`):
  - Label: `VAT / মূসক (৳)`
  - Calculated based on product tax code rate.
- **`subtotal`** (`TextInput`):
  - Disabled, computed: $\text{quantity} \times \text{unit\_price}$.
- **`total_amount`** (`TextInput`):
  - Disabled, computed: $\text{subtotal} + \text{tax\_amount}$.
- **`notes`** (`TextInput`):
  - Item-specific specifications, delivery instructions, or notes.

#### Card 3: Commercial Totals
- **`subtotal`** (`TextInput`): Reactive sum of line subtotals.
- **`tax_amount`** (`TextInput`): Reactive sum of line VAT amounts.
- **`grand_total`** (`TextInput`): Grand total payable ($\sum \text{total\_amount}$).

---

### 3.2 Table Schema (`table(Table $table): Table`)

#### Columns
1. **`quotation_number`** (`TextColumn`): Searchable, sortable, copyable.
2. **`quotation_date`** (`TextColumn`): Date formatted, sortable.
3. **`valid_until`** (`TextColumn`): Date formatted. Highlights in red if expired.
4. **`customer.name`** (`TextColumn`): Searchable, sortable.
5. **`branch.name`** (`TextColumn`): Sortable.
6. **`grand_total`** (`TextColumn`): Money formatted (`৳ 25,000.00`), sortable.
7. **`status`** (`TextColumn`): Badge display:
   - `draft`: `gray`
   - `sent`: `info`
   - `converted`: `success`
   - `expired`: `warning`
   - `cancelled`: `danger`

#### Filters
- **Status Filter**: Multi-select.
- **Customer Filter**: Searchable select.
- **Branch Filter**: Location scoped.
- **Validity Filter**: Active Quotes vs. Expired Quotes.

---

## 4. Lifecycle Actions & Service Invocation

### 4.1 "Convert to Sales Order" Action

When a customer accepts a quotation, the Sales Manager triggers conversion:

```php
Action::make('convert_to_so')
    ->label('Convert to Sales Order / বিক্রয় আদেশে রূপান্তর')
    ->icon('heroicon-o-arrow-path-rounded-square')
    ->color('success')
    ->requiresConfirmation()
    ->modalHeading('Convert Quotation to Confirmed Sales Order')
    ->modalDescription('This will create an official Sales Order linked to this quotation and lock the quotation.')
    ->visible(fn (Quotation $record): bool => in_array($record->status, ['draft', 'sent']) && ! $record->valid_until->isPast())
    ->action(function (Quotation $record, SalesDocumentChainContract $salesService) {
        $lines = $record->lines->map(function ($line): CreateSalesOrderLineDTO {
            return new CreateSalesOrderLineDTO(
                quotationLineId: $line->id,
                productId: $line->product_id,
                variantId: $line->variant_id,
                orderedQuantity: $line->quantity,
                unitPrice: $line->unit_price,
                taxAmount: $line->tax_amount
            );
        })->toArray();

        $dto = new CreateSalesOrderDTO(
            companyId: $record->company_id,
            branchId: $record->branch_id,
            customerId: $record->customer_id,
            quotationId: $record->id,
            orderDate: now()->toDateString(),
            expectedDeliveryDate: now()->addDays(7)->toDateString(),
            createdBy: Auth::id(),
            lines: $lines
        );

        $salesOrder = $salesService->convertQuotationToSalesOrder($record->id, $dto);

        Notification::make()
            ->title('Sales Order Created')
            ->body("Created SO #{$salesOrder->order_number} from Quotation #{$record->quotation_number}")
            ->success()
            ->send();
    })
```

### 4.2 "Print Quotation PDF" Action
- Produces professional customer-facing PDF quotation with corporate letterhead, customer BIN, payment terms, itemized table, and authorized signature section.

---

## 5. Security & Verification Strategy

1. **Price Integrity Assertion**: Verify that quotation pricing cannot be altered below minimum authorized margin without managerial discount override.
2. **Post-Conversion Lockout**: Verify that once converted, a quotation line cannot be modified or re-converted.
3. **Tenant Isolation Test**: Verify that customer listings and product selections in the quotation form are strictly isolated to the authenticated tenant.
4. **Pest Feature Test Class**:
   - `tests/Feature/Filament/QuotationResourceTest.php`
