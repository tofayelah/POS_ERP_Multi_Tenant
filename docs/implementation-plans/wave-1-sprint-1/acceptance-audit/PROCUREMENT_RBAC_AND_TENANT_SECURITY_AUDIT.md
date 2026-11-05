# PROCUREMENT RBAC, LOCATION SCOPES & TENANT SECURITY AUDIT
## KC Retail ERP | Architecture & Security Governance
**Wave 1 / Sprint 1 Acceptance Audit Deliverable**

- **Document ID:** `SEC-W1S1-004`
- **Audit Date:** 2026-11-05
- **Auditor:** Independent Security Reviewer & Principal Architect
- **Target Branch:** `step-14-procurement-ui`
- **Execution Target:** Dev Server Container CT 104 (`dev.kcerp.com`), PostgreSQL 17.4

---

## 1. Executive Summary

This report presents the security, role-based access control (RBAC), location scoping, and multi-tenant isolation audit for the procurement components delivered in Wave 1 / Sprint 1.

### Overall Assessment
- **PostgreSQL 17 Forced RLS**: **PASS**. All 68 tenant tables enforce forced RLS with fail-closed security for `app_rw` and `reporting_ro`.
- **Location Scoping**: **PASS**. Cross-branch and cross-warehouse document access is blocked via `EnforcesLocationScope`.
- **Document Immutability**: **PASS**. Confirmed/posted POs, receipts, and invoices are strictly immutable.
- **Identified Gap**: `SupplierBillPolicy` was not authored as a standalone policy class; permissions are currently enforced through inline action callbacks.

---

## 2. RBAC Permission Matrix for Procure-to-Pay

The procurement workflow enforces strict separation of responsibilities across business roles:

| Action / Document Stage | Tenant Admin / Owner | Procurement Manager | Warehouse Manager | Accounts Payable Officer | Financial Controller | Auditor (Read-Only) |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Create Purchase Order** | Yes | Yes | No | No | No | No |
| **Confirm Purchase Order** | Yes | Yes | No | No | No | No |
| **Cancel Purchase Order (Draft)** | Yes | Yes | No | No | No | No |
| **Cancel PO (Partially Received)** | **BLOCKED** | **BLOCKED** | **BLOCKED** | **BLOCKED** | **BLOCKED** | **BLOCKED** |
| **Create Goods Receipt (Draft)** | Yes | No | Yes | No | No | No |
| **Confirm & Post GRN to Stock** | Yes | No | Yes | No | No | No |
| **Edit/Delete Confirmed GRN** | **BLOCKED** | **BLOCKED** | **BLOCKED** | **BLOCKED** | **BLOCKED** | **BLOCKED** |
| **Create Supplier Bill** | Yes | No | No | Yes | Yes | No |
| **Approve Bill (Clean Match)** | Yes | No | No | Yes | Yes | No |
| **Approve Bill with Variance Override** | Yes | No | No | **BLOCKED** | **Yes (Logged)** | No |
| **View Audit Trail** | Yes | Yes | Yes | Yes | Yes | **Yes** |

---

## 3. Policy-by-Policy Code Inspection

### 3.1 `PurchaseOrderPolicy`
- **Path:** [`app/Policies/PurchaseOrderPolicy.php`](file:///d:/POS_ERP_Multi_Tenant/app/Policies/PurchaseOrderPolicy.php)
- **Trait:** `EnforcesLocationScope`
- **Verified Guards:**
  - `viewAny()`: Restricts listing to authorized roles.
  - `view()`: Verifies `$this->canAccessLocation($user, $po->company_id, $po->branch_id)`.
  - `update()`: Fails immediately if `$po->status !== 'draft'`. Confirmed orders cannot be edited.
  - `deleteAny()`: Hardcoded `return false;`. Bulk deletions are prohibited to maintain statutory audit trails.
  - `cancel()`:
    ```php
    if (in_array($po->status, ['partially_received', 'received', 'closed', 'cancelled'], true)) {
        return false;
    }
    foreach ($po->lines as $line) {
        if (BigDecimal::of($line->received_quantity)->isPositive()) {
            return false;
        }
    }
    ```
    Guarantees that an order that has delivered goods into the warehouse can never be cancelled.

### 3.2 `GoodsReceiptPolicy`
- **Path:** [`app/Policies/GoodsReceiptPolicy.php`](file:///d:/POS_ERP_Multi_Tenant/app/Policies/GoodsReceiptPolicy.php)
- **Trait:** `EnforcesLocationScope`
- **Verified Guards:**
  - `create()`: Restricted to `Warehouse Manager`, `Tenant Admin`, and `Tenant Owner`. Procurement officers cannot create warehouse receipts (prevents phantom intake).
  - `update()`: Fails if `$grn->status !== 'draft'`. Once stock is posted (`received`), records are immutable.
  - `deleteAny()`: Bulk delete explicitly blocked.
  - Location Scoping: Warehouse officers can only view or confirm receipts for branches/warehouses within their assigned location scope.

### 3.3 `QuotationPolicy`
- **Path:** [`app/Policies/QuotationPolicy.php`](file:///d:/POS_ERP_Multi_Tenant/app/Policies/QuotationPolicy.php)
- **Trait:** `EnforcesLocationScope`
- **Verified Guards:**
  - `convert()`: Hard-blocks conversion if `$quotation->valid_until->isPast()`.
  - Immutability: Converted quotations cannot be edited or cancelled.

### 3.4 Missing Policy: `SupplierBillPolicy`
- **Audit Observation:** `SupplierBillResource` currently enforces permissions inline within its table action closures:
  ```php
  $canOverride = $user && ($user->hasRole('Financial Controller') || $user->hasRole('Tenant Admin') || $user->hasRole('Tenant Owner'));
  ```
- **Security Assessment**: While the inline check correctly blocks unauthorized overrides in the UI, standard Laravel policy authorization (`viewAny`, `view`, `create`, `update`, `delete`, `approve`) should reside in a dedicated `SupplierBillPolicy` to safeguard API endpoints, Livewire component calls, and background jobs.
- **Remediation**: Author and register `app/Policies/SupplierBillPolicy.php` in Sprint 2.

---

## 4. Multi-Tenant Isolation & Forced RLS Audit

### 4.1 PostgreSQL 17 RLS Configuration Check
The automated RLS checker command was executed directly against PostgreSQL 17 on CT 104:
```bash
php artisan db:check-rls
```
**Raw Console Output:**
```text
Checking Row-Level Security configuration on connection [pgsql_owner]...
✓ RLS Verification PASSED: All 68 tenant tables enforce forced RLS with valid policies for app_rw and reporting_ro.
```

### 4.2 Fail-Closed Invariant
Every procurement table (`purchase_orders`, `purchase_order_lines`, `goods_receipts`, `goods_receipt_lines`, `supplier_bills`, `supplier_bill_lines`) satisfies the four non-negotiable multi-tenant rules:
1. `tenant_id` column present on every table.
2. Composite uniqueness: `UNIQUE (tenant_id, company_id, id)`.
3. Forced RLS enabled: `ALTER TABLE ... FORCE ROW LEVEL SECURITY`.
4. Fail-closed policy: `current_setting('app.current_tenant_id', true)::uuid = tenant_id`. If tenant context is unset, queries return 0 rows.

### 4.3 Empirical Test Evidence
Verified via [`tests/Database/TenantIsolationTest.php`](file:///d:/POS_ERP_Multi_Tenant/tests/Database/TenantIsolationTest.php) (**10 passed, 31 assertions**):
- Tenant A cannot read Tenant B procurement records via Eloquent or raw SQL.
- Tenant A cannot update, delete, or insert records with Tenant B ID.
- Missing tenant context returns zero rows.
- Application connects strictly as `app_rw`, never as table owner.

---

## 5. Security & RBAC Recommendations for Sprint 2

1. **Implement `SupplierBillPolicy`**:
   Create `app/Policies/SupplierBillPolicy.php` enforcing:
   - `viewAny()`: Restricted to Accounts Payable, Financial Controller, Tenant Admin, Auditor.
   - `create()`: Restricted to Accounts Payable, Tenant Admin.
   - `approve()`: Clean match approval allowed for AP Officer and Controller.
   - `override()`: Restricted strictly to `Financial Controller`, `Tenant Admin`, and `Tenant Owner`.
2. **Register in `AppServiceProvider`**:
   Add `Gate::policy(SupplierBill::class, SupplierBillPolicy::class);`.

---
*End of Procurement RBAC and Tenant Security Audit.*
