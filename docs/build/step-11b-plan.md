# Step 11B: Sales and Procurement Document Chains Implementation Plan

## 1. Context & Review Goals
- **Design Sections:** Design §1.3 (Scope Boundaries & Phasing), §8.2 (Items 4 & 6: Sales & Customer Receivables, Procurement & Supplier Payables), §5.4 (General Ledger & Double Entry), §9.1.
- **Review Items Closed:**
  - `NEW-34` (Full Document Chains: Quotation $\to$ Sales Order $\to$ Delivery Challan $\to$ Sales Invoice, and Purchase Order $\to$ Goods Receipt [GRN] $\to$ Vendor Bill).
- **Core Principles:**
  1. Each document has its own gapless number series (`QT`, `SO`, `DC`, `INV`, `PO`, `GRN`, `BILL`).
  2. Each document maintains explicit bidirectional links to its source header and source lines.
  3. Partial fulfillment is supported across all stages:
     - Sales Order lines track `ordered_quantity`, `delivered_quantity`, `invoiced_quantity`.
     - Delivery Challan lines track `quantity`, `invoiced_quantity`.
     - Purchase Order lines track `ordered_quantity`, `received_quantity`, `billed_quantity`.
     - Goods Receipt (GRN) lines track `received_quantity`, `billed_quantity`.
  4. Integration:
     - Delivery Challan confirmation creates outbound **Stock Movements** in the stock ledger.
     - Goods Receipt (GRN) confirmation creates inbound **Stock Movements** in the stock ledger.
     - Sales Invoice issuance posts balanced double-entry journals to the **GL** (Debit AR 1030 / Credit Revenue 4010) and creates open items in the **AR Subledger**.
     - Vendor Bill (Supplier Bill) confirmation posts balanced double-entry journals to the **GL** (Debit Expense/Inventory 5010 / Credit AP 2010) and creates open items in the **AP Subledger**.
  5. Immutability: Once confirmed, issued, or received, documents cannot be modified via `UPDATE` on financial/stock fields. Corrections require explicit cancellation or reversing adjustment documents.
  6. Multi-Tenancy & RLS: All tables enforce composite keys `(tenant_id, company_id, id)`, composite foreign keys, and forced PostgreSQL Row-Level Security.

---

## 2. PostgreSQL Schema Architecture

### Sales Document Chain Tables
1. **`quotations` & `quotation_lines`**
   - Headers: `id`, `tenant_id`, `company_id`, `branch_id`, `customer_id`, `quotation_number`, `quotation_date`, `valid_until`, `subtotal`, `tax_amount`, `grand_total`, `status` (`draft`, `sent`, `accepted`, `rejected`, `converted`, `cancelled`), `created_by`, `timestampsTz`.
   - Lines: `id`, `tenant_id`, `company_id`, `quotation_id`, `product_id`, `variant_id`, `quantity`, `unit_price`, `subtotal`, `tax_amount`, `total_amount`, `notes`.
   - Keys: `UNIQUE (tenant_id, id)`, `UNIQUE (tenant_id, company_id, id)`, `UNIQUE (tenant_id, company_id, quotation_number)`.

2. **`sales_orders` & `sales_order_lines`**
   - Headers: `id`, `tenant_id`, `company_id`, `branch_id`, `customer_id`, `quotation_id` (nullable), `order_number`, `order_date`, `expected_delivery_date`, `subtotal`, `tax_amount`, `grand_total`, `status` (`draft`, `confirmed`, `partially_delivered`, `delivered`, `closed`, `cancelled`), `created_by`, `timestampsTz`.
   - Lines: `id`, `tenant_id`, `company_id`, `sales_order_id`, `quotation_line_id` (nullable), `product_id`, `variant_id`, `ordered_quantity`, `delivered_quantity`, `invoiced_quantity`, `unit_price`, `subtotal`, `tax_amount`, `total_amount`.
   - Keys: `UNIQUE (tenant_id, id)`, `UNIQUE (tenant_id, company_id, id)`, `UNIQUE (tenant_id, company_id, order_number)`.

3. **`delivery_challans` & `delivery_challan_lines`**
   - Headers: `id`, `tenant_id`, `company_id`, `branch_id`, `warehouse_id`, `customer_id`, `sales_order_id`, `challan_number`, `challan_date`, `status` (`draft`, `confirmed`, `invoiced`, `cancelled`), `vehicle_number`, `driver_name`, `driver_phone`, `created_by`, `timestampsTz`.
   - Lines: `id`, `tenant_id`, `company_id`, `delivery_challan_id`, `sales_order_line_id`, `product_id`, `variant_id`, `quantity`, `invoiced_quantity`, `batch_number`, `notes`.
   - Keys: `UNIQUE (tenant_id, id)`, `UNIQUE (tenant_id, company_id, id)`, `UNIQUE (tenant_id, company_id, challan_number)`.

### Procurement Document Chain Tables
4. **`purchase_orders` & `purchase_order_lines`**
   - Headers: `id`, `tenant_id`, `company_id`, `branch_id`, `supplier_id`, `order_number`, `order_date`, `expected_delivery_date`, `subtotal`, `tax_amount`, `grand_total`, `status` (`draft`, `confirmed`, `partially_received`, `received`, `closed`, `cancelled`), `created_by`, `timestampsTz`.
   - Lines: `id`, `tenant_id`, `company_id`, `purchase_order_id`, `product_id`, `variant_id`, `ordered_quantity`, `received_quantity`, `billed_quantity`, `unit_price`, `subtotal`, `tax_amount`, `total_amount`.
   - Keys: `UNIQUE (tenant_id, id)`, `UNIQUE (tenant_id, company_id, id)`, `UNIQUE (tenant_id, company_id, order_number)`.

5. **`goods_receipts` & `goods_receipt_lines`**
   - Headers: `id`, `tenant_id`, `company_id`, `branch_id`, `warehouse_id`, `supplier_id`, `purchase_order_id`, `grn_number`, `receipt_date`, `challan_number`, `status` (`draft`, `received`, `billed`, `cancelled`), `created_by`, `timestampsTz`.
   - Lines: `id`, `tenant_id`, `company_id`, `goods_receipt_id`, `purchase_order_line_id`, `product_id`, `variant_id`, `received_quantity`, `billed_quantity`, `unit_cost`, `batch_number`, `expiry_date`.
   - Keys: `UNIQUE (tenant_id, id)`, `UNIQUE (tenant_id, company_id, id)`, `UNIQUE (tenant_id, company_id, grn_number)`.

6. **`supplier_bill_lines`**
   - Lines: `id`, `tenant_id`, `company_id`, `supplier_bill_id`, `goods_receipt_line_id` (nullable), `purchase_order_line_id` (nullable), `product_id`, `variant_id`, `quantity`, `unit_price`, `subtotal`, `tax_amount`, `total_amount`.
   - Keys: `UNIQUE (tenant_id, id)`, `UNIQUE (tenant_id, company_id, id)`.

---

## 3. Implementation Commits & Test-First Order

### Commit 1: Schema Migrations & Tenant Isolation
- **Test Written First:** `tests/Database/DocumentChainsSchemaTenantIsolationTest.php`
  - Asserts all 11 tables enforce tenant isolation under RLS.
  - Asserts cross-company references are strictly rejected by PostgreSQL composite FK constraints.
  - Asserts immutability triggers reject direct updates to confirmed/delivered/received document rows.
- **Implementation:**
  - Migrations `create_quotations_table`, `create_sales_orders_table`, `create_delivery_challans_table`, `create_purchase_orders_table`, `create_goods_receipts_table`, `create_supplier_bill_lines_table`, and document immutability triggers.
  - Eloquent models with composite keys, UUIDv7, and relationships.

### Commit 2: Order-to-Cash (O2C) Document Chain Engine (Sales)
- **Test Written First:** `tests/Feature/SalesDocumentChainFeatureTest.php`
  - Step 1: Drafts Quotation for 100 units of Product A at ৳1,000/unit (Total ৳100,000).
  - Step 2: Converts Quotation to Sales Order. Confirms Sales Order (draws `SO` series).
  - Step 3: Issues Partial Delivery Challan 1 for 60 units from Main Warehouse.
    - Confirms Delivery Challan (draws `DC` series).
    - Verifies Stock Movement Ledger records dispatch of 60 units (warehouse stock balance decremented).
    - Verifies Sales Order line: `delivered_quantity = 60`, status = `partially_delivered`.
  - Step 4: Issues Sales Invoice for Delivery Challan 1 (60 units):
    - Draws `INV` series.
    - Verifies double-entry GL journal posted: Debit AR `1030` ৳60,000 / Credit Revenue `4010` ৳60,000.
    - Verifies Subledger Open Item created with `direction = debit`, `remaining_amount = 60000.0000`.
    - Verifies Sales Order line: `invoiced_quantity = 60`.
  - Step 5: Issues Partial Delivery Challan 2 for remaining 40 units.
    - Confirms Delivery Challan. Stock movement records dispatch of 40 units.
    - Verifies Sales Order line: `delivered_quantity = 100`, status = `delivered`.
  - Step 6: Issues Sales Invoice for Delivery Challan 2 (40 units):
    - GL posted: Debit AR `1030` ৳40,000 / Credit Revenue `4010` ৳40,000.
    - Subledger Open Item created with `remaining_amount = 40000.0000`.
    - Verifies Sales Order status transitions to `closed`.
  - Step 7: Final End-to-End Reconciliation Assertions:
    - GL Account 1030 balance (৳100,000) $\equiv$ Subledger AR open items total (৳100,000).
    - Stock movement ledger balance decremented by exactly 100 units.
    - Rejects direct edits to confirmed delivery challans or issued sales orders.
- **Implementation:**
  - `App\Modules\Sales\Contracts\SalesDocumentChainContract`.
  - `App\Modules\Sales\Services\SalesDocumentChainService`.
  - DTOs for Quotation, Sales Order, Delivery Challan, and Invoice creation.

### Commit 3: Procure-to-Pay (P2P) Document Chain Engine (Procurement)
- **Test Written First:** `tests/Feature/ProcurementDocumentChainFeatureTest.php`
  - Step 1: Creates Purchase Order for 200 units of Raw Material at ৳500/unit (Total ৳100,000). Confirms PO (draws `PO` series).
  - Step 2: Partial Goods Receipt 1 (GRN): Receives 120 units into Central Warehouse.
    - Confirms GRN (draws `GRN` series).
    - Verifies Stock Movement Ledger records receipt of 120 units at ৳500/unit (warehouse balance incremented).
    - Verifies Purchase Order line: `received_quantity = 120`, status = `partially_received`.
  - Step 3: Issues Vendor Bill for GRN 1 (120 units):
    - Draws `BILL` series.
    - Verifies double-entry GL journal posted: Debit Purchase Expense/Inventory `5010` ৳60,000 / Credit AP `2010` ৳60,000.
    - Verifies Subledger Open Item created with `direction = credit`, `remaining_amount = 60000.0000`.
    - Verifies PO line: `billed_quantity = 120`.
  - Step 4: Partial Goods Receipt 2 (GRN): Receives remaining 80 units into Central Warehouse.
    - Confirms GRN. Stock movement records receipt of 80 units at ৳500/unit.
    - Verifies Purchase Order line: `received_quantity = 200`, status = `received`.
  - Step 5: Issues Vendor Bill for GRN 2 (80 units):
    - GL posted: Debit Expense/Inventory `5010` ৳40,000 / Credit AP `2010` ৳40,000.
    - Subledger Open Item created with `remaining_amount = 40000.0000`.
    - Verifies Purchase Order status transitions to `closed`.
  - Step 6: Final End-to-End Reconciliation Assertions:
    - GL Account 2010 balance (৳100,000) $\equiv$ Subledger AP open items total (৳100,000).
    - Stock movement ledger balance incremented by exactly 200 units.
    - Rejects direct edits to confirmed GRNs or confirmed purchase orders.
- **Implementation:**
  - `App\Modules\Procurement\Contracts\ProcurementDocumentChainContract`.
  - `App\Modules\Procurement\Services\ProcurementDocumentChainService`.
  - DTOs for Purchase Order, Goods Receipt, and Vendor Bill creation.

### Commit 4: Cancellation Workflows & Over-Fulfillment Safeguards
- **Test Written First:** `tests/Database/DocumentCancellationAndGuardTest.php`
  - Asserts delivery challan cannot deliver more than unfulfilled sales order quantity (`DeliveredQuantityExceedsOrderException`).
  - Asserts GRN cannot receive more than unfulfilled purchase order quantity (`ReceivedQuantityExceedsOrderException`).
  - Asserts cancelling an unfulfilled or partially fulfilled sales order / purchase order releases pending commitments and sets status to `cancelled`.
  - Asserts that confirmed delivery challans with stock movements cannot be cancelled without generating a return/reversal.
- **Implementation:**
  - Validation guards and domain exceptions in Sales and Procurement services.
  - Immutability guards on confirmed business documents.

---

## 4. Verification Commands (CT 104)

```bash
# 1. Run migrations as pgsql_owner
DB_DATABASE=erp php artisan migrate --database=pgsql_owner --force
DB_DATABASE=erp_test php artisan migrate --database=pgsql_owner --force

# 2. Check Row-Level Security
php artisan db:check-rls

# 3. Code formatting
vendor/bin/pint --test

# 4. Static analysis
vendor/bin/phpstan analyse --no-progress

# 5. Run feature tests as app_rw
php artisan test tests/Database/DocumentChainsSchemaTenantIsolationTest.php
php artisan test tests/Feature/SalesDocumentChainFeatureTest.php
php artisan test tests/Feature/ProcurementDocumentChainFeatureTest.php
php artisan test tests/Database/DocumentCancellationAndGuardTest.php
```
