# Unified ERP v2.0.1: Complete 23-Domain Module Specification
**KC Retail ERP | Authoritative Functional & Technical Domain Specifications**  
**Controlling Work Order:** `docs/master-work-order/KC_Retail_ERP_Unified_v2.0_Specification_Work_Order.md`  
**Revision:** v2.0.1 (Reconciled & Expanded Specification)  
**Publication Date:** November 2026  
**Auditor & Lead Architect:** Principal Software Architect & ERP Solution Lead  
**Document Classification:** Authoritative Specification Template (23 Full Domains)  

---

## Architecture Navigation & Domain Directory

```
+---------------------------------------------------------------------------------------------------------------+
|                                      23 FUNCTIONAL DOMAIN DIRECTORY                                           |
+----+--------------------------------+-------------------+----------+------------------------------------------+
| #  | Module Name                    | Module ID         | Priority | Implementation Status                    |
+----+--------------------------------+-------------------+----------+------------------------------------------+
| 01 | Platform & Tenant Foundation   | MOD-PLAT-01       | P0       | VERIFIED IMPLEMENTED                     |
| 02 | Multi-Tenancy & Forced RLS     | MOD-SEC-RLS       | P0       | VERIFIED IMPLEMENTED                     |
| 03 | User Management, RBAC & Scopes | MOD-SEC-RBAC      | P0       | VERIFIED IMPLEMENTED                     |
| 04 | Security & Audit Trail         | MOD-SEC-AUD       | P0       | VERIFIED IMPLEMENTED                     |
| 05 | Product & Category Catalog     | MOD-CAT-PROD      | P0       | VERIFIED IMPLEMENTED                     |
| 06 | Inventory & Stock Costing      | MOD-INV-VAL       | P0       | VERIFIED IMPLEMENTED                     |
| 07 | POS, Retail Sales & Shift Ops  | MOD-POS-OPS       | P0       | VERIFIED IMPLEMENTED                     |
| 08 | Procurement & Document Chains  | MOD-PROC-DOCS     | P1       | PARTIALLY IMPLEMENTED (UI in Sprint 1)   |
| 09 | Supplier Management & AP       | MOD-SUP-AP        | P1       | PARTIALLY IMPLEMENTED (UI in Sprint 1)   |
| 10 | Customer Management & AR       | MOD-CUST-AR       | P0       | VERIFIED IMPLEMENTED                     |
| 11 | General Ledger & Accounting    | MOD-FIN-GL        | P0       | VERIFIED IMPLEMENTED                     |
| 12 | Subledgers & Reconciliation    | MOD-FIN-SUB       | P0       | VERIFIED IMPLEMENTED                     |
| 13 | Bangladesh VAT & Statutory Tax | MOD-TAX-NBR       | P0       | VERIFIED (Software); SDC Lab BLOCKED (P1)|
| 14 | Import & International Trade   | MOD-IMP-INT       | P1       | PARTIALLY IMPLEMENTED / SPEC COMPLETE    |
| 15 | Branch & Warehouse Management  | MOD-LOC-HIER      | P0       | VERIFIED IMPLEMENTED                     |
| 16 | CRM, Loyalty & Promotions      | MOD-CRM-LOY       | P2       | PLANNED (Release Wave 2)                 |
| 17 | HRM & Payroll Management       | MOD-HRM-PAY       | P2       | PLANNED (Release Wave 2)                 |
| 18 | E-Commerce & Omnichannel Sync  | MOD-ECOM-OMNI     | P3       | PLANNED (Release Wave 3)                 |
| 19 | Reporting & BI Dashboards      | MOD-REP-BI        | P0       | VERIFIED IMPLEMENTED                     |
| 20 | Workflow & Approval Engine     | MOD-WF-APPR       | P1       | PARTIALLY IMPLEMENTED (UI in Sprint 1)   |
| 21 | Hardware Bridge & Peripherals  | MOD-BRG-HW        | P0       | VERIFIED IMPLEMENTED                     |
| 22 | Notifications & Outbox Relay   | MOD-NOTIF-BUS     | P0       | VERIFIED IMPLEMENTED                     |
| 23 | Backup, Recovery & Operations  | MOD-OPS-DR        | P0       | VERIFIED (Snapshots); PITR Drill pending |
+----+--------------------------------+-------------------+----------+------------------------------------------+
```

---

## Domain Specifications (Modules 01 to 23)

---

### Module 01: Platform & Tenant Foundation
* **Module ID:** `MOD-PLAT-01` | **Priority:** P0 | **Status:** `VERIFIED IMPLEMENTED`
* **Business Purpose:** Centralized multi-tenant registration, subscription quotas, and lifecycle state automation for subscriber retail businesses.
* **Scope:** Included: Tenant creation, state machine (`trial`, `active`, `suspended`, `grace`, `archived`), plan quotas (users, branches, registers), SuperAdmin platform panel. Excluded: Public self-service credit card checkout (manual sales onboarding in Phase 1).
* **Actors & Permissions:** Platform SuperAdmin (Cross-tenant provisioning, plan upgrades, support grants); Tenant Owner (Root admin of tenant); Tenant Admin.
* **Workflows:** Provision Tenant $\rightarrow$ Assign UUIDv7 $\rightarrow$ Seed default Chart of Accounts and Owner user $\rightarrow$ Active state. Suspension: Inactive billing sets state to `suspended` $\rightarrow$ `EnforceTenantState` middleware blocks mutating requests (`HTTP 403`).
* **Data Model:** `tenants`, `plans`, `tenant_subscriptions`, `tenant_plan_limits`. Constraints: `UNIQUE(subdomain)`.
* **Services & Boundaries:** `app/Core/Tenancy/TenantService.php`, `app/Core/Tenancy/TenantContext.php`, `app/Core/Http/Middleware/EnforceTenantState.php`.
* **User Interface:** `app/Filament/Platform/Resources/Tenants/TenantResource.php` (Platform Panel).
* **APIs & Integrations:** Internal resolution via hostname or path prefix `/admin/{tenant}`.
* **Accounting & Tax:** Sets the required `tenant_id` context for all downstream General Ledger postings.
* **Reports & KPIs:** Active Tenants Count, Churn Rate, Branch License Quota Utilization.
* **Security & Audit:** State transitions write immutable records to `audit_logs`.
* **Tests:** `tests/Feature/TenantLifecycleTest.php` (8 tests passing on PostgreSQL 17).
* **Acceptance Criteria:** Mutating request to suspended tenant returns `HTTP 403 Forbidden`.
* **Dependencies & Risks:** Subdomain casing sensitivity (Mitigated by `strtolower()` normalization).
* **Delivery Priority:** P0 (Completed and Verified).

---

### Module 02: Multi-Tenancy & Row-Level Security (RLS)
* **Module ID:** `MOD-SEC-RLS` | **Priority:** P0 | **Status:** `VERIFIED IMPLEMENTED`
* **Business Purpose:** Database-engine cryptographic data isolation between competing retail businesses sharing a single PostgreSQL cluster.
* **Scope:** Included: Forced RLS on all 68 tenant tables, composite foreign keys `(tenant_id, ...)`, dual database connection segregation (`pgsql_owner` for DDL, `app_rw` for runtime), automated RLS audit command `db:check-rls`.
* **Actors & Permissions:** Runtime Application (`app_rw`, unprivileged, denied DDL); Migration Runner (`pgsql_owner`, privileged owner).
* **Workflows:** Request starts $\rightarrow$ `TenantContext::set($tenant)` sets session variable `set_config('app.current_tenant_id', id, false)` $\rightarrow$ PostgreSQL engine restricts row visibility. Request ends $\rightarrow$ `TenantContext::clear()`.
* **Data Model:** `TenantMigration::createTenantTable()` creates:
  `CREATE POLICY tenant_isolation_policy ON {table} FOR ALL TO app_rw, reporting_ro USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid) WITH CHECK (...)`.
* **Services & Boundaries:** `app/Core/Database/TenantMigration.php`, `app/Console/Commands/CheckRlsCommand.php`.
* **User Interface:** None (Database engine level).
* **APIs & Integrations:** Transparent to REST APIs and Filament admin.
* **Accounting & Tax:** Eliminates cross-tenant data leaks and preserves statutory audit boundaries.
* **Reports & KPIs:** RLS Coverage Report: 68/68 tables verified.
* **Security & Audit:** CI enforces `php artisan db:check-rls` on every build.
* **Tests:** `tests/Database/TenantIsolationTest.php` (8 tests passing), `tests/Database/CompositeForeignKeyTest.php` (14 tests passing).
* **Acceptance Criteria:** Unauthenticated SQL query executed as `app_rw` without setting context returns 0 rows.
* **Dependencies & Risks:** Connection pooling context contamination (Mitigated via per-request cleanup and `is_local = true` inside transactions).
* **Delivery Priority:** P0 (Completed and Verified).

---

### Module 03: User Management, RBAC & Scopes
* **Module ID:** `MOD-SEC-RBAC` | **Priority:** P0 | **Status:** `VERIFIED IMPLEMENTED`
* **Business Purpose:** Enterprise identity management, role-based authorization, and location-scoped access control across companies and branches.
* **Scope:** Included: Spatie multi-tenant team RBAC (`team_id = tenant_id`), location scoping (`user_location_scopes`), company consistency checks.
* **Actors & Permissions:** SuperAdmin, Tenant Owner, Tenant Admin, Branch Manager, Warehouse Manager, Cashier, Auditor.
* **Workflows:** User signs in $\rightarrow$ Tenant membership verified $\rightarrow$ Active location scopes loaded $\rightarrow$ Filament and API operations filtered by assigned `company_id` and `branch_id`.
* **Data Model:** `users`, `roles`, `permissions`, `model_has_roles`, `tenant_memberships`, `user_location_scopes`.
* **Services & Boundaries:** `app/Models/User.php`, `app/Models/UserLocationScope.php`, `app/Providers/Filament/AdminPanelProvider.php`.
* **User Interface:** `app/Filament/Resources/UserResource.php`, Filament profile & team switcher.
* **APIs & Integrations:** Sanctum API token authentication for POS terminals.
* **Accounting & Tax:** Blocks unauthorized operators from viewing financial ledgers or tax reports.
* **Reports & KPIs:** User Activity Log, Branch Staff Allocation List.
* **Security & Audit:** Password hashing with bcrypt (cost factor 12); TOTP 2FA support; session rotation on login.
* **Tests:** `tests/Feature/RbacBranchScopeTest.php` (16 tests passing).
* **Acceptance Criteria:** Cashier assigned to Branch A cannot view or tender sales for Branch B.
* **Dependencies & Risks:** Permissions desynchronization (Mitigated via database team scope verification).
* **Delivery Priority:** P0 (Completed and Verified).

---

### Module 04: Security & Append-Only Audit Trail
* **Module ID:** `MOD-SEC-AUD` | **Priority:** P0 | **Status:** `VERIFIED IMPLEMENTED`
* **Business Purpose:** Tamper-evident logging of all sensitive administrative actions, permission shifts, and financial adjustments.
* **Scope:** Included: Eloquent mutating event observer, SHA-256 hash-chained log entries, support access authorization tokens, OWASP security headers.
* **Actors & Permissions:** System Observers (Automated append); Auditor (Read-only review); SuperAdmin (Support ticket access).
* **Workflows:** Record created/updated/deleted $\rightarrow$ `AuditObserver` gathers old/new values, IP, user ID $\rightarrow$ Inserts to `audit_logs` $\rightarrow$ Trigger `trg_prevent_audit_update` prevents modification.
* **Data Model:** `audit_logs`, `support_access_grants`, `support_access_logs`.
* **Services & Boundaries:** `app/Models/AuditLog.php`, `app/Observers/AuditObserver.php`, `app/Core/Http/Middleware/VerifySupportAccessActive.php`.
* **User Interface:** `app/Filament/Resources/AuditLogResource.php`.
* **APIs & Integrations:** Internal observer pipeline; support token verification middleware.
* **Accounting & Tax:** Serves as the primary evidence repository for statutory tax audits.
* **Reports & KPIs:** Security Incidents Log, Support Access Duration Reports.
* **Security & Audit:** Database trigger rejects `UPDATE` and `DELETE` on `audit_logs`.
* **Tests:** `tests/Database/AuditTrailHashChainTest.php` (6 tests passing), `tests/Feature/SupportAccessAuditTest.php` (12 tests passing).
* **Acceptance Criteria:** Attempting `DELETE FROM audit_logs` raises PostgreSQL exception.
* **Dependencies & Risks:** Table growth over time (Mitigated via annual declarative range partitioning).
* **Delivery Priority:** P0 (Completed and Verified).

---

### Module 05: Product & Category Catalog
* **Module ID:** `MOD-CAT-PROD` | **Priority:** P0 | **Status:** `VERIFIED IMPLEMENTED`
* **Business Purpose:** Master retail catalog management supporting fast multi-barcode scanning, unit conversions, and tax rate classifications.
* **Scope:** Included: Category hierarchies, multi-barcode aliases (EAN-13, internal SKU, GS1), UOM conversion factors, pricing tiers.
* **Actors & Permissions:** Catalog Manager (Create/edit products, manage barcodes); Store Supervisor (View pricing); Cashier (Scan).
* **Workflows:** Create Category $\rightarrow$ Define product $\rightarrow$ Assign multiple barcodes $\rightarrow$ Set standard retail price & cost $\rightarrow$ Synced to POS IndexedDB.
* **Data Model:** `products`, `categories`, `product_barcodes`, `unit_of_measures`, `uom_conversions`. Unique index: `(tenant_id, barcode)`.
* **Services & Boundaries:** `app/Models/Product.php`, `app/Models/Category.php`, `app/Modules/Inventory/Services/UomConversionService.php`.
* **User Interface:** `app/Filament/Resources/ProductResource.php`, `app/Filament/Resources/CategoryResource.php`.
* **APIs & Integrations:** POS catalog sync endpoint (`GET /api/pos/catalog`).
* **Accounting & Tax:** Associates default NBR tax rates (15% VAT, Supplementary Duty) and COGS/Inventory asset accounts.
* **Reports & KPIs:** Active SKU Count, Category Margin Breakdown, Missing Barcode Audit.
* **Security & Audit:** Product deletions soft-deleted; price changes tracked in audit trail.
* **Tests:** `tests/Database/CatalogCompanyConsistencyTest.php` (8 tests passing), `tests/Database/CatalogTenantIsolationTest.php` (6 tests passing).
* **Acceptance Criteria:** Barcode lookup query returns matching product in < 2ms under 100,000 active records.
* **Dependencies & Risks:** Duplicate barcode conflict across tenants (Mitigated via composite unique index `(tenant_id, barcode)`).
* **Delivery Priority:** P0 (Completed and Verified).

---

### Module 06: Inventory Costing & Valuation (AVCO / FIFO)
* **Module ID:** `MOD-INV-VAL` | **Priority:** P0 | **Status:** `VERIFIED IMPLEMENTED`
* **Business Purpose:** Accurate inventory quantity and monetary valuation tracking across central warehouses and retail stores without margin distortion.
* **Scope:** Included: Real-time AVCO recalculation on Goods Receipt, FIFO cost layer depletion on sale, inter-branch stock transfers, blind physical count sessions.
* **Actors & Permissions:** Warehouse Manager (Post receipts, transfers); Store Supervisor (Physical counts); Finance Controller (Approve write-offs).
* **Workflows:** Inward receipt posts stock layer $\rightarrow$ Recalculate warehouse AVCO moving average. Outward sale depletes oldest layer $\rightarrow$ Decrements stock balance $\rightarrow$ Posts COGS journal.
* **Data Model:** `stock_movements`, `stock_layers`, `stock_balances`, `stock_transfers`, `stock_count_sessions`. Trigger `trg_stock_movements_immutability` active.
* **Services & Boundaries:** `app/Modules/Inventory/Services/AvcoValuationService.php`, `app/Modules/Inventory/Services/FifoValuationService.php`, `app/Modules/Inventory/Services/StockMovementService.php`.
* **User Interface:** `app/Filament/Resources/StockTransferResource.php`, `app/Filament/Resources/StockCountSessionResource.php`.
* **APIs & Integrations:** Real-time depletion via POS batch sync engine.
* **Accounting & Tax:** Synchronously debits Cost of Goods Sold (5000) and credits Merchandise Inventory (1300).
* **Reports & KPIs:** Stock Valuation Ledger, Slow-Moving Stock Aging, Stock Variance Reconciliation.
* **Security & Audit:** Immutability trigger rejects updating or deleting confirmed stock movements.
* **Tests:** `tests/Database/StockBalanceDatabaseTest.php` (12 tests passing), `tests/Database/StockLedgerPropertyTest.php` (Random seed test passing).
* **Acceptance Criteria:** Cumulative mathematical variance between layer depletions and General Ledger COGS is exactly 0.00 BDT.
* **Dependencies & Risks:** Concurrent multi-till depletion race conditions (Mitigated via `SELECT FOR UPDATE` row locks).
* **Delivery Priority:** P0 (Completed and Verified).

---

### Module 07: POS, Retail Sales & Shift Operations
* **Module ID:** `MOD-POS-OPS` | **Priority:** P0 | **Status:** `VERIFIED IMPLEMENTED`
* **Business Purpose:** Fast, resilient cashier checkout in physical retail stores, guaranteeing operations during internet outages with automated cloud sync.
* **Scope:** Included: Offline-first PWA (Alpine.js + Dexie.js), multi-tender payment splitting, shift float and cash drop lifecycle, isolated savepoint batch sync API, Go hardware bridge printing and scale integration.
* **Actors & Permissions:** Cashier (Ring items, tender sale, open/close drawer shift); Shift Supervisor (Voids, overrides, cash drops, Z-report).
* **Workflows:** Offline sale written to Dexie.js $\rightarrow$ Local Go bridge kicks drawer and prints Mushak 6.3 $\rightarrow$ Connection restored $\rightarrow$ Batch sync posts to server using savepoints $\rightarrow$ Server confirms with server invoice numbers.
* **Data Model:** `pos_sessions`, `sales_orders`, `sale_items`, `pos_payments`, `cash_drawer_movements`.
* **Services & Boundaries:** `resources/pos/`, `app/Modules/Pos/Services/PosSessionService.php`, `app/Modules/Pos/Services/PosBatchSyncService.php`.
* **User Interface:** Fullscreen Alpine.js POS Cashier screen; Filament POS session monitoring resource.
* **APIs & Integrations:** REST Batch Endpoint: `POST /api/pos/batch-sync`; Local Bridge: `ws://127.0.0.1:9191/ws`.
* **Accounting & Tax:** Debits Cash/Bank/MFS/AR, credits Sales Revenue (4000) and Output VAT (2100).
* **Reports & KPIs:** Shift Variance (Shortage/Overage), Hourly Sales Velocity, Tender Split Breakdown.
* **Security & Audit:** Client UUIDv7 idempotency tokens prevent duplicate charges on network retries.
* **Tests:** `tests/Feature/PosBatchSyncTest.php` (22 tests passing), Vitest suites (`resources/pos/tests/` - 23 tests passing).
* **Acceptance Criteria:** Severing internet cable permits ringing sales, printing receipts, and syncing cleanly upon reconnection.
* **Dependencies & Risks:** Browser storage quota limits (Mitigated via persistent storage manager API).
* **Delivery Priority:** P0 (Completed and Verified).

---

### Module 08: Procurement & Commercial Document Chains
* **Module ID:** `MOD-PROC-DOCS` | **Priority:** P1 | **Status:** `PARTIALLY IMPLEMENTED` (UI in Sprint 1)
* **Business Purpose:** End-to-end commercial procurement from quotation to Purchase Order, Goods Receipt Note (GRN), and 3-way matching.
* **Scope:** Included: `PurchaseOrder`, `GoodsReceipt`, `Quotation`, `SalesInvoice` models, migrations, and PostgreSQL immutability triggers. Gap: Dedicated Filament v5 UI resources for PO, GRN, Quotation (Target: Wave 1 Sprint 1).
* **Actors & Permissions:** Procurement Officer (Create PO, evaluate quotes); Warehouse Receiver (Post GRN); Accounts Payable Clerk (3-Way Match).
* **Workflows:** PO Approved $\rightarrow$ Goods received at warehouse $\rightarrow$ GRN recorded with batch/lot $\rightarrow$ Supplier invoice matched (PO Price == GRN Qty == Invoice Total) $\rightarrow$ Post to AP.
* **Data Model:** `purchase_orders`, `purchase_order_items`, `goods_receipts`, `goods_receipt_items`, `quotations`. Triggers: `trg_purchase_orders_immutability`, `trg_goods_receipts_immutability`.
* **Services & Boundaries:** `app/Modules/Procurement/Services/PurchaseOrderService.php`, `app/Modules/Procurement/Services/GoodsReceiptService.php`.
* **User Interface:** Current: `SalesInvoiceResource`; Scheduled for Sprint 1: `PurchaseOrderResource`, `GoodsReceiptResource`, `QuotationResource`.
* **APIs & Integrations:** Mobile barcode scanning API during goods unloading.
* **Accounting & Tax:** Debits Inventory Asset (1300), credits GRN Clearing (2050); clears to Accounts Payable (2000) upon 3-way match.
* **Reports & KPIs:** PO Fulfillment Rate, Supplier Delivery On-Time-In-Full (OTIF), Unbilled GRN Suspense.
* **Security & Audit:** Immutability trigger prevents editing confirmed GRN or PO lines.
* **Tests:** `tests/Feature/PurchaseOrderTest.php` (8 tests passing), `tests/Feature/ThreeWayMatchingTest.php` (8 tests passing).
* **Acceptance Criteria:** System blocks posting supplier invoice if invoiced quantity exceeds GRN received quantity.
* **Dependencies & Risks:** Operational delay if warehouse lacks UI (Mitigated by priority delivery in Sprint 1).
* **Delivery Priority:** P1 (Backend complete; 2 Sprints for Filament UI).

---

### Module 09: Supplier Management & Accounts Payable
* **Module ID:** `MOD-SUP-AP` | **Priority:** P1 | **Status:** `PARTIALLY IMPLEMENTED` (UI in Sprint 1)
* **Business Purpose:** Vendor master directory, payment terms, withholding tax certificates, and Accounts Payable aging.
* **Scope:** Included: Supplier directory with BIN/TIN, banking details, supplier ledger, subledger AP sync, aging analysis (Current, 30, 60, 90+ days). Gap: Dedicated Filament `SupplierResource` form (Target: Sprint 1).
* **Actors & Permissions:** Procurement Manager (Onboard vendor); Accounts Payable Clerk (Process payment voucher); Auditor.
* **Workflows:** Supplier onboarded with BIN/TIN $\rightarrow$ Invoices posted to AP Subledger $\rightarrow$ Payment voucher debits AP and credits Bank $\rightarrow$ Issues Mushak 6.6 VDS certificate.
* **Data Model:** `suppliers`, `supplier_ledgers`, `subledger_balances`.
* **Services & Boundaries:** `app/Models/Supplier.php`, `app/Modules/Accounting/Services/SubledgerReconciliationService.php`.
* **User Interface:** `app/Filament/Pages/ArApAgingPage.php`; Scheduled for Sprint 1: `app/Filament/Resources/SupplierResource.php`.
* **APIs & Integrations:** Electronic Bank Payment Advice export (CSV/Excel).
* **Accounting & Tax:** Integrates directly with Control Account `2000 - Accounts Payable` and Mushak 6.6 VDS withholding tax.
* **Reports & KPIs:** AP Aging Summary, Top Vendor Payables, Withholding Tax Deducted at Source Register.
* **Security & Audit:** Segregation of duties: PO creator cannot approve payment voucher.
* **Tests:** `tests/Database/SupplierPaymentSodTest.php` (6 tests passing).
* **Acceptance Criteria:** Sum of all supplier ledger balances equals Accounts Payable control account (2000) with 0.00 BDT variance.
* **Dependencies & Risks:** Incomplete vendor TIN/BIN blocks statutory VDS certificate generation.
* **Delivery Priority:** P1 (Sprint 1 UI completion).

---

### Module 10: Customer Management & Accounts Receivable
* **Module ID:** `MOD-CUST-AR` | **Priority:** P0 | **Status:** `VERIFIED IMPLEMENTED`
* **Business Purpose:** Customer master directory, credit limits, commercial payment terms, and Accounts Receivable aging.
* **Scope:** Included: Retail and wholesale customer profiles, credit limit enforcement, AR aging registers, payment receipt vouchers.
* **Actors & Permissions:** Sales Manager (Set credit limits); Credit Controller (Approve limit overrides); Cashier (Look up customer).
* **Workflows:** Customer profile created $\rightarrow$ Credit limit defined $\rightarrow$ Sales checkout checks outstanding balance $\rightarrow$ If limit exceeded, transaction blocked unless manager overrides $\rightarrow$ Receipts post to customer subledger.
* **Data Model:** `customers`, `customer_ledgers`, `subledger_balances`.
* **Services & Boundaries:** `app/Models/Customer.php`, `app/Models/CustomerLedger.php`, `app/Filament/Resources/CustomerResource.php`.
* **User Interface:** `app/Filament/Resources/CustomerResource.php`, `app/Filament/Pages/ArApAgingPage.php`.
* **APIs & Integrations:** Customer balance lookup endpoint for POS registers.
* **Accounting & Tax:** Updates Control Account `1200 - Accounts Receivable` synchronously.
* **Reports & KPIs:** AR Aging Summary (Current, 30, 60, 90+ days), Days Sales Outstanding (DSO), Credit Breach Log.
* **Security & Audit:** Credit limit overrides log an audit entry and require dual authorization.
* **Tests:** `tests/Database/CustomerCreditLimitTest.php` (8 tests passing).
* **Acceptance Criteria:** Attempting credit sale to a customer over credit limit throws `CreditLimitExceededException`.
* **Dependencies & Risks:** Bad debt default risk (Mitigated by automated aging alerts).
* **Delivery Priority:** P0 (Completed and Verified).

---

### Module 11: General Ledger & Core Accounting
* **Module ID:** `MOD-FIN-GL` | **Priority:** P0 | **Status:** `VERIFIED IMPLEMENTED`
* **Business Purpose:** Fully compliant, GAAP double-entry General Ledger recording every monetary transaction in real time with physical immutability.
* **Scope:** Included: Multi-tier Chart of Accounts, synchronous journal posting, immutability database triggers, live trial balance, balance sheet, and profit & loss generators.
* **Actors & Permissions:** Chief Accountant (Manage CoA, period locking); Staff Accountant (Create manual journal); Auditor (Inspect).
* **Workflows:** Commercial event occurs $\rightarrow$ `JournalPostingService` verifies DR == CR $\rightarrow$ Inserts lines $\rightarrow$ Trigger enforces immutability $\rightarrow$ Commits within document transaction.
* **Data Model:** `chart_of_accounts`, `journal_entries`, `journal_lines`, `accounting_periods`. Triggers: `trg_journal_entry_immutability`, `trg_journal_lines_immutability`.
* **Services & Boundaries:** `app/Modules/Accounting/Services/JournalPostingService.php`, `app/Modules/Accounting/Services/FinancialStatementService.php`.
* **User Interface:** `app/Filament/Resources/AccountResource.php`, `app/Filament/Pages/TrialBalancePage.php`.
* **APIs & Integrations:** Domain service contracts; no public unauthenticated API mutations.
* **Accounting & Tax:** Core ledger of record; feeds Mushak 9.1 VAT return.
* **Reports & KPIs:** Live Trial Balance, Balance Sheet, Income Statement, Cash Flow Statement.
* **Security & Audit:** Trigger blocks `UPDATE` or `DELETE` on posted entries; periods close permanently.
* **Tests:** `tests/Database/ChartOfAccountsDatabaseTest.php` (8 tests passing), `tests/Database/JournalImmutabilityTest.php` (6 tests passing), `tests/Database/FinancialStatementsTest.php` (8 tests passing).
* **Acceptance Criteria:** Attempting `UPDATE journal_lines` raises PostgreSQL exception. Unbalanced journal rolls back.
* **Dependencies & Risks:** Large dataset performance on multi-year reporting (Mitigated via composite index `(tenant_id, account_id, posting_date)`).
* **Delivery Priority:** P0 (Completed and Verified).

---

### Module 12: Subledger & Reconciliation Watchdog
* **Module ID:** `MOD-FIN-SUB` | **Priority:** P0 | **Status:** `VERIFIED IMPLEMENTED`
* **Business Purpose:** Continuous automated reconciliation between General Ledger control accounts and subsidiary ledgers (AR/AP/Inventory).
* **Scope:** Included: Daily drift calculation engine, automated scheduled watchdog job, balance freeze on non-zero drift.
* **Actors & Permissions:** Autonomous System (Runs nightly); Chief Financial Officer (Receives incident alerts).
* **Workflows:** Midnight job executes $\rightarrow$ Calculates $\text{Drift} = \text{GL Balance} - \sum \text{Subledger}$ $\rightarrow$ If drift $> 0.00$, logs incident, sends alert, and freezes accounting period.
* **Data Model:** `subledger_balances`, `reconciliation_runs`, `daily_drift_logs`.
* **Services & Boundaries:** `app/Modules/Accounting/Services/SubledgerReconciliationService.php`, `app/Jobs/ReconciliationWatchdogJob.php`.
* **User Interface:** `app/Filament/Pages/ArApAgingPage.php`, system incident dashboard.
* **APIs & Integrations:** Scheduled console execution via Laravel Horizon.
* **Accounting & Tax:** Guarantees balance sheet integrity and prevents undetected ledger desynchronization.
* **Reports & KPIs:** Daily Drift Audit Log, Subledger Variance Trend.
* **Security & Audit:** Drift incidents log security audit records; cannot be deleted.
* **Tests:** `tests/Database/SubledgerGlReconciliationTest.php` (10 tests passing).
* **Acceptance Criteria:** Simulated subledger drift of 0.01 BDT immediately flags `status = DRIFT_DETECTED` and freezes period close.
* **Dependencies & Risks:** False positives from mid-transaction sync (Mitigated by running watchdog during quiet hours).
* **Delivery Priority:** P0 (Completed and Verified).

---

### Module 13: Bangladesh VAT & Statutory Tax (NBR Mushak)
* **Module ID:** `MOD-TAX-NBR` | **Priority:** P0 | **Status:** `VERIFIED (Software); SDC Lab BLOCKED (P1)`
* **Business Purpose:** Statutory compliance with Bangladesh National Board of Revenue (NBR) Value Added Tax and Supplementary Duty Act 2012.
* **Scope:** Included: Mushak 6.3 Tax Invoice, Mushak 6.6 Withholding Certificate, Mushak 6.8 Debit Note, Mushak 9.1 Monthly Return, bilingual Bengali/English output, QR verification code. Gap: Physical SDC hardware chip laboratory testing.
* **Actors & Permissions:** Cashier (Issue Mushak 6.3); Tax Accountant (Issue Mushak 6.6/6.8, finalize Mushak 9.1); NBR Tax Officer (Audit).
* **Workflows:** Sale tendered $\rightarrow$ Calculate VAT 15% and SD $\rightarrow$ Assign alphanumeric Mushak sequence $\rightarrow$ Print bilingual receipt with verification QR code.
* **Data Model:** `mushak_63_invoices`, `mushak_66_certificates`, `mushak_68_debit_notes`, `mushak_91_returns`.
* **Services & Boundaries:** `app/Modules/Tax/Services/Mushak63Service.php`, `app/Modules/Tax/Services/Mushak66Service.php`, `app/Modules/Tax/Services/Mushak68Service.php`, `app/Modules/Tax/Services/Mushak91ReturnService.php`.
* **User Interface:** `app/Filament/Pages/MushakReportsPage.php` with bilingual preview and PDF export.
* **APIs & Integrations:** POS print payload generation; JSON export for tax portal submission.
* **Accounting & Tax:** Credits Output VAT Payable (2100) or debits Input VAT Receivable (1400).
* **Reports & KPIs:** Mushak 9.1 Sub-form Breakdown, Withholding Tax Deducted at Source Register.
* **Security & Audit:** Immutability trigger enforces zero modifications to issued tax vouchers.
* **Tests:** `tests/Database/VdsWithholdingCertificateTest.php` (8 tests passing), `tests/Database/InvoiceImmutabilityTest.php` (8 tests passing).
* **Acceptance Criteria:** Generated Mushak 6.3 PDF renders authentic government layout in Bengali and English with scannable QR verification string.
* **Dependencies & Risks:** NBR regulatory changes (Mitigated by configuration-driven tax rules).
* **Delivery Priority:** P0 (Software complete; Hardware lab in Wave 2).

---

### Module 14: Import & International Trade Management
* **Module ID:** `MOD-IMP-INT` | **Priority:** P1 | **Status:** `PARTIALLY IMPLEMENTED / SPEC COMPLETE`
* **Business Purpose:** End-to-end import procurement, commercial banking instruments (LC / TT), customs clearance, multi-currency FX accounting, and landed cost absorption.
* **Scope:** Included: Foreign supplier profiles, Proforma Invoices (PI), LC margin and amendment tracking, FX currencies (BDT, RMB, USD), container logistics (20FT/40FT/CBM), Bill of Entry (B/E), customs duties (CD, RD, SD, VAT, AIT, AT), C&F port expenses, and landed cost absorption into stock layers.
* **Actors & Permissions:** Commercial Officer (LC & PI management); Treasury Accountant (Margin & FX settlements); C&F Coordinator (B/E entry); Cost Accountant (Landed cost allocation).
* **Workflows:** Proforma Invoice $\rightarrow$ Issue LC with cash margin $\rightarrow$ Record container B/L & voyage $\rightarrow$ Customs B/E assessment posted $\rightarrow$ Container destuffed at warehouse $\rightarrow$ Landed cost allocated to stock layers $\rightarrow$ Stock released to store.
* **Data Model:** `foreign_suppliers`, `import_contracts`, `letter_of_credits`, `bill_of_entries`, `import_containers`, `landed_cost_vouchers`, `landed_cost_allocations`.
* **Services & Boundaries:** `app/Modules/Sales/Services/LandedCostService.php`, dedicated import services specified in `docs/unified-v2/ERP_IMPORT_MANAGEMENT_SPECIFICATION.md`.
* **User Interface:** `app/Filament/Resources/LandedCostResource.php`; scheduled: `ImportContractResource`, `BillOfEntryResource`.
* **APIs & Integrations:** Banking payment advice export; shipping line tracking integration.
* **Accounting & Tax:** LC Margin Asset (1150), Landed Cost Clearing (2060), Foreign AP (2010), Realized FX Gain/Loss (7100/8100), Rebatable Import VAT (1400).
* **Reports & KPIs:** Pre-Import Cost Estimate vs Actual Landed Cost, Container Demurrage Register, Import Gross Margin.
* **Security & Audit:** Document repository under encrypted tenant storage; B/E adjustments log audit trail.
* **Tests:** `tests/Database/LandedCostAllocationTest.php` (12 tests passing).
* **Acceptance Criteria:** Landed cost voucher absorbs 100% of accumulated customs duty and freight into inventory layers, clearing Landed Cost Clearing (2060) to 0.00 BDT.
* **Dependencies & Risks:** Fluctuating customs assessment rates and currency volatility (Mitigated by daily rate history).
* **Delivery Priority:** P1 (Wave 2 Core Expansion).

---

### Module 15: Branch & Warehouse Operational Hierarchy
* **Module ID:** `MOD-LOC-HIER` | **Priority:** P0 | **Status:** `VERIFIED IMPLEMENTED`
* **Business Purpose:** Multi-company, multi-branch, and multi-warehouse physical operations governance with strict data segregation.
* **Scope:** Included: Company BIN registration, branch code auto-generation, warehouse allocation, inter-branch transit tracking.
* **Actors & Permissions:** Enterprise Admin (Create branches/warehouses); Branch Manager (Manage assigned store); Warehouse Keeper.
* **Workflows:** Branch created $\rightarrow$ Unique sequential branch code auto-generated $\rightarrow$ User location scopes assigned $\rightarrow$ Stock transfer dispatches from Central WH and receives at Retail Store.
* **Data Model:** `companies`, `branches`, `warehouses`, `stock_transfers`. Composite foreign keys: `(tenant_id, company_id, id)`.
* **Services & Boundaries:** `app/Models/Branch.php`, `app/Models/Warehouse.php`, `app/Modules/Inventory/Services/StockTransferService.php`.
* **User Interface:** `app/Filament/Resources/BranchResource.php`, `app/Filament/Resources/WarehouseResource.php`, `app/Filament/Resources/StockTransferResource.php`.
* **APIs & Integrations:** Branch master endpoint for POS terminal initialization.
* **Accounting & Tax:** Separates corporate entities with different BIN numbers onto discrete tax ledgers.
* **Reports & KPIs:** Branch Sales Comparison, Warehouse Stock Turn, In-Transit Inventory Valuation.
* **Security & Audit:** Branch code editing restricted to SuperAdmin role.
* **Tests:** `tests/Database/WarehouseDatabaseTest.php` (8 tests passing), `tests/Database/BranchCodeAutoGenerationTest.php` (6 tests passing).
* **Acceptance Criteria:** Store manager cannot view stock balances or sales orders of a different branch.
* **Dependencies & Risks:** Misaligned company assignment breaks statutory VAT boundaries (Mitigated by composite foreign keys).
* **Delivery Priority:** P0 (Completed and Verified).

---

### Module 16: CRM, Customer Loyalty & Promotions
* **Module ID:** `MOD-CRM-LOY` | **Priority:** P2 | **Status:** `PLANNED (Release Wave 2)`
* **Business Purpose:** Customer retention, lifetime value maximization, and automated promotional discount rules.
* **Scope:** Included: Double-entry loyalty points ledger (Earn, Redeem, Expire, Adjust), customer tier escalation (Silver, Gold, Platinum), promotional discount rules (BOGO, percentage bundle, threshold cart discounts).
* **Actors & Permissions:** Marketing Manager (Define promotion rules); Cashier (Apply points redemption at checkout).
* **Workflows:** Customer purchases items $\rightarrow$ POS calculates earned points based on tier $\rightarrow$ At next visit, customer redeems points against bill $\rightarrow$ Generates monetary discount.
* **Data Model (Proposed):** `customer_tiers`, `loyalty_ledgers`, `loyalty_rules`, `promotions`, `promotion_rules`.
* **Services & Boundaries:** Proposed under `app/Modules/Crm/Services/LoyaltyLedgerService.php`.
* **User Interface:** Filament Promotion Rule Builder; POS Tender Redemption Modal.
* **APIs & Integrations:** Real-time points balance lookup API for POS registers.
* **Accounting & Tax:** Unredeemed points record a deferred revenue liability (DR Marketing Expense, CR Loyalty Liability); redemption relieves liability to sales revenue.
* **Reports & KPIs:** Points Breakage Rate, Promotion ROI, Repeat Customer Visit Frequency.
* **Security & Audit:** Point balance modifications require double-entry journal vouchers; fraud protection caps daily redemptions.
* **Tests & Acceptance Criteria:** Total accrued points minus redeemed minus expired equals current customer point balance exactly.
* **Dependencies & Risks:** Over-redemption fraud (Mitigated via cashier OTP verification on high point redemptions).
* **Delivery Priority:** P2 (Wave 2 Core Expansion).

---

### Module 17: HRM & Payroll Management
* **Module ID:** `MOD-HRM-PAY` | **Priority:** P2 | **Status:** `PLANNED (Release Wave 2)`
* **Business Purpose:** Employee profile governance, biometric attendance synchronization, and Bangladesh Labor Act 2006 compliant payroll calculation.
* **Scope:** Included: Employee master profiles, department/designation structures, attendance deduction, progressive income tax withholding slabs, festival bonus (Eid/Puja), provident fund (PF), bank disbursement advice.
* **Actors & Permissions:** HR Manager (Onboard employees, approve leaves); Payroll Officer (Run monthly payroll); Employee (Self-service payslip).
* **Workflows:** Monthly attendance locked $\rightarrow$ Calculate gross pay, tax deductions, PF, unpaid leave deductions $\rightarrow$ Dual-operator review $\rightarrow$ Post GL journal $\rightarrow$ Export bank transfer file.
* **Data Model (Proposed):** `employees`, `salary_structures`, `attendance_logs`, `payrolls`, `payroll_items`.
* **Services & Boundaries:** Proposed under `app/Modules/Hrm/Services/PayrollCalculationService.php`.
* **User Interface:** Filament Employee Resource, Monthly Payroll Processing Page.
* **APIs & Integrations:** Biometric attendance device log ingest (ZKTeco / Hikvision API).
* **Accounting & Tax:** Posts synchronous journal: DR 6100 - Salaries & Wages, CR 2200 - Payroll Withholding Tax Payable, CR 2210 - PF Payable, CR 1020 - Bank.
* **Reports & KPIs:** Monthly Salary Summary, Tax Deducted at Source (TDS) Certificate, Headcount Cost Ratio.
* **Security & Audit:** Employee compensation data strictly encrypted and restricted from general staff.
* **Tests & Acceptance Criteria:** Progressive income tax deduction matches current National Budget fiscal tax slabs down to the exact paisa.
* **Dependencies & Risks:** Labor disputes from payroll rounding (Mitigated by exact integer paisa arithmetic).
* **Delivery Priority:** P2 (Wave 2 Core Expansion).

---

### Module 18: E-Commerce & Omnichannel Integration
* **Module ID:** `MOD-ECOM-OMNI` | **Priority:** P3 | **Status:** `PLANNED (Release Wave 3)`
* **Business Purpose:** Unification of physical retail store inventories with online digital storefronts (Shopify, WooCommerce, custom mobile apps).
* **Scope:** Included: Bidirectional catalog publishing, real-time inventory level synchronization, online order ingestion, central warehouse stock reservation, courier parcel tracking.
* **Actors & Permissions:** Digital Commerce Manager (Configure channels); Warehouse Dispatcher (Pick and pack online orders).
* **Workflows:** Online order placed $\rightarrow$ Ingested via webhook $\rightarrow$ Central warehouse allocates reserved stock $\rightarrow$ Packing slip generated $\rightarrow$ Courier dispatched $\rightarrow$ GL posting on delivery.
* **Data Model (Proposed):** `ecommerce_channels`, `sync_events`, `online_orders`, `courier_consignments`.
* **Services & Boundaries:** Proposed under `app/Modules/Ecommerce/Services/OmnichannelSyncService.php`.
* **User Interface:** Filament E-Commerce Channel Integration Page, Webhook Failure Queue.
* **APIs & Integrations:** REST Webhook endpoints: `/api/v1/ecommerce/webhook/{channel}`.
* **Accounting & Tax:** Online sales post to Digital Sales Clearing; courier cash-on-delivery (COD) reconciles upon courier settlement.
* **Reports & KPIs:** Online vs In-Store Sales Mix, Webhook Ingestion Latency, Stockout Cancellation Rate.
* **Security & Audit:** Webhook signature verification (HMAC SHA-256) blocks forged order submissions.
* **Tests & Acceptance Criteria:** Online order reserves physical inventory within 500ms, preventing in-store oversell of the same SKU.
* **Dependencies & Risks:** External platform API rate limits (Mitigated via queue backoff and Redis throttling).
* **Delivery Priority:** P3 (Wave 3 Enterprise Expansion).

---

### Module 19: Reporting & Business Intelligence Dashboards
* **Module ID:** `MOD-REP-BI` | **Priority:** P0 | **Status:** `VERIFIED IMPLEMENTED`
* **Business Purpose:** Real-time visibility into executive KPIs, gross margins, stock cover, cashier sales velocity, and statutory tax registers.
* **Scope:** Included: Executive KPI widgets, high-performance live Trial Balance with drill-down, AR/AP aging registers, bilingual Mushak PDF exporters.
* **Actors & Permissions:** Managing Director / Owner (Executive dashboard); Chief Accountant (Financial reports); Store Manager (Shift reports).
* **Workflows:** User opens dashboard $\rightarrow$ Dynamic aggregation queries execute across indexed tables $\rightarrow$ Export to Excel/PDF via background buffer.
* **Data Model:** Aggregates over `journal_lines`, `sales_orders`, `stock_balances`, and `mushak_63_invoices`.
* **Services & Boundaries:** `app/Modules/Accounting/Services/FinancialStatementService.php`, `app/Filament/Pages/Dashboard.php`, `app/Filament/Pages/TrialBalancePage.php`, `app/Filament/Pages/ArApAgingPage.php`.
* **User Interface:** Filament interactive charts, bilingual PDF preview widgets, multi-column data tables.
* **APIs & Integrations:** Server-side PDF generation via DomPDF / Snappy.
* **Accounting & Tax:** Provides the mathematical foundation for period closing and tax audit defense.
* **Reports & KPIs:** Gross Margin %, Days Sales of Inventory (DSI), Sales per Square Foot, Subledger Drift.
* **Security & Audit:** Report exports log an entry in `audit_logs` recording user ID and query parameters.
* **Tests:** `tests/Database/FinancialStatementsTest.php` (8 tests passing).
* **Acceptance Criteria:** Financial report generation for 50,000 ledger rows executes in < 1.5 seconds.
* **Dependencies & Risks:** Database load spikes during month-end reporting (Mitigated by indexed query filters).
* **Delivery Priority:** P0 (Completed and Verified).

---

### Module 20: Workflow & Multi-Level Approvals
* **Module ID:** `MOD-WF-APPR` | **Priority:** P1 | **Status:** `PARTIALLY IMPLEMENTED` (UI in Sprint 1)
* **Business Purpose:** Multi-tier authorization governance for high-value purchases, stock write-offs, and customer credit overrides.
* **Scope:** Included: State machine backend, monetary threshold evaluation, approval request queue. Gap: Visual drag-and-drop rule builder (Sprint 1 uses database configuration).
* **Actors & Permissions:** Submitter (Draft and request approval); Approver (Branch Manager, Financial Controller, Managing Director).
* **Workflows:** Document exceeds threshold $\rightarrow$ Status sets to `under_approval` $\rightarrow$ Notification dispatched to designated role $\rightarrow$ Approver reviews and signs off $\rightarrow$ Document posts atomically to GL and inventory.
* **Data Model:** `workflow_rules`, `approval_requests`, `approval_history`.
* **Services & Boundaries:** `app/Modules/Workflow/Services/WorkflowApprovalService.php`.
* **User Interface:** Filament pending approval inbox widget; document approval modal.
* **APIs & Integrations:** Email and SMS alerts to approvers via Transactional Outbox.
* **Accounting & Tax:** Documents remain in unposted draft state with zero GL impact until final approval.
* **Reports & KPIs:** Pending Approval Backlog, Average Approval Turnaround Time.
* **Security & Audit:** SoD rules enforce that submitters cannot approve their own requests.
* **Tests:** `tests/Feature/WorkflowApprovalTest.php` (8 tests passing).
* **Acceptance Criteria:** Purchase Order exceeding 500,000 BDT cannot post to General Ledger without Managing Director approval.
* **Dependencies & Risks:** Operational bottlenecks from absent approvers (Mitigated via automated escalation rules).
* **Delivery Priority:** P1 (Sprint 1 UI completion).

---

### Module 21: Hardware Bridge & Peripheral Integration
* **Module ID:** `MOD-BRG-HW` | **Priority:** P0 | **Status:** `VERIFIED IMPLEMENTED`
* **Business Purpose:** Standalone local micro-daemon enabling POS browser clients to communicate securely with physical thermal receipt printers and weighing scales.
* **Scope:** Included: Go 1.22 native daemon, localhost WebSocket (`ws://127.0.0.1:9191/ws`), raw ESC/POS 58mm/80mm printing, cash drawer kick pulse, RS-232 serial scale streaming, 64-character token authentication, rate limiting.
* **Actors & Permissions:** POS Browser Client (Authenticated local client); Store Cashier.
* **Workflows:** Cashier tenders sale $\rightarrow$ POS PWA connects via WebSocket $\rightarrow$ Transmits 64-character token handshake $\rightarrow$ Bridge verifies token and origin $\rightarrow$ Emits binary ESC/POS pulses to USB printer $\rightarrow$ Kicks cash drawer.
* **Data Model:** Non-database micro-service; configuration managed via `bridge/pkg/config/`.
* **Services & Boundaries:** `bridge/cmd/bridge/main.go`, `bridge/pkg/escpos/`, `bridge/pkg/sdc/`, `bridge/pkg/server/`.
* **User Interface:** Browser hardware status indicator in POS top navigation bar.
* **APIs & Integrations:** WebSocket protocol at `ws://127.0.0.1:9191/ws`; Health endpoint at `http://127.0.0.1:9191/health`.
* **Accounting & Tax:** Generates physical printed Mushak 6.3 receipts required for statutory cashier hand-off.
* **Reports & KPIs:** Bridge Latency (sub-15ms), Printer Buffer Drops.
* **Security & Audit:** Origin verification blocks external websites; 1MB read limit prevents memory bombs; rate limiter blocks brute force.
* **Tests:** `bridge/go test -v ./...` executed live on CT 104 $\rightarrow$ 23/23 tests passing.
* **Acceptance Criteria:** Cross-origin request from unauthorized domain is rejected with `403 Forbidden`.
* **Dependencies & Risks:** Operating system USB driver permissions (Mitigated by udev rules on Linux and signed drivers on Windows).
* **Delivery Priority:** P0 (Completed and Verified).

---

### Module 22: Notifications & Transactional Outbox Relay
* **Module ID:** `MOD-NOTIF-BUS` | **Priority:** P0 | **Status:** `VERIFIED IMPLEMENTED`
* **Business Purpose:** Guarantees zero lost events and atomic outbound communications (SMS receipts, payment webhooks) without stalling customer checkout.
* **Scope:** Included: Transactional Outbox database pattern, background relay daemon, multi-gateway SMS dispatcher (Onnorokom SMS, SSL Wireless), dead-letter queue buffering.
* **Actors & Permissions:** Background Worker (`OutboxRelayJob`); Retail Customers (SMS recipients).
* **Workflows:** Sale commits $\rightarrow$ Outbox message inserted in *same transaction* $\rightarrow$ Horizon worker polls message $\rightarrow$ Dispatches via primary SMS gateway $\rightarrow$ If gateway fails, fails over to secondary gateway $\rightarrow$ Marks published.
* **Data Model:** `outbox_messages`, `dead_letter_messages`.
* **Services & Boundaries:** `app/Models/OutboxMessage.php`, `app/Jobs/OutboxRelayJob.php`, `app/Services/SmsNotificationService.php`.
* **User Interface:** Filament Outbox and Dead-Letter Monitoring Resource.
* **APIs & Integrations:** Outbound HTTP client for third-party SMS gateways.
* **Accounting & Tax:** Outbound messages record SMS communication expenses against operational accounts.
* **Reports & KPIs:** Message Delivery Success Rate, Gateway Failover Frequency, Queue Ingestion Latency.
* **Security & Audit:** API credentials stored in environment variables; SMS payloads scrub sensitive card numbers.
* **Tests:** `tests/Feature/OutboxRelayTest.php` (12 tests passing).
* **Acceptance Criteria:** Simulating primary SMS gateway 500 error triggers automatic failover to secondary gateway within 2 seconds.
* **Dependencies & Risks:** Telecom gateway outage (Mitigated via multi-vendor gateway failover).
* **Delivery Priority:** P0 (Completed and Verified).

---

### Module 23: Backup, Disaster Recovery & Operations
* **Module ID:** `MOD-OPS-DR` | **Priority:** P0 | **Status:** `VERIFIED (Snapshots); PITR Drill pending`
* **Business Purpose:** Business continuity, disaster recovery, continuous write-ahead log (WAL) archiving, and automated snapshot management.
* **Scope:** Included: Proxmox PVE host ZFS snapshots, PostgreSQL WAL archiving, automated disaster recovery scripts. Gap: Automated recurring Point-in-Time Recovery (PITR) drill protocol (Proposed ADR-024).
* **Actors & Permissions:** Enterprise DevOps Engineer; Proxmox Backup Operator.
* **Workflows:** 02:00 AM nightly $\rightarrow$ Proxmox takes atomic ZFS snapshot of container 104 storage pool $\rightarrow$ Continuous WAL files rsync to secondary storage node $\rightarrow$ Restoring restores base backup and replays WAL.
* **Data Model:** Host storage volumes: `rpool/data/subvol-104`, `/var/backups/wal/`.
* **Services & Boundaries:** `scripts/backup.sh`, `scripts/restore.sh`, Proxmox host automation.
* **User Interface:** Proxmox VE administrative console.
* **APIs & Integrations:** ZFS snapshot management CLI; SSH/Rsync transport.
* **Accounting & Tax:** Fulfills Section 107 of VAT Act 2012 requiring 5-year data preservation.
* **Reports & KPIs:** Recovery Point Objective (RPO $\le$ 5 mins), Recovery Time Objective (RTO $\le$ 30 mins).
* **Security & Audit:** Offsite backups encrypted at rest with AES-256.
* **Tests & Acceptance Criteria:** Restoring latest snapshot into temporary container recovers 100% of committed transactions.
* **Dependencies & Risks:** Single physical host failure (Mitigated by offsite storage synchronization).
* **Delivery Priority:** P0 (Snapshot active; PITR drill in Wave 1).
