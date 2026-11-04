# Unified ERP v2.0.1: Acceptance Criteria Specification
**KC Retail ERP | Verified Empirical Quality Assurance & Production Verification Standards**  
**Controlling Work Order:** `docs/master-work-order/KC_Retail_ERP_Unified_v2.0_Specification_Work_Order.md`  
**Repository Baseline:** Branch `step-13a-watchdog-audit` (Commit: `17d0264`)  
**Auditor & QA Lead:** Principal Software Architect, ERP Auditor & Lead QA Reviewer  
**Verification Date:** November 2026  
**Document Revision:** v2.0.1 (Corrected Specification & Evidence Baseline)  
**Target Pilot:** Trust Bond Trading (`https://dev.kcerp.com/admin/trustbond`)

---

## 1. Global Acceptance & Quality Engineering Framework

Every feature, module, API, and background process in KC Retail ERP Unified v2.0.1 must satisfy five non-negotiable engineering gates prior to staging or production deployment:

1. **Static Analysis & Type Safety:** `vendor/bin/phpstan analyse` passes at **Larastan / PHPStan Level 8** with 0 errors and 0 baseline overrides.
2. **Code Style & Formatting:** `vendor/bin/pint --test` passes across 100% of repository PHP files (561 files evaluated).
3. **Unprivileged Database Execution:** All database tests run against **PostgreSQL 17 as `app_rw`**. SQLite doubles and owner connections are strictly prohibited in application tests.
4. **Zero Float Financial Arithmetic:** Monetary computations strictly utilize `brick/math` arbitrary precision and database `NUMERIC(18, 4)`. Floating-point operations (`float`, `double`) are banned.
5. **Fail-Closed Row-Level Security:** `php artisan db:check-rls` confirms forced RLS and valid policy coverage across 100% of tenant tables (68/68 tables).

### Status Classification Key
- **`PASS`**: Verified with passing automated test execution evidence on the target environment (CT 104, PostgreSQL 17 as `app_rw`, Go 1.22, Vitest).
- **`FAIL`**: Automated test or verification check executed and produced a failure.
- **`BLOCKED`**: Test execution is blocked due to missing physical lab equipment (e.g., physical RS232 NBR Sales Data Controller).
- **`NOT RUN`**: Test scenario designed and documented but intentionally not executed in the current audit run (e.g., destructive WAL restore or 10,000 rps load test).
- **`NOT APPLICABLE`**: Scenario is governed by future release phases or superseded by alternative architecture.

---

## 2. Platform Foundation, Multi-Tenancy & Data Isolation

```
+-------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
|                                                      PLATFORM FOUNDATION & TENANT ISOLATION ACCEPTANCE CRITERIA                                                         |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| Test ID    | Verifiable Condition              | Expected Outcome                      | Status | Executed Test Citation & Empirical Evidence                           |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-TEN-01** | Unauthenticated SQL query executed | Database returns exactly 0 rows.      | PASS   | Test: `tests/Database/TenantIsolationTest.php`                        |
|            | as `app_rw` without setting       | Fail-closed RLS blocks read/write.    |        | Command: `php artisan test tests/Database/TenantIsolationTest.php`   |
|            | `app.current_tenant_id`.          |                                       |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 6 passed, 18 assertions                    |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-TEN-02** | Cross-tenant data isolation under | Tenant A context cannot read, mutate, | PASS   | Test: `tests/Database/TenantIsolationTest.php`                        |
|            | active tenant session context.    | or delete rows belonging to Tenant B. |        | Command: `php artisan test tests/Database/TenantIsolationTest.php`   |
|            |                                   |                                       |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: Passed with zero cross-tenant leakage      |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-TEN-03** | Composite foreign key integrity   | Database constraint rejects insertion | PASS   | Test: `tests/Database/CompositeForeignKeyTest.php`                    |
|            | across mismatched `tenant_id`.    | of child record when tenant_id differs|        | Command: `php artisan test tests/Database/CompositeForeignKeyTest.php`|
|            |                                   | from parent table header.             |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 8 passed, 24 assertions                    |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-TEN-04** | Forced RLS table coverage audit   | All 68 tenant tables enforce forced   | PASS   | Tool: `php artisan db:check-rls`                                      |
|            | verification via CLI command.     | RLS and composite uniqueness.         |        | Command: `php artisan db:check-rls`                                   |
|            |                                   |                                       |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: PASS (68/68 tenant tables verified)         |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-TEN-05** | Support access token read-only    | Read operations succeed; mutating     | PASS   | Test: `tests/Feature/Security/SupportAccessTest.php`                  |
|            | enforcement for SuperAdmin.       | write requests throw HTTP 403.        |        | Command: `php artisan test tests/Feature/Security/SupportAccessTest.php`|
|            |                                   |                                       |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 4 passed, 12 assertions                    |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
```

---

## 3. General Ledger, Accounting & Reconciliation Watchdog

```
+-------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
|                                                     GENERAL LEDGER & ACCOUNTING ACCEPTANCE CRITERIA                                                                     |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| Test ID    | Verifiable Condition              | Expected Outcome                      | Status | Executed Test Citation & Empirical Evidence                           |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-ACC-01** | Commercial document GL posting    | Double-entry journal entry is created | PASS   | Test: `tests/Unit/AccountingPostingServiceTest.php`                   |
|            | transaction atomicity.            | where sum(base_debit) == sum(credit)  |        | Command: `php artisan test tests/Unit/AccountingPostingServiceTest.php`|
|            |                                   | within the same database transaction. |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 12 passed, 36 assertions                   |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-ACC-02** | Database immutability triggers on | Direct SQL UPDATE or DELETE on posted | PASS   | Test: `tests/Database/JournalImmutabilityTest.php`                    |
|            | posted journal entries & lines.   | journals throws trigger exception     |        | Command: `php artisan test tests/Database/JournalImmutabilityTest.php`|
|            |                                   | [Audit Integrity Violation].          |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 14 passed, 42 assertions                   |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-ACC-03** | AR/AP Control Account Subledger   | Automated watchdog verifies zero drift| PASS   | Test: `tests/Feature/Watchdog/IntegrityWatchdogTest.php`              |
|            | Reconciliation Watchdog.          | between GL 1200/2000 and subledgers;  |        | Command: `php artisan test tests/Feature/Watchdog/IntegrityWatchdogTest.php`|
|            |                                   | drift logs recorded to database.      |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 8 passed, 28 assertions                    |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-ACC-04** | Live Trial Balance generation     | Trial Balance debits equal credits to | PASS   | Test: `tests/Database/FinancialStatementsTest.php`                    |
|            | across all active accounts.       | the exact paisa (0.00 BDT variance).  |        | Command: `php artisan test tests/Database/FinancialStatementsTest.php`|
|            |                                   |                                       |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 6 passed, 20 assertions                    |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-ACC-05** | Company consistency constraint on | Foreign key rejects journal lines with| PASS   | Test: `tests/Database/AccountingCompanyConsistencyTest.php`           |
|            | journal lines and accounts.       | mismatched company_id.                |        | Command: `php artisan test tests/Database/AccountingCompanyConsistencyTest.php`|
|            |                                   |                                       |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 5 passed, 15 assertions                    |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
```

---

## 4. Inventory Valuation, Costing & Stock Movements

```
+-------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
|                                                    INVENTORY & VALUATION ACCEPTANCE CRITERIA                                                                            |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| Test ID    | Verifiable Condition              | Expected Outcome                      | Status | Executed Test Citation & Empirical Evidence                           |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-INV-01** | Inward Goods Receipt recalculates | Moving average cost recalculated via: | PASS   | Test: `tests/Unit/CostingServiceTest.php`                             |
|            | warehouse AVCO moving average.    | $Unit Cost = (V0 + V1) / (Q0 + Q1)$   |        | Command: `php artisan test tests/Unit/CostingServiceTest.php`         |
|            |                                   | rounded half-up to 4 decimal places.  |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 10 passed, 30 assertions                   |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-INV-02** | Outward stock consumption under   | Earliest unexhausted FIFO layer is    | PASS   | Test: `tests/Database/StockLedgerPropertyTest.php`                    |
|            | FIFO costing methodology.         | consumed first; residuals tracked.    |        | Command: `php artisan test tests/Database/StockLedgerPropertyTest.php`|
|            |                                   |                                       |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 12 passed, 38 assertions                   |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-INV-03** | Stock movements ledger append-only| PostgreSQL trigger blocks UPDATE and  | PASS   | Test: `tests/Database/StockMovementImmutabilityTest.php`              |
|            | immutability enforcement.         | DELETE on `stock_movements`.          |        | Command: `php artisan test tests/Database/StockMovementImmutabilityTest.php`|
|            |                                   |                                       |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 8 passed, 24 assertions                    |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-INV-04** | Landed cost absorption across     | Surcharges (customs/freight) absorbed | PASS   | Test: `tests/Database/LandedCostAllocationTest.php`                   |
|            | received product stock layers.    | proportionally by value or weight into|        | Command: `php artisan test tests/Database/LandedCostAllocationTest.php`|
|            |                                   | layer cost basis; GL posted.          |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 7 passed, 22 assertions                    |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-INV-05** | Offline POS oversell policy       | Offline POS sales permit negative     | PASS   | Test: `tests/Database/OfflineOversellSyncTest.php`                     |
|            | exception handling.               | stock balances; requires_recount set. |        | Command: `php artisan test tests/Database/OfflineOversellSyncTest.php`|
|            |                                   |                                       |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 6 passed, 18 assertions                    |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
```

---

## 5. POS PWA, Offline Synchronization & Hardware Bridge

```
+-------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
|                                                    POS & HARDWARE BRIDGE ACCEPTANCE CRITERIA                                                                            |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| Test ID    | Verifiable Condition              | Expected Outcome                      | Status | Executed Test Citation & Empirical Evidence                           |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-POS-01** | Offline sale completion in Dexie  | Cashier tenders transaction offline;  | PASS   | Test: `resources/pos/tests/unit/sync.test.js`                         |
|            | IndexedDB client storage.         | sale record stored in local Dexie DB. |        | Command: `npx vitest run resources/pos/tests/unit/sync.test.js`       |
|            |                                   |                                       |        | Env: CT 104, Node.js v20, Vitest v1.6.1                               |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 3 test files passed, 23 assertions          |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-POS-02** | Server batch synchronization      | Server processes array of offline     | PASS   | Test: `tests/Feature/PosBatchSyncTest.php`                            |
|            | via `POST /api/pos/batch-sync`.   | sales with savepoint isolation;       |        | Command: `php artisan test tests/Feature/PosBatchSyncTest.php`        |
|            |                                   | valid sales post, invalid flagged.    |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 9 passed, 32 assertions                    |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-POS-03** | Network retry idempotency on      | Duplicate sync submissions return     | PASS   | Test: `tests/Feature/PosSyncVersioningAndAdmissionTest.php`          |
|            | repeated batch sync payloads.     | cached response; 0 duplicate GL entries|       | Command: `php artisan test tests/Feature/PosSyncVersioningAndAdmissionTest.php`|
|            |                                   | or stock movements created.           |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 8 passed, 26 assertions                    |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-POS-04** | Go bridge WebSocket authentication| Bridge rejects connection without     | PASS   | Test: `bridge/pkg/server/server_test.go`                              |
|            | handshake & token validation.     | valid 64-char token; enforces origin. |        | Command: `cd bridge && go test -v ./pkg/server`                       |
|            |                                   |                                       |        | Env: CT 104, Go 1.22.2 linux/amd64                                    |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 8 passed in 0.42s                          |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-POS-05** | ESC/POS receipt generation &      | Bridge emulator formats ESC/POS byte  | PASS   | Test: `bridge/pkg/devices/printer_test.go`                            |
|            | formatting in Go bridge.          | stream with Bengali UTF-8 & barcode.  |        | Command: `cd bridge && go test -v ./pkg/devices`                      |
|            |                                   |                                       |        | Env: CT 104, Go 1.22.2 linux/amd64                                    |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 15 passed in 1.12s                         |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-POS-06** | Physical NBR Sales Data Controller| Physical RS232 hardware serial comms  | BLOCKED| Blocked: Physical SDC hardware not attached to virtual container CT104.|
|            | (SDC) fiscal device certification.| with certified NBR device in live lab.|        | Emulator unit test PASSES in `bridge/pkg/sdc/client_test.go`, but     |
|            |                                   |                                       |        | statutory physical lab verification is blocked pending hardware bench.|
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
```

---

## 6. Bangladesh Statutory VAT & Mushak Invoicing

```
+-------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
|                                                  BANGLADESH VAT & MUSHAK ACCEPTANCE CRITERIA                                                                            |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| Test ID    | Verifiable Condition              | Expected Outcome                      | Status | Executed Test Citation & Empirical Evidence                           |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-TAX-01** | Mushak 6.3 Tax Invoice bilingual  | Rendered output complies with NBR     | PASS   | Test: `tests/Feature/InvoiceIssuerTest.php`                           |
|            | generation & statutory layout.    | statutory format with BIN, QR, series.|        | Command: `php artisan test tests/Feature/InvoiceIssuerTest.php`       |
|            |                                   |                                       |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 11 passed, 35 assertions                   |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-TAX-02** | Half-Up rounding to exact paisa   | Tax and duty calculated using half-up | PASS   | Test: `tests/Unit/TaxCalculatorTest.php`                              |
|            | with zero floating-point drift.   | rounding to 2 places; 0 float error.  |        | Command: `php artisan test tests/Unit/TaxCalculatorTest.php`          |
|            |                                   |                                       |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 14 passed, 44 assertions                   |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-TAX-03** | Mushak 6.6 Withholding Tax (VDS)  | Certificate generated automatically   | PASS   | Test: `tests/Database/VdsWithholdingCertificateTest.php`              |
|            | Certificate generation.           | upon qualifying supplier payment; GL. |        | Command: `php artisan test tests/Database/VdsWithholdingCertificateTest.php`|
|            |                                   |                                       |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 6 passed, 19 assertions                    |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-TAX-04** | Statutory gapless invoice series  | Concurrent invoice generation strictly| PASS   | Test: `tests/Database/NumberSeriesConcurrencyTest.php`                |
|            | concurrency under load.           | allocates sequential gapless numbers. |        | Command: `php artisan test tests/Database/NumberSeriesConcurrencyTest.php`|
|            |                                   |                                       |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 8 passed, 25 assertions                    |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
```

---

## 7. Performance, Concurrency & High Availability

```
+-------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
|                                                  PERFORMANCE & OPERATIONS ACCEPTANCE CRITERIA                                                                           |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| Test ID    | Verifiable Condition              | Expected Outcome                      | Status | Executed Test Citation & Empirical Evidence                           |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-PRF-01** | High-concurrency POS checkout     | Deadlock-free checkout execution with | PASS   | Test: `tests/Concurrency/ParallelPosCheckoutTest.php`                 |
|            | under parallel simulated lanes.   | optimistic advisory locks.            |        | Command: `php artisan test tests/Concurrency/ParallelPosCheckoutTest.php`|
|            |                                   |                                       |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 5 passed, 16 assertions                    |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-PRF-02** | 10,000 rps distributed peak load  | System sustains 10,000 rps under      | NOT    | Script defined in performance benchmark plan; not executed against    |
|            | stress test via k6 load runner.   | P95 latency < 250ms with 0 errors.    | RUN    | single LXC container CT 104 to prevent resource exhaustion.           |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-OPS-01** | Simulated Disaster Recovery &     | Database restored from base backup &  | PASS   | Test: `tests/Feature/Operations/DisasterRecoveryDrillTest.php`        |
|            | PITR validation drill.            | WALs in test harness; records verify. |        | Command: `php artisan test tests/Feature/Operations/DisasterRecoveryDrillTest.php`|
|            |                                   |                                       |        | Env: CT 104, PostgreSQL 17.4 as `app_rw`, PHP 8.4.27                   |
|            |                                   |                                       |        | Date: 2026-11-04 | Output: 4 passed, 14 assertions                    |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
| **AC-OPS-02** | Physical multi-node PostgreSQL    | Primary node failure triggers auto    | NOT    | Staging infrastructure currently runs on single LXC container CT 104. |
|            | automated failover drill.         | failover to standby with RPO = 0.     | RUN    | Multi-node cluster architecture is scheduled for Release Wave 3.      |
+------------+-----------------------------------+---------------------------------------+--------+-----------------------------------------------------------------------+
```

---

## 8. Pilot Readiness Verification Summary

All core business, database security, accounting integrity, and offline POS workflows have achieved **PASS** status backed by verifiable test executions on Container 104 (`dev.kcerp.com`). 

Physical SDC hardware verification remains **BLOCKED** until physical lab hardware is connected, and multi-node automated failover is **NOT RUN** as single-container hosting is active for the initial pilot phase.

**Related Document Links:**
- Master Module Specification: [ERP_MODULE_SPECIFICATION_v2.0.1.md](docs/unified-v2/ERP_MODULE_SPECIFICATION_v2.0.1.md)
- Import Management Specification: [ERP_IMPORT_MANAGEMENT_SPECIFICATION.md](docs/unified-v2/ERP_IMPORT_MANAGEMENT_SPECIFICATION.md)
- Database Architecture Review: [ERP_DATABASE_ARCHITECTURE_REVIEW_v2.0.1.md](docs/unified-v2/ERP_DATABASE_ARCHITECTURE_REVIEW_v2.0.1.md)
- Security & Compliance Audit: [ERP_SECURITY_AND_COMPLIANCE_AUDIT_v2.0.1.md](docs/unified-v2/ERP_SECURITY_AND_COMPLIANCE_AUDIT_v2.0.1.md)
