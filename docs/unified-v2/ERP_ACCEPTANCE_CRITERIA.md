# Unified ERP v2.0: Acceptance Criteria Specification
**KC Retail ERP | Quality Assurance & Production Verification Standards**  
**Controlling Work Order:** `docs/master-work-order/KC_Retail_ERP_Unified_v2.0_Specification_Work_Order.md`  
**Repository Baseline:** Branch `step-13a-watchdog-audit` (Commit: `17d0264`)  
**Auditor & QA Lead:** Principal Quality Architect & Lead Systems Auditor  
**Publication Date:** November 2026  
**Document Authority:** Authoritative Quality & Acceptance Specification (v2.0)  

---

## 1. Global Acceptance & Quality Criteria

Every feature, module, and integration introduced into KC Retail ERP Unified v2.0 must satisfy the following global engineering standards prior to deployment into staging or production environments:

1. **Static Analysis & Type Safety:** `vendor/bin/phpstan analyse` must pass at **PHPStan Level 8** with exactly 0 errors and 0 baseline overrides.
2. **Code Formatting Style:** `vendor/bin/pint --test` must exit with 0 formatting violations.
3. **Database Test Execution:** All database tests must run on **PostgreSQL 17 as `app_rw`** (unprivileged connection). SQLite test doubles or mock databases are strictly forbidden.
4. **Zero Financial Floating-Point Arithmetic:** Any pull request containing PHP `float`, `double`, or non-brick/math monetary calculations must be rejected automatically by CI.
5. **Fail-Closed RLS Enforcement:** `php artisan db:check-rls` must exit with return code `0`, confirming forced RLS, composite keys, and restrictive policies across 100% of tenant tables.

---

## 2. Multi-Tenancy & Data Isolation Criteria

```
+---------------------------------------------------------------------------------------------------------------+
|                                      TENANT ISOLATION ACCEPTANCE CRITERIA                                     |
+--------------------------+----------------------------------------------------+-------------------------------+
| Criteria ID              | Verifiable Condition                               | Pass / Fail Verification Test |
+--------------------------+----------------------------------------------------+-------------------------------+
| **AC-TEN-01**            | An unauthenticated SQL query executed as `app_rw`  | PASS: Query returns exactly 0 |
|                          | against any tenant table without setting context   | rows; zero data leaked.       |
|                          | returns exactly 0 rows.                            |                               |
| **AC-TEN-02**            | A query with `app.current_tenant_id` set to Tenant A| PASS: Query returns only      |
|                          | cannot read, update, or delete rows of Tenant B.   | Tenant A rows.                |
| **AC-TEN-03**            | Attempting to insert a child row with a mismatched | PASS: Database throws foreign |
|                          | `tenant_id` relative to its parent row fails.      | key constraint violation.     |
| **AC-TEN-04**            | SuperAdmin support token with `is_read_only = true`| PASS: Write throws HTTP 403;  |
|                          | is blocked from executing any mutating HTTP action.| read succeeds.                |
+--------------------------+----------------------------------------------------+-------------------------------+
```

---

## 3. Accounting & General Ledger Consistency Criteria

```
+---------------------------------------------------------------------------------------------------------------+
|                                    ACCOUNTING CONSISTENCY ACCEPTANCE CRITERIA                                 |
+--------------------------+----------------------------------------------------+-------------------------------+
| Criteria ID              | Verifiable Condition                               | Pass / Fail Verification Test |
+--------------------------+----------------------------------------------------+-------------------------------+
| **AC-ACC-01**            | Every commercial document posting (sale, receipt,  | PASS: Journal line sum(DR) == |
|                          | transfer, landed cost) generates balanced journals.| sum(CR) within same tx.       |
| **AC-ACC-02**            | Direct `UPDATE` or `DELETE` on posted journal      | PASS: PostgreSQL trigger      |
|                          | entries or lines is physically blocked.            | throws [SEC-IMMUTABLE] error. |
| **AC-ACC-03**            | The daily drift between Accounts Receivable Control| PASS: Watchdog reports drift  |
|                          | Account (1200) and customer subledgers is 0.00 BDT.| = 0.00 BDT for 7 days.        |
| **AC-ACC-04**            | The daily drift between Accounts Payable Control   | PASS: Watchdog reports drift  |
|                          | Account (2000) and supplier subledgers is 0.00 BDT.| = 0.00 BDT for 7 days.        |
| **AC-ACC-05**            | Trial Balance generated for any date range balances| PASS: Total Debits == Total   |
|                          | down to the exact paisa (0.00 variance).           | Credits exactly.              |
+--------------------------+----------------------------------------------------+-------------------------------+
```

---

## 4. Inventory Costing & Valuation Criteria

```
+---------------------------------------------------------------------------------------------------------------+
|                                     INVENTORY VALUATION ACCEPTANCE CRITERIA                                   |
+--------------------------+----------------------------------------------------+-------------------------------+
| Criteria ID              | Verifiable Condition                               | Pass / Fail Verification Test |
+--------------------------+----------------------------------------------------+-------------------------------+
| **AC-INV-01**            | Inward Goods Receipt recalculates warehouse AVCO   | PASS: Unit cost matches       |
|                          | moving average cost basis exactly:                 | (OldVal + NewVal) / (OldQty + |
|                          | $\text{Unit Cost} = \frac{V_0 + V_1}{Q_0 + Q_1}$.  | NewQty) rounded half-up to 4. |
| **AC-INV-02**            | Outward sale under FIFO deplanes the oldest active | PASS: Earliest layer depleted;|
|                          | stock layer before touching subsequent layers.     | residual qty updated.         |
| **AC-INV-03**            | Warehouse stock cannot drop below zero unless      | PASS: Trigger rejects negative|
|                          | explicitly flagged as an offline POS sync order.   | stock for backend orders.     |
| **AC-INV-04**            | Landed cost voucher absorbs customs/freight expense| PASS: Stock layer unit cost   |
|                          | into product valuation layer proportionally.       | increased by exact surcharge. |
+--------------------------+----------------------------------------------------+-------------------------------+
```

---

## 5. Offline POS & Hardware Bridge Criteria

```
+---------------------------------------------------------------------------------------------------------------+
|                                     OFFLINE POS & BRIDGE ACCEPTANCE CRITERIA                                  |
+--------------------------+----------------------------------------------------+-------------------------------+
| Criteria ID              | Verifiable Condition                               | Pass / Fail Verification Test |
+--------------------------+----------------------------------------------------+-------------------------------+
| **AC-POS-01**            | Disconnecting network cable during checkout allows | PASS: Cashier can tender sale,|
|                          | cashier to complete sale and save to IndexedDB.    | receipt prints, drawer kicks. |
| **AC-POS-02**            | Restoring network pushes pending sales via batch   | PASS: All pending sales sync; |
|                          | sync API (`POST /api/pos/batch-sync`).             | status updated to 'synced'.   |
| **AC-POS-03**            | If 1 sale in a batch of 10 fails validation, the   | PASS: 9 sales committed via   |
|                          | remaining 9 sales must still commit successfully.  | isolated savepoints; 1 flagged.|
| **AC-POS-04**            | Submitting the same batch twice (network retry)    | PASS: Duplicate request returns|
|                          | does not create duplicate sales or journals.       | cached response; 0 extra GL.  |
| **AC-POS-05**            | Local Go bridge responds on `127.0.0.1:9100` within| PASS: Bridge healthcheck returns|
|                          | 15ms and executes raw ESC/POS receipt printing.    | HTTP 200 with device status.  |
+--------------------------+----------------------------------------------------+-------------------------------+
```

---

## 6. Bangladesh Statutory VAT & Mushak Criteria

```
+---------------------------------------------------------------------------------------------------------------+
|                                    STATUTORY TAX & MUSHAK ACCEPTANCE CRITERIA                                 |
+--------------------------+----------------------------------------------------+-------------------------------+
| Criteria ID              | Verifiable Condition                               | Pass / Fail Verification Test |
+--------------------------+----------------------------------------------------+-------------------------------+
| **AC-TAX-01**            | Mushak 6.3 Tax Invoice prints bilingual text       | PASS: Output conforms to      |
|                          | (Bengali / English) with valid alphanumeric series.| statutory NBR format layout.  |
| **AC-TAX-02**            | QR Code on Mushak 6.3 parses to verified taxpayer  | PASS: Mobile scanner decodes  |
|                          | BIN, Invoice Number, Date, Total, and VAT amount.  | valid NBR verification string.|
| **AC-TAX-03**            | Mushak 6.6 Withholding Tax Certificate generated   | PASS: VDS cert generated and  |
|                          | whenever VAT is deducted at source from supplier.  | tied to supplier payment.     |
| **AC-TAX-04**            | Mushak 9.1 Monthly VAT Return aggregates sales and | PASS: Total Output/Input VAT  |
|                          | purchases into correct statutory sub-form lines.   | matches General Ledger tax.   |
+--------------------------+----------------------------------------------------+-------------------------------+
```

---

## 7. Performance & Scalability Criteria

```
+---------------------------------------------------------------------------------------------------------------+
|                                     PERFORMANCE & SCALABILITY ACCEPTANCE CRITERIA                             |
+--------------------------+----------------------------------------------------+-------------------------------+
| Criteria ID              | Verifiable Condition                               | Pass / Fail Verification Test |
+--------------------------+----------------------------------------------------+-------------------------------+
| **AC-PERF-01**           | POS Barcode Scan Lookup responds in < 50ms from    | PASS: Local Dexie.js query    |
|                          | local IndexedDB catalog containing 50,000 SKUs.    | benchmark < 15ms.             |
| **AC-PERF-02**           | POS Batch Sync API handles a batch of 20 sales     | PASS: Server processing time  |
|                          | (with stock and GL postings) in < 800ms.           | < 500ms on PostgreSQL 17.     |
| **AC-PERF-03**           | Filament Product List View renders 25 rows under a | PASS: Total TTFB < 200ms      |
|                          | tenant catalog of 100,000 active SKUs.             | verified via browser metrics. |
+--------------------------+----------------------------------------------------+-------------------------------+
```

---

## 8. Backup, Disaster Recovery & Operations Criteria

```
+---------------------------------------------------------------------------------------------------------------+
|                                    OPERATIONS & RECOVERY ACCEPTANCE CRITERIA                                  |
+--------------------------+----------------------------------------------------+-------------------------------+
| Criteria ID              | Verifiable Condition                               | Pass / Fail Verification Test |
+--------------------------+----------------------------------------------------+-------------------------------+
| **AC-OPS-01**            | Daily automated ZFS snapshot executes at 02:00 AM  | PASS: Snapshot verified in    |
|                          | without taking container or database offline.      | `zfs list -t snapshot`.       |
| **AC-OPS-02**            | Restoring PostgreSQL from latest WAL archive       | PASS: Database restored to    |
|                          | recovers all committed transactions up to 5 min.   | within 5 min of target time.  |
| **AC-OPS-03**            | Inadvertent CLI command run as root on server does | PASS: Error handler suppresses|
|                          | not crash web server with Blade touch() 500 error. | warning; HTTP 200 delivered.  |
+--------------------------+----------------------------------------------------+-------------------------------+
```

---

## 9. User Acceptance Testing (UAT) Sign-Off Requirements

Prior to commercial go-live, User Acceptance Testing must be conducted on staging (`https://dev.kcerp.com`) with the following sign-off protocol:

1. **Cashier Day in the Life Drill:**
   * Open register shift with 5,000 BDT opening float.
   * Ring 50 standard retail sales (split tenders: Cash, Card, bKash).
   * Perform 1 supervisor-authorized price override and 1 item void.
   * Sever network for 15 minutes; ring 10 offline sales; verify thermal printing and cash drawer kick.
   * Reconnect network; verify automatic batch sync and savepoint isolation.
   * Execute mid-shift cash drop of 10,000 BDT.
   * Close register shift; generate Z-Report; verify 0.00 BDT cash variance.
   * **Sign-off:** Retail Store Manager & Lead Cashier.

2. **Finance & Tax Reconciliation Drill:**
   * Run end-of-day General Ledger Trial Balance.
   * Verify Sales Revenue equals total Z-Report gross sales.
   * Verify Output VAT equals sum of Mushak 6.3 tax amounts.
   * Execute daily drift watchdog; confirm 0.00 BDT subledger variance.
   * **Sign-off:** Chief Financial Officer / Head of Accounts.
