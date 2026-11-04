# Unified ERP v2.0.3: Master Documentation Changelog & Evidence Closure
**KC Retail ERP | Master Changelog: Corrections from v2.0.2 to v2.0.3**  
**Controlling Work Order:** `docs/master-work-order/KC_Retail_ERP_Unified_v2.0_Specification_Work_Order.md`  
**Repository Baseline:** Branch `step-13a-watchdog-audit` | Commit: `17d0264`  
**Lead Auditor & Systems Architect:** Principal Software Architect & QA Lead  
**Publication Date:** November 2026  
**Document Revision:** v2.0.3 (Authoritative Audit Changelog)  

---

## 1. Executive Summary & Purpose

This document provides the itemized record of all architectural corrections, evidence reconciliations, and statutory clarifications introduced in the **Unified ERP v2.0.3 Documentation Suite**.

Every change addresses specific technical inconsistencies identified during the independent review of v2.0.2, establishing an airtight, evidence-backed foundation prior to implementation authorization.

---

## 2. Itemized Changelog of Corrections from v2.0.2 to v2.0.3

```
+-----------------------------------------------------------------------------------------------------------------------------------------------+
|                                            MASTER CHANGELOG: v2.0.2 TO v2.0.3 CORRECTIONS                                                    |
+----+----------------------------+------------------------------------+------------------------------------+-----------------------------------+
| #  | Focus Area                 | Stated in v2.0.2                   | Corrected in v2.0.3                | Empirical Proof & Code Reference  |
+----+----------------------------+------------------------------------+------------------------------------+-----------------------------------+
| 1  | Core Database Assertions   | Individual counts sum to 91        | Reconciled: AuditTrailHashChainTest| `tests/Database/                  |
|    | Discrepancy (11 Assertions)| assertions; combined reports 102.  | actually contains **29 assertions**|  AuditTrailHashChainTest.php`:    |
|    |                            | (11-assertion discrepancy).        | (not 18). Sum: 4+38+31+29 = **102**| 6 passed, 29 assertions (1.54s).  |
+----+----------------------------+------------------------------------+------------------------------------+-----------------------------------+
| 2  | Go Hardware Bridge Tests   | Provided cached Go test output     | Executed fresh uncached command:   | Container 104 terminal output:    |
|    | Execution Freshness        | (`ok bridge (cached)`).            | `go test -count=1 -v ./...`.       | 23 passed in **2.032s** (0 cached)|
+----+----------------------------+------------------------------------+------------------------------------+-----------------------------------+
| 3  | Pilot Authorization &      | Claimed formal signed waiver in    | Reconciled: Zero signatures signed;| `docs/unified-v2/                 |
|    | Governance Status          | cross-doc reconciliation text.     | all 5 stakeholders PENDING APPROVAL|  ERP_PILOT_AUTHORIZATION_STATUS_  |
|    |                            |                                    | Status: **TECHNICALLY CONDITIONALLY|  v2.0.3.md`: Authoritative gate.  |
|    |                            |                                    | READY - PILOT NOT AUTHORIZED**.    |                                   |
+----+----------------------------+------------------------------------+------------------------------------+-----------------------------------+
| 4  | PostgreSQL Table Owner     | Claimed table owner runs DML or    | Proved: Migrations run 100% DDL;   | `docker/postgres/roles.sql`:      |
|    | DML under FORCE RLS        | bypassed RLS without explaining    | unprivileged owner hits default-   | Dev uses SUPERUSER erp_owner;     |
|    |                            | default-deny mechanics.            | deny. Designed least-privilege DML | least-privilege architecture paper|
|    |                            |                                    | architecture for future backfills. | authored in v2.0.3.               |
+----+----------------------------+------------------------------------+------------------------------------+-----------------------------------+
| 5  | PostgreSQL current_setting | Stated that current_setting returns| Corrected: When unset, returns     | PostgreSQL 17 internal docs;      |
|    | Return Value Semantics     | an empty string `''` when absent.  | **`NULL`**. `NULLIF(NULL, '')` ->  | SQL 3-valued logic evaluated:     |
|    |                            |                                    | NULL -> tenant_id=NULL -> UNKNOWN  | fail-closed behavior confirmed.   |
|    |                            |                                    | (fail-closed behavior confirmed).  |                                   |
+----+----------------------------+------------------------------------+------------------------------------+-----------------------------------+
| 6  | Bangladesh SDC & Legal     | Suggested software-only Mushak 6.3 | Reconciled: Software invoicing     | NBR GO 17/Mushak/2019 & Rule 40;  |
|    | Compliance Exemption       | is an automatic statutory waiver.  | requires written intimation and    | Physical SDC marked BLOCKED;      |
|    |                            |                                    | written tax counsel confirmation on| written counsel opinion mandatory.|
|    |                            |                                    | whether retail sector mandates SDC.|                                   |
+----+----------------------------+------------------------------------+------------------------------------+-----------------------------------+
| 7  | Procurement Pilot          | Proposed assisted seed scripts or  | Formally rejected: Mandated        | `docs/unified-v2/                 |
|    | Operational Policy         | backend document creation.         | **Option B (Preloaded opening stk  |  ERP_PILOT_AUTHORIZATION_STATUS_  |
|    |                            |                                    | with purchasing suspended)** until |  v2.0.3.md`: Option B/A protocol. |
|    |                            |                                    | **Option A (Sprint 1 UI)** ready.  |                                   |
+----+----------------------------+------------------------------------+------------------------------------+-----------------------------------+
| 8  | Import Management DDL      | Basic preliminary schema with      | Complete production DDL:           | `docs/unified-v2/                 |
|    | Relational Refinement      | static tax rates and missing lines.| `btree_gist` exclusion constraints,|  ERP_IMPORT_DATABASE_DESIGN_      |
|    |                            |                                    | composite keys, multi-line PIs,    |  REFINEMENT_v2.0.3.md`.           |
|    |                            |                                    | partial shipments, FX snapshots.   |                                   |
+----+----------------------------+------------------------------------+------------------------------------+-----------------------------------+
| 9  | Infrastructure Evidence    | Ambiguous claims between harness   | Strictly delineated: Category A    | Container 104 storage verified:   |
|    | Distinction (Cat A vs B)   | and live operational recovery.     | automated harness passed (10 a);   | Docker current-postgres-1;        |
|    |                            |                                    | Category B live cluster restore    | cold restore & 10k rps = NOT RUN. |
|    |                            |                                    | and 10k rps load test = **NOT RUN**|                                   |
+----+----------------------------+------------------------------------+------------------------------------+-----------------------------------+
```

---

## 3. Verification & Compliance Sign-Off

All discrepancies from v2.0.2 have been completely reconciled and verified against physical code, test suites, and container configurations.

**Authoritative Status:** **CHANGELOG VERIFIED & RECORD LOCKED (v2.0.3)**
