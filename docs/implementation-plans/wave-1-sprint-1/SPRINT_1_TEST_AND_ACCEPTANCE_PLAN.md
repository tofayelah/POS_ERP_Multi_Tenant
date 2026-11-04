# SPRINT 1 TEST AND ACCEPTANCE PLAN
## KC Retail ERP | QA & Verification Framework

- **Target Sprint:** Wave 1 / Sprint 1: Procurement & Document UI Parity
- **Test Database:** PostgreSQL 17.4 (`current-postgres-1`), Connection: `app_rw`
- **Testing Framework:** Pest / PHPUnit 11 on Laravel 13 & PHP 8.4
- **Coverage Requirement:** 100% of new UI actions, domain integrations, and policy guards
- **Document Status:** IMPLEMENTATION PLANNING ONLY (Awaiting Human Approval)

---

## 1. Testing Strategy Overview

Sprint 1 introduces four critical presentation components (`PurchaseOrderResource`, `GoodsReceiptResource`, `QuotationResource`, and `ThreeWayMatchingWidget`). To ensure that the introduction of UI layers does not compromise the underlying PostgreSQL 17 forced RLS, immutability guarantees, or accounting integrity, testing is structured into five rigorous layers:

```
+---------------------------------------------------------------------------------+
| Layer 5: End-to-End Walkthrough & Triple Reconciliation (GL + Stock + Subledger) |
+---------------------------------------------------------------------------------+
| Layer 4: Filament v5 Resource & Widget Component Interaction Tests              |
+---------------------------------------------------------------------------------+
| Layer 3: Role-Based Access Control (RBAC) & Location Scoping Tests              |
+---------------------------------------------------------------------------------+
| Layer 2: PostgreSQL 17 Multi-Tenant Forced RLS & Composite Key Guards           |
+---------------------------------------------------------------------------------+
| Layer 1: Schema, DTO Validation & Domain Service Invariant Tests                |
+---------------------------------------------------------------------------------+
```

---

## 2. Test Suite Specification & File Breakdown

The following dedicated test classes will be implemented:

```
tests/
└── Feature/
    └── Filament/
        ├── PurchaseOrderResourceTest.php              [NEW]
        ├── GoodsReceiptResourceTest.php               [NEW]
        ├── QuotationResourceTest.php                  [NEW]
        ├── ThreeWayMatchingWidgetTest.php             [NEW]
        ├── ProcurementLocationScopingTest.php         [NEW]
        └── ProcurementDiscrepancyAndPartialReceiptTest.php [NEW]
```

---

## 3. Detailed Test Case Catalog

### 3.1 `PurchaseOrderResourceTest`
- **`test_purchase_order_resource_renders_list_page()`**:
  - Authenticates as `Procurement Manager`.
  - Asserts HTTP 200 on `PurchaseOrderResource::getUrl('index')`.
  - Verifies presence of columns: `order_number`, `supplier.name`, `grand_total`, `status`.
- **`test_procurement_manager_can_create_draft_purchase_order_via_form()`**:
  - Submits valid form payload with 2 line items.
  - Asserts record created in `purchase_orders` with status `draft`.
  - Asserts order number generated matching regex `^PO-\d{4}-\d{4}$`.
  - **Invariance Check**: Asserts `StockBalance::count()` and `StockMovement::count()` are completely unchanged.
- **`test_confirm_action_transitions_status_and_locks_order()`**:
  - Calls `confirm` action on draft PO.
  - Asserts status is `confirmed`.
  - Attempts to edit order lines via `EditPurchaseOrder`; asserts 403 Forbidden.
- **`test_unauthorized_user_cannot_create_or_confirm_purchase_order()`**:
  - Authenticates as `Cashier`.
  - Asserts 403 Forbidden accessing `PurchaseOrderResource::getUrl('create')`.
- **`test_cancel_action_allowed_only_when_zero_goods_receipts_exist()`**:
  - Confirmed PO with zero GRNs $\to$ `cancel` transitions to `cancelled`.
  - Confirmed PO with 1 partial GRN $\to$ `cancel` throws exception and blocks.

---

### 3.2 `GoodsReceiptResourceTest`
- **`test_goods_receipt_resource_renders_list_page()`**:
  - Authenticates as `Warehouse Manager`.
  - Asserts HTTP 200 on `GoodsReceiptResource::getUrl('index')`.
- **`test_selecting_purchase_order_pulls_remaining_unreceived_lines()`**:
  - Given PO for 200 units with 80 units already received.
  - Selecting PO in form auto-populates line item with `received_quantity = 120`.
- **`test_confirm_goods_receipt_posts_positive_stock_movements()`**:
  - Calls `confirm_grn` action on draft GRN for 100 units.
  - Asserts GRN status transitions to `received`.
  - Asserts `stock_movements` contains entry with `movement_type = 'GOODS_RECEIPT'` and `quantity = '100.0000'`.
  - Asserts `stock_balances.quantity_on_hand` increased by 100.
  - Asserts parent PO updated to `partially_received` or `received`.
- **`test_over_delivery_beyond_ordered_quantity_is_strictly_rejected()`**:
  - Attempts to receive 250 units on a 200 unit PO.
  - Asserts `ReceivedQuantityExceedsOrderException` thrown.
  - Asserts database rolled back; zero stock movements created.
- **`test_damaged_and_rejected_quantities_do_not_enter_sellable_stock()`**:
  - Submits GRN with 100 arriving units: 80 accepted, 15 damaged, 5 rejected.
  - Asserts sellable `StockBalance` increases by exactly 80 units, NOT 100.
  - Asserts damaged and rejected quantities are recorded in quarantine logs.

---

### 3.3 `QuotationResourceTest`
- **`test_quotation_resource_renders_list_page()`**:
  - Authenticates as `Sales Manager`.
  - Asserts HTTP 200 on `QuotationResource::getUrl('index')`.
- **`test_sales_manager_can_create_quotation()`**:
  - Submits quotation form with customer and 2 products.
  - Asserts record created with status `draft` and number `QT-YYYY-XXXX`.
  - Asserts zero stock reservations or financial postings.
- **`test_convert_to_sales_order_action_creates_linked_so()`**:
  - Calls `convert_to_so` on active quotation.
  - Asserts quotation status transitions to `converted`.
  - Asserts new `SalesOrder` created with `quotation_id` matching quote ID.
  - Asserts lines mapped 1:1 with `quotation_line_id` pointers.
- **`test_cannot_convert_expired_quotation()`**:
  - Sets `valid_until` to yesterday.
  - Asserts `convert_to_so` action is hidden or rejects execution.

---

### 3.4 `ThreeWayMatchingWidgetTest`
- **`test_widget_evaluates_exact_match_as_matched()`**:
  - PO: 100 units @ ৳150. GRN: 100 units. Bill: 100 units @ ৳150.
  - Asserts widget output: `status = 'MATCHED'`, `net_ppv = '0.00'`, `has_exceptions = false`.
- **`test_widget_detects_quantity_overbilling_exception()`**:
  - PO: 100 units. GRN: 80 units. Bill: 100 units.
  - Asserts widget output: `status = 'EXCEPTION'`, `has_exceptions = true`.
  - Asserts exception message: `"Billed qty (100) exceeds GRN received qty (80)"`.
  - Asserts bill `approve_bill` action is blocked.
- **`test_widget_detects_purchase_price_variance()`**:
  - PO: 100 units @ ৳100. GRN: 100 units. Bill: 100 units @ ৳110.
  - Asserts widget output: `status = 'EXCEPTION'`, `net_ppv = '1000.00'`.
- **`test_financial_controller_override_logs_audit_trail()`**:
  - Financial Controller submits override modal with justification.
  - Asserts bill approved and posted to GL.
  - Asserts `audit_logs` record created with event `supplier_bill.matching_override`.
  - Asserts audit hash chain integrity verified (`VerifyAuditHashChainService`).

---

### 3.5 `ProcurementLocationScopingTest`
- **`test_branch_manager_dhanmondi_cannot_view_or_receive_gulshan_goods()`**:
  - User scoped strictly to `Branch: Dhanmondi`.
  - Attempts to access GRN for `Warehouse: Gulshan`.
  - Asserts 403 Forbidden.
- **`test_multi_tenant_isolation_on_all_procurement_resources()`**:
  - Tenant A creates PO, GRN, and Quotation.
  - Authenticates as Tenant B Admin.
  - Asserts Tenant B cannot see, query, or reference Tenant A's documents.

---

## 4. Acceptance Criteria Checklist (Gate M6)

| # | Acceptance Criterion | Verification Method | Status |
| :--- | :--- | :--- | :--- |
| **AC-01** | `PurchaseOrderResource` functional in Filament with full line-item repeater and number series | Pest feature test & manual UI inspection | Required |
| **AC-02** | Purchase Orders **never** increment stock balances or post journals | Database ledger assertion | Required |
| **AC-03** | `GoodsReceiptResource` functional with partial delivery support | Multi-stage GRN feature test | Required |
| **AC-04** | Confirmed GRN increments stock and records batch/expiry | `StockBalance` & `StockMovement` assertions | Required |
| **AC-05** | Damaged/rejected quantities separated from sellable inventory | Inspection quarantine test | Required |
| **AC-06** | `QuotationResource` functional with conversion to `SalesOrder` | Quotation conversion feature test | Required |
| **AC-07** | Three-Way Matching Widget blocks over-billed invoices | Variance exception test | Required |
| **AC-08** | Matching exceptions require Financial Controller override with audit logging | Audit hash chain test | Required |
| **AC-09** | 100% PostgreSQL 17 Forced RLS compliance (`php artisan db:check-rls`) | RLS audit script | Required |
| **AC-10** | Zero Pint formatting violations (`vendor/bin/pint --test`) | Pint command | Required |
| **AC-11** | Zero PHPStan errors at Level 8 (`vendor/bin/phpstan analyse`) | PHPStan command | Required |

---

## 5. Execution Protocol

Run all tests on the development server (CT 104) connected as `app_rw`:
```bash
# Individual Resource Tests
php artisan test tests/Feature/Filament/PurchaseOrderResourceTest.php
php artisan test tests/Feature/Filament/GoodsReceiptResourceTest.php
php artisan test tests/Feature/Filament/QuotationResourceTest.php
php artisan test tests/Feature/Filament/ThreeWayMatchingWidgetTest.php
php artisan test tests/Feature/Filament/ProcurementLocationScopingTest.php

# Full Sprint 1 Suite
php artisan test --filter=Filament

# Static Analysis & Format
vendor/bin/pint --test
vendor/bin/phpstan analyse
```
