# Unified ERP v2.0.1: Architecture Decision Register (ADR)
**KC Retail ERP | Master Technical Decisions & Architectural Governance**  
**Controlling Work Order:** `docs/master-work-order/KC_Retail_ERP_Unified_v2.0_Specification_Work_Order.md`  
**Repository Baseline:** Branch `step-13a-watchdog-audit` (Commit: `17d0264`)  
**Auditor & Lead Architect:** Principal Software Architect & ERP Solution Lead  
**Publication Date:** November 2026  
**Document Revision:** v2.0.1 (Empirical Verification & HA Architecture Correction)  

---

## 1. Executive Summary & ADR Governance Framework

This Architecture Decision Register (ADR) documents all fundamental engineering, architectural, and operational decisions governing KC Retail ERP Unified v2.0.1. 

In strict adherence to the Master Work Order, each ADR is cataloged with an exact status from the authoritative governance set:
* **`ACCEPTED`**: Decision has been formally agreed upon and implemented with verified repository evidence.
* **`PROPOSED`**: Formal technical proposal undergoing architectural evaluation for upcoming release waves.
* **`PENDING APPROVAL`**: Specification complete, awaiting stakeholder review prior to implementation.
* **`SUPERSEDED`**: Decision replaced by a subsequent architectural evolution.
* **`REJECTED`**: Proposal reviewed and explicitly rejected due to technical or business constraints.

Fabricated stakeholder sign-offs, unverified SLA claims, and simulated production certifications have been removed in v2.0.1.

---

## 2. Architecture Decision Register Inventory (ADR-001 through ADR-024)

```
+---------------------------------------------------------------------------------------------------------------------------------------------------+
|                                                     ARCHITECTURE DECISION REGISTER (ADR-001 - ADR-024)                                            |
+----------+------------------------------------+------------------+-----------------------+--------------------------------------------------------+
| ADR ID   | Title & Decision Subject           | Governance Status| Implementation State  | Verified Repository Evidence File / Path               |
+----------+------------------------------------+------------------+-----------------------+--------------------------------------------------------+
| ADR-001  | Modular Monolith Architecture      | ACCEPTED         | Implemented           | app/Modules/{Name}/Contracts/                          |
| ADR-002  | PostgreSQL Forced RLS & app_rw     | ACCEPTED         | Implemented           | app/Core/Database/TenantMigration.php                  |
| ADR-003  | Financial Immutability Triggers    | ACCEPTED         | Implemented           | database/migrations/2026_11_03_000007_*.php            |
| ADR-004  | Client-Side Offline PWA (Dexie.js) | ACCEPTED         | Implemented           | resources/pos/ (Alpine.js + Dexie.js)                  |
| ADR-005  | Transactional Outbox Relay Pattern | ACCEPTED         | Implemented           | app/Core/Outbox/ & OutboxRelayJob                      |
| ADR-006  | Go Hardware Peripheral Daemon      | ACCEPTED         | Implemented           | bridge/ (Go 1.22 WebSocket daemon on 9191)             |
| ADR-007  | Framework Baseline (PHP 8.4/L13)   | ACCEPTED         | Implemented           | composer.json, composer.lock                           |
| ADR-008  | Peripheral Bridge Localhost WS     | ACCEPTED         | Implemented           | bridge/pkg/server/websocket.go (CheckOrigin, 64-char)  |
| ADR-009  | Zero-Negative Stock Enforcement    | SUPERSEDED       | Replaced by ADR-016   | Superseded by ADR-016 offline oversell policy          |
| ADR-010  | Zero-Downtime Expand-Contract DDL  | ACCEPTED         | Implemented           | tests/Feature/Architecture/ExpandAndContract*.php      |
| ADR-011  | UUIDv7 Sequential Primary Keys     | ACCEPTED         | Implemented           | app/Core/Database/TenantMigration.php                  |
| ADR-012  | Append-Only Audit Trail Fabric     | ACCEPTED         | Implemented           | database/migrations/2026_11_14_000001_*.php            |
| ADR-013  | Horizon Worker Dynamic Allocation  | ACCEPTED         | Implemented           | config/horizon.php                                     |
| ADR-014  | Staged Stream Review Gates         | ACCEPTED         | Implemented           | docs/build/steps.md, .agents/rules/dev-server.md       |
| ADR-015  | Multi-Tenant Identity & RBAC       | ACCEPTED         | Implemented           | app/Core/Tenancy/TenantContext.php (Spatie team_id)    |
| ADR-015-INF| LXC Dev Container (CT 104)       | ACCEPTED         | Implemented           | Proxmox Container 104 (dev.kcerp.com)                  |
| ADR-016  | Offline POS Oversell Policy        | ACCEPTED         | Implemented           | app/Modules/Inventory/Services/CostingService.php       |
| ADR-017  | Statutory Mushak Immutability Guard| ACCEPTED         | Implemented           | database/migrations/2026_11_09_000004_*.php            |
| ADR-018  | Half-Up Tax Rounding to Paisa      | ACCEPTED         | Implemented           | app/Modules/Vat/Services/TaxCalculator.php             |
| ADR-019  | Subledger Module Boundaries        | ACCEPTED         | Implemented           | app/Modules/Accounting/Services/SubledgerService.php   |
| ADR-020  | Integrity Watchdog & Daily Drift   | ACCEPTED         | Implemented           | app/Core/Watchdog/Services/IntegrityWatchdogService.php|
| ADR-021  | Procurement Document UI Parity     | PENDING APPROVAL | Design Complete       | docs/unified-v2/ERP_MODULE_SPECIFICATION_v2.0.1.md     |
| ADR-022  | PostgreSQL High Availability (HA)  | PROPOSED         | Partially Implemented | WAL Archiving implemented; Streaming standby PROPOSED   |
| ADR-023  | Phase 2 Modular Decoupling (HRM)   | PROPOSED         | Architecture Mapped   | docs/unified-v2/ERP_MODULE_SPECIFICATION_v2.0.1.md     |
| ADR-024  | Automated PITR Recovery Drills     | PROPOSED         | Scripted in Test Harness| tests/Feature/Operations/DisasterRecoveryDrillTest.php  |
+----------+------------------------------------+------------------+-----------------------+--------------------------------------------------------+
```

---

## 3. Detailed Focus: ADR-022 (PostgreSQL High Availability Architecture)

### 3.1 Status & Classification
* **Status:** `PROPOSED` (Target: Release Wave 3 Multi-Store Deployment)
* **Current Operational State:** Single-node PostgreSQL 17.4 running inside Container 104 on ZFS storage.
* **Correction Note:** Previous documentation erroneously claimed that 99.99% availability, zero RPO (0 seconds), and RTO < 30 seconds were currently active. This section clarifies the exact boundary between **what is currently implemented** and **what is proposed for high-availability production clusters**.

### 3.2 Technical High-Availability Architecture Components

```
+---------------------------------------------------------------------------------------------------------------------------------------------------+
|                                           POSTGRESQL HIGH AVAILABILITY COMPONENT BREAKDOWN                                                        |
+---+--------------------------------+-----------------------+--------------------------------------------------------------------------------------+
| # | HA Component                   | Implementation State  | Architectural Mechanism & Operational Description                                    |
+---+--------------------------------+-----------------------+--------------------------------------------------------------------------------------+
| 1 | Continuous WAL Archiving       | CURRENTLY IMPLEMENTED | PostgreSQL `archive_mode = on` continuously writes completed 16MB WAL segments       |
|   |                                |                       | to dedicated storage (`/var/lib/postgresql/wal_archive/`). Basis for point-in-time   |
|   |                                |                       | recovery (PITR) up to the latest completed transaction log segment.                  |
+---+--------------------------------+-----------------------+--------------------------------------------------------------------------------------+
| 2 | Base Backups & PITR Drills     | CURRENTLY IMPLEMENTED | Daily filesystem base backups paired with WAL archives. Automated restoration drill  |
|   |                                |                       | verified in `tests/Feature/Operations/DisasterRecoveryDrillTest.php`.                |
+---+--------------------------------+-----------------------+--------------------------------------------------------------------------------------+
| 3 | Physical Streaming Replication | PROPOSED (Wave 3)     | Continuous physical byte-level replication from Primary Node to a dedicated Warm/Hot |
|   | to Standby Node                |                       | Standby Node over secure internal network via PostgreSQL streaming replication.     |
+---+--------------------------------+-----------------------+--------------------------------------------------------------------------------------+
| 4 | Standby Replay & Lag Monitoring| PROPOSED (Wave 3)     | Standby continuously applies WALs; replication lag monitored via                     |
|   |                                |                       | `pg_stat_replication` and Prometheus alerting (alert if lag > 10MB or 30s).          |
+---+--------------------------------+-----------------------+--------------------------------------------------------------------------------------+
| 5 | Synchronous vs Asynchronous    | PROPOSED (Wave 3)     | Asynchronous replication recommended for single-datacenter retail operations to avoid|
|   | Replication Tradeoff           |                       | write latency spikes on retail POS tenders. For strict zero-RPO requirements,        |
|   |                                |                       | `synchronous_commit = on` will be enabled for financial journal transactions.       |
+---+--------------------------------+-----------------------+--------------------------------------------------------------------------------------+
| 6 | Automated Failover &           | PROPOSED (Wave 3)     | Cluster manager (`Patroni` with `etcd` consensus or `repmgr`) with virtual floating  |
|   | Split-Brain Protection         |                       | IP (`Keepalived`). Requires minimum 3 nodes (2 DB + 1 witness) to prevent split-brain|
|   |                                |                       | network partitioning where two primaries accept conflicting writes.                  |
+---+--------------------------------+-----------------------+--------------------------------------------------------------------------------------+
```

### 3.3 Target SLAs for Release Wave 3 (When Cluster Deployed)
* **Target Uptime:** 99.95% (Multi-node Proxmox HA cluster across separate physical hosts).
* **Target RPO (Recovery Point Objective):** $< 5 \text{ seconds}$ under asynchronous streaming replication; $0 \text{ seconds}$ under synchronous commit for General Ledger transactions.
* **Target RTO (Recovery Time Objective):** $< 45 \text{ seconds}$ via automated health checking and virtual IP reassignment.

---

## 4. Key Architectural Reconciliations

### 4.1 Resolution: ADR-009 vs ADR-016 (Zero Negative Stock vs Offline Overselling)
* **Historical Conflict:** ADR-009 strictly banned negative inventory balances (`CHECK (qty >= 0)`). However, in live retail store operations, cashiers must never be prevented from scanning and tendering goods physically held by customers at the checkout counter, even if cloud sync was delayed or an unrecorded stock transfer occurred.
* **Authoritative Resolution:** ADR-016 supersedes ADR-009. The strict database check constraint was replaced with an intelligent trigger:
  1. Offline POS batch sync operations (`source = 'POS_OFFLINE'`) are permitted to drive stock balances negative.
  2. The system immediately tags the balance record with `requires_recount = true`.
  3. The Store Manager dashboard alerts the store team to perform an immediate physical audit and issue an inventory adjustment voucher to re-zero the variance.

### 4.2 Resolution: ADR-015 Disambiguation
* **Historical Conflict:** Two historical ADR documents used the identifier `ADR-015`: one for multi-tenancy identity scoping and one for Proxmox LXC containerization.
* **Authoritative Resolution:** 
  * `ADR-015`: Permanently designated as **Multi-Tenant Identity & RBAC Scoping**.
  * `ADR-015-INF`: Permanently designated as **Proxmox LXC Containerization & Staging Environment**.

---

## 5. Architectural Governance & Review Process

All architectural changes follow a strict 4-step governance process:
1. **Authoring:** Author drafts a standardized ADR document defining Context, Decision, Alternatives, and Consequences.
2. **Review:** Peer review by Principal Software Architect and Lead Database Architect.
3. **Automated Verification:** Implementation must include automated unit, feature, and database constraint tests proving the decision's behavior.
4. **Acceptance:** Status updated to `ACCEPTED` only after all tests pass on PostgreSQL 17 as `app_rw`.

**Related Document Links:**
- Module Specification Baseline: [ERP_MODULE_SPECIFICATION_v2.0.1.md](docs/unified-v2/ERP_MODULE_SPECIFICATION_v2.0.1.md)
- Acceptance Criteria Baseline: [ERP_ACCEPTANCE_CRITERIA_v2.0.1.md](docs/unified-v2/ERP_ACCEPTANCE_CRITERIA_v2.0.1.md)
- Database Architecture Review: [ERP_DATABASE_ARCHITECTURE_REVIEW_v2.0.1.md](docs/unified-v2/ERP_DATABASE_ARCHITECTURE_REVIEW_v2.0.1.md)
- Documentation Changelog: [ERP_DOCUMENTATION_CHANGELOG_v2.0.1.md](docs/unified-v2/ERP_DOCUMENTATION_CHANGELOG_v2.0.1.md)
