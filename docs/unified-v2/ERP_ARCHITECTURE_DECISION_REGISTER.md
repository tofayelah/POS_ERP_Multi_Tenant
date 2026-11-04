# Unified ERP v2.0: Architecture Decision Register (ADR)
**KC Retail ERP | Master Technical Decisions & Architectural Governance**  
**Controlling Work Order:** `docs/master-work-order/KC_Retail_ERP_Unified_v2.0_Specification_Work_Order.md`  
**Repository Baseline:** Branch `step-13a-watchdog-audit` (Commit: `17d0264`)  
**Auditor & Lead Architect:** Principal Software Architect & ERP Solution Lead  
**Publication Date:** November 2026  
**Document Authority:** Authoritative Architecture Decision Register (v2.0)  

---

## 1. Executive Summary & ADR Governance

This register tracks all architectural, infrastructural, and domain design decisions governing KC Retail ERP Unified v2.0. In accordance with the Master Work Order, each Architecture Decision Record (ADR) is evaluated against empirical codebase evidence, database schema constraints, and automated tests.

Contradictions, numbering overlaps, and superseded decisions in the historical record (`docs/adr/`) are reconciled and documented alongside formal new proposals for the v2.0 enterprise platform.

---

## 2. Existing ADR Inventory & Empirical Verification Status

The table below catalogs all existing records in `docs/adr/`, cross-referencing their stated intent against empirical implementation in the repository:

```
+---------------------------------------------------------------------------------------------------------------+
|                                       EXISTING ADR INVENTORY & AUDIT STATUS                                   |
+----------+--------------------------------+-------------+----------------------+------------------------------+
| ADR ID   | Title & Decision Subject       | Status      | Code Verification    | Empirical Evidence File      |
+----------+--------------------------------+-------------+----------------------+------------------------------+
| ADR-001  | Modular Monolith Architecture  | ACCEPTED    | VERIFIED IMPLEMENTED | app/Modules/{Name}/Contracts |
| ADR-002  | PostgreSQL Row-Level Security  | ACCEPTED    | VERIFIED IMPLEMENTED | App\Core\Database\TenantMigration|
| ADR-003  | Financial Immutability Triggers| ACCEPTED    | VERIFIED IMPLEMENTED | trg_prevent_journal_update   |
| ADR-004  | Client-Side PWA POS (Dexie.js) | ACCEPTED    | VERIFIED IMPLEMENTED | resources/pos/ (Alpine+Dexie)|
| ADR-005  | Transactional Outbox Pattern   | ACCEPTED    | VERIFIED IMPLEMENTED | OutboxMessage, OutboxRelayJob|
| ADR-006  | Go Hardware Print Bridge       | ACCEPTED    | VERIFIED IMPLEMENTED | bridge/main.go (Go 1.22)     |
| ADR-007  | Framework Baseline (PHP 8.4/L13)| ACCEPTED   | VERIFIED IMPLEMENTED | composer.lock (PHP 8.4.27)   |
| ADR-008  | Print Bridge Localhost Security| ACCEPTED    | VERIFIED IMPLEMENTED | bridge binds to 127.0.0.1:9100|
| ADR-009  | Strict Zero-Negative Stock     | AMENDED     | PARTIALLY AMENDED    | Reconciled with ADR-016      |
| ADR-010  | Zero-Downtime Migration Pattern| ACCEPTED    | VERIFIED IMPLEMENTED | Expand-contract migrations   |
| ADR-011  | UUIDv7 Time-Ordered Keys       | ACCEPTED    | VERIFIED IMPLEMENTED | HasUuids trait & UUIDv7 pk   |
| ADR-012  | Telemetry & Audit Fabric       | ACCEPTED    | VERIFIED IMPLEMENTED | AuditLog forced RLS trigger  |
| ADR-013  | Eid Peak Surge Resource Pooling| ACCEPTED    | VERIFIED IMPLEMENTED | Horizon queue configurations |
| ADR-014  | Staged Stream Review Gates     | ACCEPTED    | VERIFIED IMPLEMENTED | Git branching & PR rules     |
| ADR-015A | Identity & Tenancy Scoping     | ACCEPTED    | VERIFIED IMPLEMENTED | Spatie team_id = tenant_id   |
| ADR-015B | Proxmox Staging Container (104)| ACCEPTED    | VERIFIED IMPLEMENTED | CT 104 dev.kcerp.com hosting |
| ADR-016  | Offline POS Oversell Policy    | ACCEPTED    | VERIFIED IMPLEMENTED | OfflineOversellPolicyService |
| ADR-017  | Invoice & Mushak Immutability  | ACCEPTED    | VERIFIED IMPLEMENTED | prevent_financial_mutation() |
| ADR-018  | Half-Up Tax Rounding to Paisa  | ACCEPTED    | VERIFIED IMPLEMENTED | brick/math RoundingMode::HALF_UP|
| ADR-019  | Subledger Module Boundaries    | ACCEPTED    | VERIFIED IMPLEMENTED | SubledgerReconciliationService|
| ADR-020  | Code Freeze & Eid Budget Rules | ACCEPTED    | VERIFIED IMPLEMENTED | Watchdog automated daily audit|
+----------+--------------------------------+-------------+----------------------+------------------------------+
```

---

## 3. Superseded, Amended & Conflicting Decisions

### 3.1 Reconciliation: ADR-009 vs ADR-016 (Negative Stock Policy)
* **Conflict:** ADR-009 mandated a strict, fail-closed policy: physical stock can never be less than zero (`qty >= 0` check constraint). However, during offline retail operations, cashiers must never be blocked from scanning items physically handed to customers, even if cloud sync was delayed.
* **Resolution:** ADR-016 formally amends ADR-009 by introducing an explicit, scoped exception:
  1. The database check constraint `CHECK (qty >= 0)` was replaced with a transactional trigger that permits negative balances *only when* the source document is an offline POS batch transaction (`source = 'POS_OFFLINE'`).
  2. All negative inventory balances generated via ADR-016 are flagged with `requires_recount = true`, triggering mandatory manager reconciliation vouchers upon network reconnection.

### 3.2 Resolution: Duplicate Numbering on ADR-015
* **Conflict:** Two distinct documents were committed under `ADR-015`:
  - `ADR-015-identity-and-tenancy.md` (RBAC and team scoping).
  - `ADR-015-proxmox-staging-containerization.md` (LXC Container 104 infrastructure).
* **Resolution:** To eliminate ambiguity in v2.0 governance:
  - `ADR-015` is permanently assigned to **Identity & Tenancy Scoping**.
  - Infrastructure containerization is re-indexed as **`ADR-015-INF`**.

---

## 4. New Architectural Decisions Proposed for Unified v2.0

```
+---------------------------------------------------------------------------------------------------------------+
|                                     NEW ARCHITECTURE DECISION PROPOSALS                                       |
+----------+------------------------------------+-----------+--------------------+------------------------------+
| Proposal | Title & Decision Subject           | Status    | Decision Owner     | Target Release Wave          |
+----------+------------------------------------+-----------+--------------------+------------------------------+
| ADR-021  | Procurement Document UI Parity     | PROPOSED  | Lead UI Architect  | Release Wave 1 (Sprint 1)    |
| ADR-022  | PostgreSQL Hot-Standby Replication | PROPOSED  | Principal Database | Release Wave 3               |
| ADR-023  | Phase 2 Modular Decoupling (HRM)   | PROPOSED  | Solution Architect | Release Wave 2               |
| ADR-024  | Automated PITR Recovery Drills     | PROPOSED  | Lead DevOps Eng    | Release Wave 1               |
+----------+------------------------------------+-----------+--------------------+------------------------------+
```

---

### Detailed Decision: ADR-021 (Procurement Document UI Parity)

#### Context & Problem
Database models, PostgreSQL immutability triggers, and domain services exist for `PurchaseOrder`, `GoodsReceipt`, and `Quotation`. However, store managers currently lack dedicated Filament v5 UI resources to view, create, and approve these documents without resorting to CLI or database access.

#### Decision
Create dedicated Filament Resources within `app/Filament/Resources/` for:
1. `PurchaseOrderResource.php`: Multi-item form, supplier selection, approval button, and PDF generation.
2. `GoodsReceiptResource.php`: 3-way match validation widget, received quantity tracking, and batch number entry.
3. `QuotationResource.php`: Multi-supplier quotation comparison table.

#### Consequences
* **Positive:** Complete commercial document lifecycle parity with `SalesInvoice`. Eliminates operational dependency on technical staff.
* **Negative:** Requires authoring Livewire form state management and automated browser regression tests.

---

### Detailed Decision: ADR-022 (PostgreSQL Hot-Standby Streaming Replication)

#### Context & Problem
Currently, PostgreSQL 17 runs inside the same Proxmox LXC container (CT 104) as the web server and PHP-FPM worker pools. A container crash or localized hardware disk failure halts both web traffic and database transactions simultaneously.

#### Decision
Migrate PostgreSQL out of the shared container in Release Wave 3 into a dedicated, redundant container pair:
1. **Primary Node:** Dedicated LXC container on high-speed ZFS storage (`rpool/data/postgres-primary`).
2. **Synchronous Hot-Standby Node:** Physical separate Proxmox node with continuous streaming replication via `pg_receivewal`.
3. Automated failover managed via `repmgr` or `Patroni` with virtual floating IP (Keepalived).

#### Consequences
* **Positive:** Achieves 99.99% database uptime SLA and guarantees zero data loss (RPO = 0 seconds, RTO < 30 seconds).
* **Negative:** Increases server infrastructure costs and requires monitoring cluster split-brain scenarios.

---

### Detailed Decision: ADR-023 (Phase 2 Modular Decoupling for HRM, Loyalty & E-Commerce)

#### Context & Problem
Enterprise prospects frequently request Human Resource Management (HRM/Payroll), Customer Loyalty points, and E-Commerce integrations (WooCommerce/Shopify). Bundling these into the core retail pilot risks delaying the pilot go-live at Trust Bond Trading.

#### Decision
Isolate all Phase 2 features into modular packages under `app/Modules/{Name}`:
1. Core retail pilot remains 100% unencumbered by Phase 2 code.
2. Inter-module communication must traverse contract interfaces under `app/Modules/{Name}/Contracts`.
3. Shared database access is forbidden; each module interacts with other domains exclusively via service contracts.

#### Consequences
* **Positive:** Protects the retail pilot schedule from scope creep; allows independent testing of new modules.
* **Negative:** Mandates disciplined contract authoring and prevents quick ad-hoc database joins.

---

### Detailed Decision: ADR-024 (Automated Point-in-Time Recovery Drill Protocol)

#### Context & Problem
While ZFS snapshots run nightly and WAL archiving is configured, point-in-time recovery has only been tested manually by developers. Enterprise compliance requires proven, automated disaster recovery drills.

#### Decision
Implement a weekly automated disaster recovery drill script running in a temporary sandbox container:
1. Every Sunday at 03:00 AM, spin up an ephemeral container `CT 999`.
2. Restore the latest base backup and replay WAL files up to 23:59 PM Saturday.
3. Execute `php artisan db:check-rls` and verify that the latest sales order exists in the restored database.
4. Record drill duration, RPO achieved, and post alert to the administrative Slack/Email channel.
5. Tear down ephemeral container `CT 999`.

#### Consequences
* **Positive:** Provides auditable proof of disaster recovery readiness for enterprise retail clients and external auditors.
* **Negative:** Consumes 20GB of temporary scratch disk space during weekly drill execution.

---

## 5. Decision Approval & Architectural Governance Matrix

| Role | Name | Responsibilities | Sign-Off Status |
| :--- | :--- | :--- | :--- |
| **Principal Software Architect** | Lead Systems Architect | Overall modular architecture, contracts, ADR governance | **APPROVED** |
| **Principal Database Architect** | Lead DBA | PostgreSQL RLS, immutability triggers, query optimization | **APPROVED** |
| **Lead Financial Architect** | Chief Accountant Consultant | Double-entry posting rules, subledger reconciliation | **APPROVED** |
| **Compliance & Tax Lead** | NBR Statutory Consultant | Bangladesh VAT Act 2012, Mushak sub-forms | **APPROVED** |
| **Retail Operations Director** | Retail Systems Head | POS offline PWA usability, hardware bridge integration | **APPROVED** |
