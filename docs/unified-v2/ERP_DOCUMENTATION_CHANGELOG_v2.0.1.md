# Unified ERP v2.0.1: Documentation Changelog & Correction Register
**KC Retail ERP | Master Audit Trail of Architectural Corrections from v2.0 to v2.0.1**  
**Controlling Work Order:** `docs/master-work-order/KC_Retail_ERP_Unified_v2.0_Specification_Work_Order.md`  
**Repository Baseline:** Branch `step-13a-watchdog-audit` (Commit: `17d0264`)  
**Auditor & Lead Architect:** Principal Software Architect & ERP Solution Lead  
**Publication Date:** November 2026  
**Document Authority:** Authoritative Documentation Changelog (v2.0.1)  

---

## 1. Executive Summary & Purpose

Following an exhaustive independent technical audit of the initial Unified ERP v2.0 documentation suite, several critical discrepancies between stated architectural claims and the physical codebase were identified. 

To preserve architectural truth, prevent premature implementation authorization, and eliminate unverified assumptions, the Unified ERP v2.0.1 documentation revision was produced. This changelog serves as the authoritative, itemized register detailing every change, correction, enhancement, and empirical test citation introduced in v2.0.1.

---

## 2. Global Architectural Corrections Across All Deliverables

```
+---------------------------------------------------------------------------------------------------------------------------------------------------+
|                                            GLOBAL ARCHITECTURAL CORRECTIONS SUMMARY                                                               |
+----+----------------------------+------------------------------------+------------------------------------+---------------------------------------+
| #  | Focus Area                 | Unified ERP v2.0 Defect / Claim    | Unified ERP v2.0.1 Correction      | Empirical Verification Source         |
+----+----------------------------+------------------------------------+------------------------------------+---------------------------------------+
| 1  | Functional Domain Coverage | Only 10 modules documented in      | All 23 functional domains fully    | docs/unified-v2/                      |
|    |                            | Module Specification.              | specified with Section 10 template.| ERP_MODULE_SPECIFICATION_v2.0.1.md    |
+----+----------------------------+------------------------------------+------------------------------------+---------------------------------------+
| 2  | Import & International     | Missing dedicated Bangladesh       | Standalone 32KB specification      | docs/unified-v2/                      |
|    | Trade Specification        | import trade specification.        | created covering LC, FX, Customs.  | ERP_IMPORT_MANAGEMENT_SPECIFICATION.md|
+----+----------------------------+------------------------------------+------------------------------------+---------------------------------------+
| 3  | Acceptance Criteria Status | Every criterion marked "PASS"      | Rigorous status classification:    | docs/unified-v2/                      |
|    | Classification             | without distinguishing test types. | PASS, FAIL, BLOCKED, NOT RUN, N/A. | ERP_ACCEPTANCE_CRITERIA_v2.0.1.md     |
+----+----------------------------+------------------------------------+------------------------------------+---------------------------------------+
| 4  | Hardware Testing Reality   | Physical SDC device claimed tested | Physical SDC marked BLOCKED;       | bridge/pkg/devices/sdc_test.go;       |
|    |                            | in dev environment.                | software emulator PASS verified.   | container has no physical RS232 port. |
+----+----------------------------+------------------------------------+------------------------------------+---------------------------------------+
| 5  | High-Throughput Load Test  | 10,000 rps load test claimed PASS. | Load test marked NOT RUN (script   | Prevented resource exhaustion on      |
|    |                            |                                    | exists; single container not run). | Proxmox CT 104 dev environment.       |
+----+----------------------------+------------------------------------+------------------------------------+---------------------------------------+
| 6  | PostgreSQL RLS Policy      | Claimed RLS policy was defined     | Quoted real TenantMigration.php:   | app/Core/Database/TenantMigration.php |
|    | Definition                 | `AS RESTRICTIVE` for all roles.    | PERMISSIVE targeted to app_rw.     | (FOR ALL TO app_rw, reporting_ro).    |
+----+----------------------------+------------------------------------+------------------------------------+---------------------------------------+
| 7  | Go Hardware Bridge URL &   | Claimed raw HTTP endpoints on      | Quoted real bridge implementation: | bridge/pkg/server/server.go;          |
|    | Protocol                   | port 9100.                         | WebSocket on ws://127.0.0.1:9191/ws| bridge/pkg/server/websocket.go        |
+----+----------------------------+------------------------------------+------------------------------------+---------------------------------------+
| 8  | High Availability & SLA    | Claimed 99.99% uptime, RPO = 0,    | Delineated WAL archiving (ACTIVE)  | Single-node container CT 104;         |
|    | Claims (ADR-022)           | RTO < 30s currently implemented.   | vs Streaming standby (PROPOSED).   | streaming cluster planned for Wave 3. |
+----+----------------------------+------------------------------------+------------------------------------+---------------------------------------+
| 9  | Governance Sign-Offs       | Fabricated persona names & sign-   | Replaced with authoritative status | docs/unified-v2/                      |
|    |                            | offs in ADR matrix.                | enums: ACCEPTED, PROPOSED, etc.    | ERP_ARCHITECTURE_DECISION_REGISTER_v2.0.1|
+----+----------------------------+------------------------------------+------------------------------------+---------------------------------------+
| 10 | Hyperlink Portability      | Machine-specific absolute links    | Converted 100% of links to valid   | Repository-relative paths:            |
|    |                            | (e.g. `file:///d:/...`).           | repository-relative Markdown links.| `app/...`, `docs/...`, `bridge/...`   |
+----+----------------------------+------------------------------------+------------------------------------+---------------------------------------+
```

---

## 3. Detailed Document-by-Document Correction Log

### 3.1 Deliverable 1: `ERP_MODULE_SPECIFICATION_v2.0.1.md`
* **Original Issue in v2.0:** Document specified only 10 modules, leaving 13 functional domains unaddressed despite being highlighted in the Feature Gap Analysis.
* **Correction in v2.0.1:** Expanded to cover **all 23 functional domains** in rigorous detail:
  1. Platform Foundation
  2. Multi-Tenancy
  3. User Management and RBAC
  4. Security and Audit Trail
  5. Product and Catalog
  6. Inventory and Costing
  7. POS and Sales
  8. Procurement
  9. Supplier Management
  10. Customer Management and AR/AP
  11. General Ledger and Accounting
  12. Subledger and Reconciliation
  13. Bangladesh VAT and Tax
  14. Import and International Trade
  15. Branch and Warehouse Management
  16. CRM, Loyalty and Promotions
  17. HRM and Payroll
  18. E-Commerce and Omnichannel
  19. Reporting and Business Intelligence
  20. Workflow and Approvals
  21. Hardware and Peripheral Integration
  22. Notifications and Outbox
  23. Backup, Recovery and Operations
* **Standardized Structure:** Every domain contains Business Purpose, Functional Scope, Actors & Permissions, Business Workflows, Data Model & Relationships, Services & Boundaries, UI, APIs, Accounting/Tax Impact, Reports/KPIs, Security/Audit, Test Scenarios, Acceptance Criteria, Dependencies, Risks, and Delivery Priority.

### 3.2 Deliverable 2: `ERP_IMPORT_MANAGEMENT_SPECIFICATION.md`
* **Original Issue in v2.0:** Complete absence of a dedicated import and international trade specification tailored for Bangladesh retail and wholesale operations.
* **Correction in v2.0.1:** Authored a standalone 32KB specification covering the complete import lifecycle:
  - Foreign Supplier & Proforma Invoice (PI) Management.
  - Multi-Currency (BDT, USD, RMB) with IAS 21 realized and unrealized FX gain/loss posting.
  - Letter of Credit (LC) & Telegraphic Transfer (TT) lifecycle (Margin, Opening, Retirement).
  - Commercial Shipment, CBM volume calculation, Container Tracking (FCL/LCL).
  - Customs Assessment under Bangladesh Customs Act 2023 (Customs Value, CD, RD, SD, VAT, AIT, AT).
  - Landed Cost Absorption Engine allocating freight, insurance, clearing, and port demurrage.
  - Bill of Entry (B/E) matching and Mushak 9.1 VAT rebate reconciliation.

### 3.3 Deliverable 3: `ERP_ACCEPTANCE_CRITERIA_v2.0.1.md`
* **Original Issue in v2.0:** Every acceptance criterion displayed a generic `PASS:` prefix without evidence, test commands, or dates. Physical SDC testing and 10,000 rps load testing were claimed to pass despite being impossible on the dev container.
* **Correction in v2.0.1:**
  - Adopted formal statuses: `PASS`, `FAIL`, `BLOCKED`, `NOT RUN`, `NOT APPLICABLE`.
  - Every `PASS` criterion includes exact Test File, Test Name, Test Command, Environment (CT 104, PostgreSQL 17 as `app_rw`), Execution Date (`2026-11-04`), and Output Summary.
  - AC-POS-06 (Physical NBR SDC hardware) marked `BLOCKED` due to absence of physical RS232 hardware in the virtual container.
  - AC-PRF-02 (k6 10,000 rps load test) marked `NOT RUN` to prevent container denial-of-service.
  - AC-OPS-02 (Multi-node automated failover) marked `NOT RUN` pending Wave 3 multi-node clustering.

### 3.4 Deliverable 4: `ERP_DATABASE_ARCHITECTURE_REVIEW_v2.0.1.md`
* **Original Issue in v2.0:** Claimed PostgreSQL RLS policy was created `AS RESTRICTIVE`, omitted actual `TenantMigration.php` code, omitted connection pooling cleanup mechanics, and generalized triggers.
* **Correction in v2.0.1:**
  - Quoted exact source code from [`app/Core/Database/TenantMigration.php`](app/Core/Database/TenantMigration.php).
  - Clarified that the policy is **`PERMISSIVE`** (PostgreSQL default) targeted specifically `TO app_rw, reporting_ro`, backed by `FORCE ROW LEVEL SECURITY`.
  - Documented unprivileged runtime role separation (`pgsql_owner` vs `app_rw`).
  - Documented connection pooling leakage risks and confirmed cleanup via [`TenantContext::clear()`](app/Core/Tenancy/TenantContext.php) across middlewares, queue jobs, and watchdog commands.
  - Quoted exact PL/pgSQL database triggers for General Ledger, Stock Movements, Sales Invoices, and Document Chains.

### 3.5 Deliverable 5: `ERP_SECURITY_AND_COMPLIANCE_AUDIT_v2.0.1.md`
* **Original Issue in v2.0:** Claimed Go bridge exposed HTTP endpoints on port 9100. Failed to document actual WebSocket authentication, rate limiting, and DNS rebinding protections.
* **Correction in v2.0.1:**
  - Documented true Go bridge architecture: WebSocket on `ws://127.0.0.1:9191/ws` and healthcheck on `http://127.0.0.1:9191/health`.
  - Documented Gorilla `CheckOrigin` origin verification preventing Cross-Origin WebSocket Hijacking.
  - Documented pre-upgrade IP rate limiting and 1MB memory bomb payload protection.
  - Documented mandatory 64-character token authentication handshake.
  - Documented loopback IP `127.0.0.1` binding preventing DNS rebinding.
  - Documented physical SDC hardware testing gap honestly.

### 3.6 Deliverable 6: `ERP_ARCHITECTURE_DECISION_REGISTER_v2.0.1.md`
* **Original Issue in v2.0:** ADR-022 claimed 99.99% availability, RPO = 0, and RTO < 30s were already implemented. Governance matrix contained fabricated persona sign-offs.
* **Correction in v2.0.1:**
  - Restructured ADR-022 to separate **Currently Implemented** (Continuous WAL Archiving and Base Backup PITR drills) from **Proposed for Release Wave 3** (Streaming replication to warm standby, Patroni failover, virtual floating IP).
  - Documented synchronous vs asynchronous replication tradeoffs.
  - Normalized all ADR statuses to `ACCEPTED`, `PROPOSED`, `PENDING APPROVAL`, `SUPERSEDED`, `REJECTED`.
  - Removed fabricated stakeholder names and approval dates.

---

## 4. Verification Checkpoint

All eight deliverables for Unified ERP v2.0.1 now align 100% with repository evidence, passing automated test suites, and empirical container configurations:

1. `ERP_MODULE_SPECIFICATION_v2.0.1.md` (Complete 23 Domains)
2. `ERP_IMPORT_MANAGEMENT_SPECIFICATION.md` (Complete Bangladesh Import Specification)
3. `ERP_ACCEPTANCE_CRITERIA_v2.0.1.md` (Empirical Evidence Baseline)
4. `ERP_DATABASE_ARCHITECTURE_REVIEW_v2.0.1.md` (PostgreSQL 17 RLS & Triggers)
5. `ERP_SECURITY_AND_COMPLIANCE_AUDIT_v2.0.1.md` (Go Bridge & Security Controls)
6. `ERP_ARCHITECTURE_DECISION_REGISTER_v2.0.1.md` (ADR-001 - ADR-024)
7. `ERP_DOCUMENTATION_CHANGELOG_v2.0.1.md` (This document)
8. `ERP_FINAL_GAP_AND_RISK_REGISTER_v2.0.1.md` (Comprehensive Risk Register)

**Related Document Links:**
- Module Specification: [ERP_MODULE_SPECIFICATION_v2.0.1.md](docs/unified-v2/ERP_MODULE_SPECIFICATION_v2.0.1.md)
- Import Specification: [ERP_IMPORT_MANAGEMENT_SPECIFICATION.md](docs/unified-v2/ERP_IMPORT_MANAGEMENT_SPECIFICATION.md)
- Final Gap & Risk Register: [ERP_FINAL_GAP_AND_RISK_REGISTER_v2.0.1.md](docs/unified-v2/ERP_FINAL_GAP_AND_RISK_REGISTER_v2.0.1.md)
