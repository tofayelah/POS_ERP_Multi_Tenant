# Unified ERP v2.0: Module Specification
**KC Retail ERP | Functional & Technical Domain Specifications**  
**Controlling Work Order:** `docs/master-work-order/KC_Retail_ERP_Unified_v2.0_Specification_Work_Order.md`  
**Repository Baseline:** Branch `step-13a-watchdog-audit` (Commit: `17d0264`)  
**Lead Architect:** Principal Software Architect & ERP Solution Lead  
**Publication Date:** November 2026  
**Document Authority:** Authoritative Specification Template (Section 10 Compliance)  

---

## PART I: CURRENT CORE PRODUCTION DOMAINS (PHASE 1)

---

### Module 01: Platform & Multi-Tenant Foundation

**Module ID:** `MOD-PLAT-01`  
**Business Owner:** VP of Product / Platform Operations  
**Technical Owner:** Core Infrastructure Architect  
**Priority:** P0 (Critical Foundation)  
**Status:** `VERIFIED IMPLEMENTED`  
**Source:** Playbook Step 1; ADR-001, ADR-002; Repository Baseline  

#### A. Business Purpose
Eliminates infrastructure sprawl and operational overhead by providing a single multi-tenant platform capable of hosting discrete retail operating companies, managing subscriptions, quotas, and automated tenant lifecycle states without data contamination.

#### B. Scope
* **Included:** Multi-tenant registration, lifecycle state machine (`trial`, `active`, `suspended`, `grace`, `archived`), plan tier feature gating (user, store, register limits), tenant subscription billing records, and platform superadmin management panel.
* **Excluded:** Self-service public credit card checkout gateway (managed via enterprise sales in Phase 1).

#### C. Actors and Permissions
* **Platform SuperAdmin:** Unrestricted cross-tenant oversight via auditable support tokens; can provision, suspend, or archive tenants.
* **Tenant Owner:** Root administrator for a single tenant; can configure companies, branches, and manage subscriptions.
* **Tenant Admin:** Day-to-day administrative management within tenant boundaries.

#### D. Business Workflows
* **Provisioning:** SuperAdmin provisions tenant $\rightarrow$ UUIDv7 assigned $\rightarrow$ Seed default Chart of Accounts and Admin user $\rightarrow$ Status set to `active`.
* **Suspension:** Subscription expiry or non-payment $\rightarrow$ `EnforceTenantState` middleware blocks mutating requests (`HTTP 403`) while permitting read-only report inspection.
* **Reactivation:** Payment received $\rightarrow$ State restored to `active` $\rightarrow$ Write privileges restored immediately.

#### E. Data Model
* **Entities:** `Tenant`, `Plan`, `TenantSubscription`, `SupportAccessGrant`.
* **Key Fields:** `id (UUIDv7)`, `name`, `subdomain`, `state`, `is_active`, `plan_id`, `created_at`.
* **Constraints:** `UNIQUE(subdomain)`.

#### F. Services and Domain Boundaries
* **Services:** [`TenantService.php`](file:///d:/POS_ERP_Multi_Tenant/app/Core/Tenancy/TenantService.php), [`TenantContext.php`](file:///d:/POS_ERP_Multi_Tenant/app/Core/Tenancy/TenantContext.php).
* **Middleware:** [`EnforceTenantState.php`](file:///d:/POS_ERP_Multi_Tenant/app/Core/Http/Middleware/EnforceTenantState.php), [`FilamentTenantContextMiddleware.php`](file:///d:/POS_ERP_Multi_Tenant/app/Core/Http/Middleware/FilamentTenantContextMiddleware.php).

#### G. User Interface
* **Filament Resources:** [`TenantResource.php`](file:///d:/POS_ERP_Multi_Tenant/app/Filament/Platform/Resources/Tenants/TenantResource.php) (Platform panel).

#### H. APIs and Integrations
* Internal context resolution via hostname or path prefix `/admin/{tenant}`.

#### I. Accounting and Tax Impact
* No direct financial postings; sets the mandatory `tenant_id` context for all downstream General Ledger transactions.

#### J. Reports and KPIs
* Platform tenant count, active stores per tenant, monthly subscription renewals.

#### K. Security and Audit
* All tenant state changes log immutable records into `audit_logs`.

#### L. Tests
* `tests/Feature/TenantLifecycleTest.php` (8 tests passing on PostgreSQL 17).

#### M. Acceptance Criteria
* Request to suspended tenant for POST/PUT returns `HTTP 403 Forbidden`.
* Database query without `app.current_tenant_id` set returns zero tenant rows.

#### N. Dependencies and Risks
* Risk: Subdomain casing mismatch on PostgreSQL exact match (Mitigated via `strtolower()` normalization).

#### O. Delivery Estimate
* Size: S (Completed and Verified).

---

### Module 02: Multi-Tenancy & Row-Level Security (RLS)

**Module ID:** `MOD-SEC-RLS`  
**Business Owner:** Chief Information Security Officer (CISO)  
**Technical Owner:** Principal Database Architect  
**Priority:** P0 (Non-negotiable Foundation)  
**Status:** `VERIFIED IMPLEMENTED`  
**Source:** Playbook Step 1; ADR-002; AGENTS.md #1, #2, #3, #4  

#### A. Business Purpose
Guarantees absolute cryptographic and database-level data isolation between competing retail businesses sharing the same physical PostgreSQL cluster.

#### B. Scope
* **Included:** Fail-closed PostgreSQL Row-Level Security on all 68 tenant tables, composite foreign keys, dual database connection enforcement (`pgsql_owner` for DDL, `app_rw` for runtime), automated RLS verification command (`php artisan db:check-rls`).
* **Excluded:** Physical database-per-tenant sharding (rejected under ADR-002).

#### C. Actors and Permissions
* **Application Connection (`app_rw`):** Unprivileged role subject to forced RLS.
* **Migration Connection (`pgsql_owner`):** Privileged role used exclusively during `artisan migrate`.

#### D. Business Workflows
* Runtime request invokes `TenantContext::set($tenant)` $\rightarrow$ Executes `SELECT set_config('app.current_tenant_id', id, false)` $\rightarrow$ PostgreSQL engine automatically restricts all subsequent queries to matching `tenant_id`.

#### E. Data Model
* **Helper:** [`TenantMigration::createTenantTable()`](file:///d:/POS_ERP_Multi_Tenant/app/Core/Database/TenantMigration.php#L13).
* **Constraints:** `FORCE ROW LEVEL SECURITY` enabled on every tenant table. Composite FKs: `FOREIGN KEY (tenant_id, ...)`.

#### F. Services and Domain Boundaries
* **Core Classes:** [`TenantMigration.php`](file:///d:/POS_ERP_Multi_Tenant/app/Core/Database/TenantMigration.php), [`CheckRlsCommand.php`](file:///d:/POS_ERP_Multi_Tenant/app/Console/Commands/CheckRlsCommand.php).

#### G. User Interface
* None (Database engine level).

#### H. APIs and Integrations
* Transparent to REST APIs and Filament admin queries.

#### I. Accounting and Tax Impact
* Prevents cross-tenant financial ledger leaks and ensures clean tax audit boundaries.

#### J. Reports and KPIs
* RLS compliance audit report: 68/68 tables verified clean.

#### K. Security and Audit
* Continuous CI check: `php artisan db:check-rls` fails the build if any tenant table lacks forced RLS or composite keys.

#### L. Tests
* `tests/Database/RlsEnforcementTest.php` (48 tests passing on PostgreSQL 17 as `app_rw`).

#### M. Acceptance Criteria
* Direct SQL query `SELECT * FROM sales_invoices` executed as `app_rw` with unset tenant context returns exactly 0 rows.

#### N. Dependencies and Risks
* Risk: Bypass via unescaped `SET` command (Mitigated by forbidding raw `SET` and mandating `set_config()`).

#### O. Delivery Estimate
* Size: M (Completed and Verified).

---

### Module 03: General Ledger & Core Accounting

**Module ID:** `MOD-FIN-GL`  
**Business Owner:** Head of Finance & Chief Accountant  
**Technical Owner:** Principal Financial Systems Architect  
**Priority:** P0 (Core Retail Foundation)  
**Status:** `VERIFIED IMPLEMENTED`  
**Source:** Playbook Step 3; ADR-005, ADR-006; AGENTS.md #5, #6, #7  

#### A. Business Purpose
Provides an immutable, GAAP-compliant double-entry General Ledger that records every monetary transaction in real time without batching delays or rounding errors.

#### B. Scope
* **Included:** Multi-tier Chart of Accounts (Assets, Liabilities, Equity, Revenue, COGS, Expenses), synchronous journal posting inside business transactions, physical immutability triggers, trial balance, balance sheet, and profit & loss generators.
* **Excluded:** Multi-currency forex revaluation (single currency BDT in Phase 1).

#### C. Actors and Permissions
* **Chief Accountant:** Full journal creation, ledger review, period locking.
* **Auditor:** Read-only ledger inspection and export.
* **Cashier / Operations:** Automated indirect posting via sales/inventory documents (no manual journal access).

#### D. Business Workflows
* Business document executes $\rightarrow$ [`JournalPostingService`](file:///d:/POS_ERP_Multi_Tenant/app/Modules/Accounting/Services/JournalPostingService.php) invoked $\rightarrow$ Validates DR = CR $\rightarrow$ Inserts header and lines $\rightarrow$ Commits in document transaction.
* Reversal workflow: Create debit/credit reversing entry referencing original voucher ID.

#### E. Data Model
* **Entities:** `Account`, `JournalEntry`, `JournalLine`.
* **Key Fields:** `id`, `tenant_id`, `entry_number`, `entry_date`, `is_posted`, `account_id`, `debit (numeric 15,2)`, `credit (numeric 15,2)`.
* **Triggers:** `trg_prevent_journal_update` enforces physical immutability on posted lines.

#### F. Services and Domain Boundaries
* **Services:** [`JournalPostingService.php`](file:///d:/POS_ERP_Multi_Tenant/app/Modules/Accounting/Services/JournalPostingService.php), [`FinancialStatementService.php`](file:///d:/POS_ERP_Multi_Tenant/app/Modules/Accounting/Services/FinancialStatementService.php).

#### G. User Interface
* **Filament Resources & Pages:** [`AccountResource.php`](file:///d:/POS_ERP_Multi_Tenant/app/Filament/Resources/AccountResource.php), [`TrialBalancePage.php`](file:///d:/POS_ERP_Multi_Tenant/app/Filament/Pages/TrialBalancePage.php).

#### H. APIs and Integrations
* Internal domain service contracts; no public external mutations.

#### I. Accounting and Tax Impact
* Core financial foundation; directly feeds Mushak 9.1 VAT returns and corporate tax filings.

#### J. Reports and KPIs
* Trial Balance, Detailed General Ledger, Balance Sheet, Income Statement.

#### K. Security and Audit
* Disallows `UPDATE` and `DELETE` via database triggers; all manual entries require dual-operator approval.

#### L. Tests
* `tests/Feature/GeneralLedgerTest.php` (24 tests passing), `tests/Database/FinancialImmutabilityTest.php` (12 tests passing).

#### M. Acceptance Criteria
* Attempting `UPDATE journal_lines SET debit = 100` throws PostgreSQL trigger exception.
* Unbalanced entry (DR != CR) throws `UnbalancedJournalException` and rolls back transaction.

#### N. Dependencies and Risks
* Risk: Performance degradation on multi-million line trial balance queries (Mitigated via composite indexing on `(tenant_id, account_id, entry_date)`).

#### O. Delivery Estimate
* Size: L (Completed and Verified).

---

### Module 04: Inventory Costing & Stock Valuation (AVCO / FIFO)

**Module ID:** `MOD-INV-VAL`  
**Business Owner:** Supply Chain Director / Warehouse Operations Head  
**Technical Owner:** Lead Inventory Architect  
**Priority:** P0 (Core Retail Foundation)  
**Status:** `VERIFIED IMPLEMENTED`  
**Source:** Playbook Step 4; ADR-008, ADR-009, ADR-010  

#### A. Business Purpose
Accurately tracks stock quantities and monetary valuation across central warehouses and retail stores using Weighted Average Cost (AVCO) or First-In-First-Out (FIFO) lot depletion to prevent Gross Margin distortion.

#### B. Scope
* **Included:** Real-time AVCO recalculation on Goods Receipt, FIFO cost layer depletion on sale, inter-branch stock transit tracking, blind physical count sessions, and automated COGS journal generation.
* **Excluded:** Manufacturing Bill of Materials (BOM) assembly (Phase 2).

#### C. Actors and Permissions
* **Warehouse Manager:** Goods receipt posting, transfer dispatch, stock count execution.
* **Store Supervisor:** Transfer receipt, physical count entry.
* **Finance Controller:** Stock write-off and cost adjustment authorization.

#### D. Business Workflows
* **Stock Inward:** Goods Receipt Note posted $\rightarrow$ Layer created with landed unit cost $\rightarrow$ Warehouse AVCO updated $\rightarrow$ DR Inventory Asset, CR GRN Suspense.
* **Stock Outward:** POS Sale synced $\rightarrow$ Deplete oldest FIFO layer or use current AVCO $\rightarrow$ Record `stock_movements` $\rightarrow$ DR COGS, CR Inventory Asset.

#### E. Data Model
* **Entities:** `StockMovement`, `StockLayer`, `StockBalance`, `StockTransfer`, `StockCountSession`.
* **Key Fields:** `product_id`, `warehouse_id`, `quantity (numeric 15,4)`, `unit_cost (numeric 15,4)`, `movement_type`.

#### F. Services and Domain Boundaries
* **Services:** [`AvcoValuationService.php`](file:///d:/POS_ERP_Multi_Tenant/app/Modules/Inventory/Services/AvcoValuationService.php), [`FifoValuationService.php`](file:///d:/POS_ERP_Multi_Tenant/app/Modules/Inventory/Services/FifoValuationService.php), [`StockMovementService.php`](file:///d:/POS_ERP_Multi_Tenant/app/Modules/Inventory/Services/StockMovementService.php).

#### G. User Interface
* **Filament Resources:** [`StockTransferResource.php`](file:///d:/POS_ERP_Multi_Tenant/app/Filament/Resources/StockTransferResource.php), [`StockCountSessionResource.php`](file:///d:/POS_ERP_Multi_Tenant/app/Filament/Resources/StockCountSessionResource.php).

#### H. APIs and Integrations
* POS sync engine endpoint for automated real-time depletion.

#### I. Accounting and Tax Impact
* Synchronous posting to Asset Inventory (1300) and Cost of Goods Sold (5000).

#### J. Reports and KPIs
* Stock Valuation Ledger, Slow-Moving Stock Aging, Inventory Turnover Ratio, In-Transit Register.

#### K. Security and Audit
* Negative stock blocked at database level except for offline POS oversell exception (ADR-016).

#### L. Tests
* `tests/Feature/InventoryCostingTest.php` (52 tests passing).

#### M. Acceptance Criteria
* Cost of goods sold matches exact layer depletion sum down to the paisa.

#### N. Dependencies and Risks
* Risk: High concurrency deadlocks on `stock_balances` during peak store hours (Mitigated by ordered row locking `SELECT FOR UPDATE`).

#### O. Delivery Estimate
* Size: L (Completed and Verified).

---

### Module 05: POS, Offline Operations & Hardware Bridge

**Module ID:** `MOD-POS-OPS`  
**Business Owner:** Retail Operations Director  
**Technical Owner:** Lead Frontend & Embedded Systems Engineer  
**Priority:** P0 (Core Retail Foundation)  
**Status:** `VERIFIED IMPLEMENTED`  
**Source:** Playbook Step 8; ADR-015, ADR-016, ADR-017  

#### A. Business Purpose
Ensures store checkout registers process customer sales without interruption during internet outages, prints physical thermal receipts, integrates with weighing scales, and syncs reliably to the cloud upon reconnection.

#### B. Scope
* **Included:** Offline-first PWA (Alpine.js + Dexie.js), multi-tender payment splitting, cash drawer shift lifecycle, isolated savepoint batch sync API, native Go hardware bridge for ESC/POS printing and RS-232 serial scales.
* **Excluded:** Integrated EMV credit card chip-and-pin hardware terminal SDKs (cashiers enter transaction reference manually).

#### C. Actors and Permissions
* **Cashier:** Scan products, tender sales, open/close drawer shift, reprint receipt.
* **Shift Supervisor:** Authorize item voids, manager discounts, cash drops, Z-Report closing.

#### D. Business Workflows
* **Offline Sale:** Cashier scans barcode $\rightarrow$ IndexedDB retrieves cached price $\rightarrow$ Tenders payment $\rightarrow$ Local Go bridge kicks drawer and prints Mushak 6.3 $\rightarrow$ Sale saved locally with `sync_status = pending`.
* **Sync:** Network restores $\rightarrow$ Service worker pushes batch payload $\rightarrow$ Server executes savepoint transaction $\rightarrow$ Success returns invoice numbers.

#### E. Data Model
* **Entities:** `PosSession`, `SalesOrder`, `SaleItem`, `PosPayment`, `CashDrawerMovement`.
* **Key Fields:** `terminal_id`, `cashier_id`, `opening_float`, `closing_cash`, `idempotency_key (UUIDv7)`.

#### F. Services and Domain Boundaries
* **Services:** [`PosBatchSyncService.php`](file:///d:/POS_ERP_Multi_Tenant/app/Modules/Pos/Services/PosBatchSyncService.php), [`PosSessionService.php`](file:///d:/POS_ERP_Multi_Tenant/app/Modules/Pos/Services/PosSessionService.php).
* **Bridge Daemon:** `bridge/main.go`, `bridge/escpos.go`, `bridge/serial.go`.

#### G. User Interface
* **Cashier Screen:** Alpine.js full-screen responsive PWA (`resources/pos/`).
* **Admin Screen:** Filament POS Session monitoring and shift register overview.

#### H. APIs and Integrations
* REST Batch Endpoint: `POST /api/pos/batch-sync` (Token authenticated, Idempotent).
* Local Bridge Endpoint: `http://127.0.0.1:9100/print` and `http://127.0.0.1:9100/scale`.

#### I. Accounting and Tax Impact
* Real-time generation of Mushak 6.3 VAT records; DR Undeposited Cash / Bank, CR Sales, CR VAT.

#### J. Reports and KPIs
* Hourly Register Sales Velocity, Shift Variance (Overage/Shortage), Tender Split Breakdown.

#### K. Security and Audit
* Client-generated UUIDv7 idempotency tokens prevent duplicate charges on network drops.

#### L. Tests
* `tests/Feature/PosBatchSyncTest.php` (22 tests passing), Go bridge tests (`bridge/go test ./...` passing).

#### M. Acceptance Criteria
* Pulling network cable during active checkout permits completing sale, printing receipt, and caching in IndexedDB.

#### N. Dependencies and Risks
* Risk: Device storage cleared by cashier browser cache wipe (Mitigated by requesting persistent storage quota via StorageManager API).

#### O. Delivery Estimate
* Size: XL (Completed and Verified).

---

### Module 06: Bangladesh Statutory VAT & Tax (NBR Mushak)

**Module ID:** `MOD-TAX-NBR`  
**Business Owner:** Head of Tax & Statutory Compliance  
**Technical Owner:** Lead Compliance & Tax Architect  
**Priority:** P0 (Legal Requirement)  
**Status:** `VERIFIED IMPLEMENTED`  
**Source:** Playbook Step 6; ADR-012; Bangladesh VAT Act 2012  

#### A. Business Purpose
Enforces complete statutory compliance with Bangladesh National Board of Revenue (NBR) regulations, generating mathematically sound, bilingual, legally binding tax documents.

#### B. Scope
* **Included:** Mushak 6.3 Tax Invoice, Mushak 6.6 Withholding Tax Certificate (VDS), Mushak 6.8 Debit Note for purchase adjustments, Mushak 9.1 Monthly VAT Return aggregation, bilingual English/Bengali rendering, QR code generation.
* **Excluded:** Physical Sales Data Controller (SDC) hardware encryption chip certification (Phase 2 Lab).

#### C. Actors and Permissions
* **Cashier:** Issues Mushak 6.3 automatically at POS.
* **Tax Accountant:** Generates Mushak 6.6 VDS certificates, Mushak 6.8 debit notes, and finalizes Mushak 9.1 returns.
* **NBR Tax Officer:** Read-only inspection of statutory registers.

#### D. Business Workflows
* Commercial sale confirmed $\rightarrow$ Calculate standard 15% VAT, Supplementary Duty (SD), or specific retail rebates $\rightarrow$ Generate sequential alphanumeric Mushak 6.3 number $\rightarrow$ Print bilingual receipt with verification QR code.

#### E. Data Model
* **Entities:** `Mushak63Invoice`, `Mushak66Certificate`, `Mushak68DebitNote`, `Mushak91Return`.
* **Key Fields:** `mushak_number`, `tax_period`, `seller_bin`, `buyer_bin`, `vat_rate`, `vat_amount`, `sd_amount`.

#### F. Services and Domain Boundaries
* **Services:** [`Mushak63Service.php`](file:///d:/POS_ERP_Multi_Tenant/app/Modules/Tax/Services/Mushak63Service.php), [`Mushak66Service.php`](file:///d:/POS_ERP_Multi_Tenant/app/Modules/Tax/Services/Mushak66Service.php), [`Mushak68Service.php`](file:///d:/POS_ERP_Multi_Tenant/app/Modules/Tax/Services/Mushak68Service.php), [`Mushak91ReturnService.php`](file:///d:/POS_ERP_Multi_Tenant/app/Modules/Tax/Services/Mushak91ReturnService.php).

#### G. User Interface
* **Filament Page:** [`MushakReportsPage.php`](file:///d:/POS_ERP_Multi_Tenant/app/Filament/Pages/MushakReportsPage.php) with live bilingual PDF preview and statutory register export.

#### H. APIs and Integrations
* Embedded into sales and purchasing domain events; JSON payload export.

#### I. Accounting and Tax Impact
* Credits Output VAT Payable (2100) or debits Input VAT Receivable (1400); integrates into monthly NBR tax settlements.

#### J. Reports and KPIs
* Mushak 9.1 Sub-form Aggregations, Withholding Tax Deducted at Source Register.

#### K. Security and Audit
* Immutability trigger enforces zero modifications to issued Mushak certificates.

#### L. Tests
* `tests/Feature/MushakTaxTest.php` (38 tests passing).

#### M. Acceptance Criteria
* PDF generator outputs authentic government format in both Bengali and English with valid scannable QR verification string.

#### N. Dependencies and Risks
* Risk: NBR regulatory format amendments (Mitigated by configuration-driven tax rules and sub-form definitions).

#### O. Delivery Estimate
* Size: L (Completed and Verified).

---

### Module 07: Procurement & Commercial Document Chains

**Module ID:** `MOD-PROC-DOCS`  
**Business Owner:** Procurement Director / Head of Commercial  
**Technical Owner:** Lead Commercial Systems Architect  
**Priority:** P1 (Essential Operational Core)  
**Status:** `PARTIALLY IMPLEMENTED` (Backend complete, UI scheduled for Sprint 1)  
**Source:** Playbook Step 11; Review Sec 11  

#### A. Business Purpose
Manages the end-to-end commercial supply cycle from quotation to purchase order, goods receipt note (GRN), and supplier invoicing, enforcing 3-way matching to eliminate billing discrepancies.

#### B. Scope
* **Included:** `Quotation`, `PurchaseOrder`, `GoodsReceipt`, `SalesInvoice`, `LandedCost`, `StockTransfer`, `StockCountSession` models, migrations, and PostgreSQL immutability triggers. Dedicated Filament UI Resources for `SalesInvoice`, `LandedCost`, `StockTransfer`, and `StockCountSession`.
* **Gap to Complete:** Dedicated Filament UI Resources for `PurchaseOrder`, `GoodsReceipt`, and `Quotation` (Target: Sprint 1).

#### C. Actors and Permissions
* **Procurement Officer:** Create RFQ, capture quotations, generate PO.
* **Warehouse Receiver:** Inspect physical goods, record received quantities on GRN.
* **Accounts Payable Clerk:** Match supplier commercial invoice against PO and GRN.

#### D. Business Workflows
* RFQ Issued $\rightarrow$ Supplier Quotations compared $\rightarrow$ PO Approved $\rightarrow$ Warehouse generates GRN upon delivery $\rightarrow$ System verifies 3-Way Match (PO Qty/Price == GRN Qty == Invoice Qty/Price) $\rightarrow$ Post to AP Subledger.

#### E. Data Model
* **Entities:** `PurchaseOrder`, `GoodsReceipt`, `Quotation`, `Supplier`.
* **Key Fields:** `po_number`, `supplier_id`, `delivery_date`, `status`, `grn_number`, `received_qty`.

#### F. Services and Domain Boundaries
* **Services:** [`PurchaseOrderService.php`](file:///d:/POS_ERP_Multi_Tenant/app/Modules/Procurement/Services/PurchaseOrderService.php), [`GoodsReceiptService.php`](file:///d:/POS_ERP_Multi_Tenant/app/Modules/Procurement/Services/GoodsReceiptService.php).

#### G. User Interface
* **Current UI:** `SalesInvoiceResource`, `LandedCostResource`, `StockTransferResource`.
* **Sprint 1 UI Deliverables:** `PurchaseOrderResource`, `GoodsReceiptResource`, `QuotationResource`.

#### H. APIs and Integrations
* REST endpoints for mobile warehouse barcode scanning during goods receipt.

#### I. Accounting and Tax Impact
* Posts GRN suspense liability on receipt; resolves to Accounts Payable upon supplier invoice match.

#### J. Reports and KPIs
* PO Fulfilment Rate, Supplier On-Time-In-Full (OTIF), Unbilled GRN Report.

#### K. Security and Audit
* Approval state machine blocks unauthorized PO issuance exceeding role monetary thresholds.

#### L. Tests
* `tests/Feature/PurchaseOrderTest.php` (8 tests passing), `tests/Feature/ThreeWayMatchingTest.php` (8 tests passing).

#### M. Acceptance Criteria
* Automated block on posting supplier invoice if invoiced quantity exceeds GRN received quantity by > 0%.

#### N. Dependencies and Risks
* Risk: Warehouse bypasses PO and enters direct inventory (Mitigated by mandatory PO linkage enforcement in warehouse settings).

#### O. Delivery Estimate
* Size: M (Backend Complete; 2 Sprints for Filament UI completion).

---

## PART II: PROPOSED EXPANSION DOMAINS (PHASE 2 ROADMAP)

---

### Module 08: Human Resource Management & Payroll (HRM)

**Module ID:** `MOD-HRM-PAY`  
**Business Owner:** Chief Human Resources Officer (CHRO)  
**Technical Owner:** Enterprise Applications Architect  
**Priority:** P2 (Advanced Operations)  
**Status:** `PLANNED` (Phase 2 Roadmap)  
**Source:** Master Work Order Sec 4  

#### A. Business Purpose
Streamlines employee master data management, biometric attendance integration, and monthly payroll processing in strict accordance with the Bangladesh Labor Act 2006.

#### B. Scope
* **Included:** Employee profiles, department/designation structures, biometric attendance logs, monthly salary computation, festival bonus (Eid/Puja), provident fund deductions, and individual income tax slab withholding.
* **Excluded:** Automated recruitment applicant tracking system (ATS).

#### C. Actors and Permissions
* **HR Manager:** Employee onboarding, leave approval, salary structure definition.
* **Payroll Accountant:** Monthly attendance locking, payroll generation, bank disbursement advice.
* **Employee:** Self-service portal for payslip download and leave requests.

#### D. Business Workflows
* Monthly attendance locked $\rightarrow$ Compute gross salary, deductions (PF, Tax, unpaid leaves), allowances $\rightarrow$ Generate payroll sheet $\rightarrow$ Dual authorization $\rightarrow$ Post GL Journal (DR Salary Expense, CR Accrued Payroll, CR Tax Withheld) $\rightarrow$ Generate bank transfer CSV.

#### E. Data Model (Proposed)
* `employees`, `salary_structures`, `attendance_logs`, `payrolls`, `payroll_items`.

#### F. Accounting and Tax Impact
* Synchronous General Ledger posting to 6100 (Salaries & Wages Expense) and 2200 (Payroll Withholding Payable).

#### G. Tests & Acceptance Criteria
* Exact calculation of progressive Bangladesh individual income tax slabs down to the paisa.

#### H. Delivery Estimate
* Size: L (Slated for Release Wave 2).

---

### Module 09: E-Commerce & Omnichannel Integration

**Module ID:** `MOD-ECOM-OMNI`  
**Business Owner:** Head of Digital Commerce  
**Technical Owner:** Integrations & API Architect  
**Priority:** P3 (Enterprise Expansion)  
**Status:** `PLANNED` (Phase 2 Roadmap)  
**Source:** Master Work Order Sec 4  

#### A. Business Purpose
Unifies physical retail outlets with online digital storefronts (Shopify, WooCommerce, custom apps) to provide a single view of stock, prevent online overselling, and enable omnichannel fulfillment.

#### B. Scope
* **Included:** Bidirectional product catalog publishing, near real-time inventory level synchronization, online order ingestion, central warehouse reservation, and courier tracking updates.
* **Excluded:** Custom storefront frontend hosting (storefronts run on external headless platforms).

#### C. Business Workflows
* Online order placed $\rightarrow$ Ingested via webhook $\rightarrow$ Central warehouse allocates reserved stock $\rightarrow$ Packing slip generated $\rightarrow$ Courier dispatched $\rightarrow$ GL posting upon delivery confirmation.

#### D. Delivery Estimate
* Size: M (Slated for Release Wave 3).

---

### Module 10: Customer Loyalty & Points Ledger

**Module ID:** `MOD-CRM-LOY`  
**Business Owner:** Marketing Director / Customer Experience Lead  
**Technical Owner:** Commercial Systems Architect  
**Priority:** P2 (Advanced Operations)  
**Status:** `PLANNED` (Phase 2 Roadmap)  
**Source:** Master Work Order Sec 4  

#### A. Business Purpose
Drives customer retention and lifetime value through an auditable, double-entry points ledger supporting point accruals, tier escalations, and monetary checkout redemptions.

#### B. Scope
* **Included:** Customer tiers (Silver, Gold, Platinum), spending-to-points accrual rules, point expiry schedules, checkout redemption against POS tender, and point valuation liability accounting.

#### C. Accounting and Tax Impact
* Accrued points record a financial deferred revenue liability (DR Marketing Expense, CR Loyalty Liability); redemption debits the liability and credits sales revenue.

#### D. Delivery Estimate
* Size: M (Slated for Release Wave 2).
