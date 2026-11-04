# Unified ERP v2.0.2: Acceptance Test Evidence Register
**KC Retail ERP | Itemized Test Execution Ledger & Infrastructure Reality Audit**  
**Controlling Work Order:** `docs/master-work-order/KC_Retail_ERP_Unified_v2.0_Specification_Work_Order.md`  
**Execution Environment:** Proxmox LXC Container 104 (`dev.kcerp.com`)  
**Database Engine:** PostgreSQL 17.4 as unprivileged runtime user `app_rw`  
**Runtime Engines:** PHP 8.4.27, Laravel 13.39.0, Go 1.22.2, Node.js v20.x  
**Repository Baseline:** Branch `step-13a-watchdog-audit` | Commit: `17d0264`  
**Execution Date:** November 4, 2026  
**Auditor & QA Lead:** Principal Quality Architect & Lead Systems Auditor  
**Document Revision:** v2.0.2 (Authoritative Empirical Evidence Baseline)  

---

## 1. Executive Summary & Resolution of Test Discrepancies

Previous documentation iterations exhibited discrepancies where overall suite totals or generic assertion estimates were cited instead of exact, isolated test run metrics. 

This register documents the **exact command, test file, test count, assertion count, execution duration, and output** for each individual test suite executed on Container 104.

Furthermore, this document strictly enforces the separation between:
* **Category A (Automated Test Harness Execution):** Code paths, database constraints, PL/pgSQL triggers, and simulated workflows verified in automated tests.
* **Category B (Live Production Infrastructure Operation):** Multi-node clusters, physical hardware peripherals, and destructive disaster recovery drills.

---

## 2. Reconciled Empirical Test Execution Ledger

```
+---------------------------------------------------------------------------------------------------------------------------------------------------------------+
|                                                     ITEMIZED ACCEPTANCE TEST EXECUTION LEDGER                                                                 |
+----+-----------------------------+-----------------------------------------------------------+-------+------------+--------+--------+-------------------------+
| #  | Test File Path              | Exact Execution Command                                   | Tests | Assertions | Time   | Result | Execution Category      |
+----+-----------------------------+-----------------------------------------------------------+-------+------------+--------+--------+-------------------------+
| 1  | tests/Database/             | `php artisan test tests/Database/CompositeForeignKeyTest` | 2     | 4          | 0.31s  | PASS   | Category A: Test Harness|
|    | CompositeForeignKeyTest.php |                                                           |       |            |        |        | PostgreSQL 17 app_rw    |
+----+-----------------------------+-----------------------------------------------------------+-------+------------+--------+--------+-------------------------+
| 2  | tests/Database/             | `php artisan test tests/Database/JournalImmutabilityTest` | 17    | 38         | 4.12s  | PASS   | Category A: Test Harness|
|    | JournalImmutabilityTest.php |                                                           |       |            |        |        | PostgreSQL 17 app_rw    |
+----+-----------------------------+-----------------------------------------------------------+-------+------------+--------+--------+-------------------------+
| 3  | tests/Database/             | `php artisan test tests/Database/TenantIsolationTest.php` | 10    | 31         | 0.78s  | PASS   | Category A: Test Harness|
|    | TenantIsolationTest.php     |                                                           |       |            |        |        | PostgreSQL 17 app_rw    |
+----+-----------------------------+-----------------------------------------------------------+-------+------------+--------+--------+-------------------------+
| 4  | tests/Database/             | `php artisan test tests/Database/AuditTrailHashChainTest` | 6     | 18         | 0.94s  | PASS   | Category A: Test Harness|
|    | AuditTrailHashChainTest.php |                                                           |       |            |        |        | PostgreSQL 17 app_rw    |
+----+-----------------------------+-----------------------------------------------------------+-------+------------+--------+--------+-------------------------+
| 5  | tests/Feature/Operations/   | `php artisan test tests/Feature/Operations/Disaster...`   | 1     | 10         | 18.62s | PASS   | Category A: Test Harness|
|    | DisasterRecoveryDrillTest   |                                                           |       |            |        |        | Simulated PITR Drill    |
+----+-----------------------------+-----------------------------------------------------------+-------+------------+--------+--------+-------------------------+
| 6  | resources/pos/tests/        | `npx vitest run`                                          | 23    | 23         | 0.20s  | PASS   | Category A: Test Harness|
|    | (fiscal, sync, tax tests)   | (Executes fiscal.test.js, sync-retry.test.js, tax.test.js)|       |            |        |        | Node.js / Dexie.js      |
+----+-----------------------------+-----------------------------------------------------------+-------+------------+--------+--------+-------------------------+
| 7  | bridge/pkg/...              | `cd bridge && go test -v ./...`                           | 23    | N/A        | 2.05s  | PASS   | Category A: Test Harness|
|    | (server, devices, sdc)      |                                                           |       |            |        |        | Go 1.22 linux/amd64     |
+----+-----------------------------+-----------------------------------------------------------+-------+------------+--------+--------+-------------------------+
| 8  | Full Laravel Test Suite     | `php artisan test --compact`                              | 403   | 6,274      | 133.97s| PASS   | Category A: Test Harness|
|    | (All modules & databases)   |                                                           |       |            |        |        | PostgreSQL 17 app_rw    |
+----+-----------------------------+-----------------------------------------------------------+-------+------------+--------+--------+-------------------------+
```

---

## 3. Discrepancy Resolution & Audit Evidence

### 3.1 CompositeForeignKeyTest Discrepancy Resolved
* **Previous Error in v2.0.1:** Document cited "8 passed, 24 assertions" or "14 tests".
* **Authoritative Code Audit:** Inspection of [`tests/Database/CompositeForeignKeyTest.php`](tests/Database/CompositeForeignKeyTest.php) confirms exactly **2 test methods**:
  1. `test_cross_tenant_foreign_key_reference_is_rejected` (0.21s)
  2. `test_valid_same_tenant_foreign_key_reference_succeeds` (0.06s)
* **Verified Execution Output:** `Tests: 2 passed (4 assertions) | Duration: 0.31s`.

### 3.2 JournalImmutabilityTest Discrepancy Resolved
* **Previous Error in v2.0.1:** Document cited "14 passed, 42 assertions".
* **Authoritative Code Audit:** Inspection of [`tests/Database/JournalImmutabilityTest.php`](tests/Database/JournalImmutabilityTest.php) confirms exactly **17 test methods**:
  - Direct insert in posted state fails (trigger 1).
  - Balanced post succeeds (trigger 2).
  - Modifying line after posting fails (trigger 3).
  - Posting into closed period fails.
  - Reversing a draft fails.
  - Posting unbalanced entry fails.
  - Posting date outside period bounds fails.
  - Reversal without target fails.
  - Cross-company reversal fails.
  - Double reversal fails.
  - Reversal succeeds.
  - Updating posted header fails.
  - Updating line company or entry fails.
  - Soft-closed period fails.
  - Fewer than two lines fails.
  - Line with zero or double debit/credit fails.
  - Allowed draft edits succeed.
* **Verified Execution Output:** `Tests: 17 passed (38 assertions) | Duration: 4.12s`.

### 3.3 POS Vitest Discrepancy Resolved
* **Previous Error in v2.0.1:** Document cited `sync.test.js` as a single file while reporting 23 assertions.
* **Authoritative Execution Truth:** The POS PWA test suite runs via `npx vitest run` in `resources/pos/`. Vitest runs across **3 test files**:
  1. `resources/pos/tests/fiscal.test.js` (5 tests)
  2. `resources/pos/tests/sync-retry.test.js` (8 tests)
  3. `resources/pos/tests/tax.test.js` (10 tests)
* **Verified Execution Output:** `Test Files: 3 passed (3) | Tests: 23 passed (23) | Duration: 200ms`.

### 3.4 Point-in-Time Recovery (PITR) Test Clarification
* **Test Verification:** [`tests/Feature/Operations/DisasterRecoveryDrillTest.php`](tests/Feature/Operations/DisasterRecoveryDrillTest.php) executed on Container 104 and passed: `1 passed (10 assertions) | Duration: 18.62s`.
* **Category A vs Category B Distinction:**
  - **Category A (Automated Harness):** The test harness spins up an ephemeral database connection, dumps schema/data, creates a base backup, generates synthetic transaction load, triggers WAL archiver, drops a record, and verifies WAL replay recovery up to the target timestamp. **This passed 100%**.
  - **Category B (Physical Infrastructure Drill):** Dropping the live PostgreSQL database cluster in Container 104 and recovering it from cold disk backups is an operational infrastructure drill. This has **NOT BEEN RUN** on the dev container to avoid interrupting developer operations.

---

## 4. Hardware & Load Testing Status

```
+---------------------------------------------------------------------------------------------------------------+
|                                    NON-AUTOMATED / EXTERNAL VERIFICATION STATUS                               |
+----+----------------------------+----------+------------------------------------------------------------------+
| ID | Verification Subject       | Status   | Technical Rationale & Current State                              |
+----+----------------------------+----------+------------------------------------------------------------------+
| E1 | Physical NBR SDC Hardware  | BLOCKED  | Virtual Container 104 lacks physical RS232 serial hardware bus.  |
|    | Integration Test           |          | Software emulator passes 100% in Go bridge test suite.          |
+----+----------------------------+----------+------------------------------------------------------------------+
| E2 | k6 Distributed 10,000 rps  | NOT RUN  | Load benchmark script exists; not executed against CT 104 to     |
|    | Stress Load Test           |          | prevent container resource starvation during audit phase.        |
+----+----------------------------+----------+------------------------------------------------------------------+
| E3 | Multi-Node Proxmox Failover| NOT RUN  | Standby node streaming replication is proposed for Wave 3.       |
|    | Cluster Drill              |          | Current environment operates on single container CT 104.         |
+----+----------------------------+----------+------------------------------------------------------------------+
```

**Authoritative Status:** **ACCEPTANCE EVIDENCE RECONCILED & VALIDATED**
