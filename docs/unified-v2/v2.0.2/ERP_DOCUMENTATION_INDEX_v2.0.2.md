# Unified ERP v2.0.2: Authoritative Documentation Index & Document Hierarchy
**KC Retail ERP | Master Document Authority, Lifecycle Status & Cross-Reference Index**  
**Controlling Work Order:** `docs/master-work-order/KC_Retail_ERP_Unified_v2.0_Specification_Work_Order.md`  
**Repository Baseline:** Branch `step-13a-watchdog-audit` | Commit: `17d0264`  
**Lead Architect & Auditor:** Principal Software Architect & Technical Documentation Lead  
**Publication Date:** November 2026  
**Document Revision:** v2.0.2 (Master Documentation Index)  

---

## 1. Executive Summary & Document Hierarchy

To eliminate ambiguity between legacy initial specifications, intermediate review deliverables, and authoritative current standards, this index defines the **hierarchical order of authority** across all documentation files residing under `docs/unified-v2/`.

### Order of Authority:
1. **Unified ERP v2.0.2 Suite (Current Controlling Specifications):** The highest documentation authority. All implementation planning, QA verification, and risk governance MUST follow these documents.
2. **Unified ERP v2.0.1 Reconciled Suite:** Technical templates and module details reconciled by v2.0.2.
3. **Verification Audit Reports (`verification/`):** Empirical audit notes and preliminary domain inspection logs.
4. **Historical Unified ERP v2.0 Suite:** Initial baseline deliverables preserved strictly for historical change tracing. Superceded wherever they conflict with v2.0.1 or v2.0.2.

---

## 2. Complete Document Inventory & Status Classification

```
+-----------------------------------------------------------------------------------------------------------------------------------------------+
|                                            MASTER DOCUMENTATION INVENTORY & STATUS LEDGER                                                     |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| #  | Document Filename & Relative Path              | Version | Document Status  | Authority Level       | Primary Function & Scope           |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 1  | docs/unified-v2/                               | v2.0.2  | CURRENT ACTIVE   | **HIGHEST AUTHORITY** | Master technical blueprint,        |
|    | ERP_MASTER_ARCHITECTURE_v2.0.2.md              |         |                  |                       | architecture, and governance rules.|
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 2  | docs/unified-v2/                               | v2.0.2  | CURRENT ACTIVE   | **HIGHEST AUTHORITY** | Complete index, hierarchy rules,   |
|    | ERP_DOCUMENTATION_INDEX_v2.0.2.md              |         |                  |                       | and lifecycle status ledger.       |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 3  | docs/unified-v2/                               | v2.0.2  | CURRENT ACTIVE   | **HIGHEST AUTHORITY** | Inter-document reconciliation and  |
|    | ERP_CROSS_DOCUMENT_RECONCILIATION_v2.0.2.md    |         |                  |                       | evidence discrepancy resolution.   |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 4  | docs/unified-v2/                               | v2.0.2  | CURRENT ACTIVE   | **HIGHEST AUTHORITY** | Git commit provenance and migration|
|    | ERP_REPOSITORY_PROVENANCE_AUDIT_v2.0.2.md      |         |                  |                       | timestamp audit.                   |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 5  | docs/unified-v2/                               | v2.0.2  | CURRENT ACTIVE   | **HIGHEST AUTHORITY** | PostgreSQL RLS semantics, roles,   |
|    | ERP_RLS_AND_DATABASE_ROLE_VERIFICATION_v2.0.2  |         |                  |                       | and session pooling safety.        |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 6  | docs/unified-v2/                               | v2.0.2  | CURRENT ACTIVE   | **HIGHEST AUTHORITY** | Go bridge port 9191 verification,  |
|    | ERP_HARDWARE_BRIDGE_VERIFICATION_v2.0.2.md     |         |                  |                       | WS security & origin validation.   |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 7  | docs/unified-v2/                               | v2.0.2  | CURRENT ACTIVE   | **HIGHEST AUTHORITY** | Reconciled itemized test execution |
|    | ERP_ACCEPTANCE_EVIDENCE_REGISTER_v2.0.2.md     |         |                  |                       | ledger with Category A/B split.    |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 8  | docs/unified-v2/                               | v2.0.2  | CURRENT ACTIVE   | **HIGHEST AUTHORITY** | Pilot readiness protocol, accepted |
|    | ERP_PILOT_RISK_ACCEPTANCE_REGISTER_v2.0.2.md   |         |                  |                       | risks and stakeholder sign-off.    |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 9  | docs/unified-v2/                               | v2.0.2  | CURRENT ACTIVE   | **CONTROLLING SPEC**  | Standalone Bangladesh Import, LC,  |
|    | ERP_IMPORT_MANAGEMENT_SPECIFICATION.md        | (Refined|                  |                       | FX, Customs & Landed Cost module.  |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 10 | docs/unified-v2/                               | v2.0.1  | ACTIVE TEMPLATE  | **ACTIVE DOMAINS**    | Comprehensive 23-domain technical  |
|    | ERP_MODULE_SPECIFICATION_v2.0.1.md             |         |                  | (Reconciled by v2.0.2)| specifications (Section 10 format).|
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 11 | docs/unified-v2/                               | v2.0.1  | ACTIVE REGISTER  | ACTIVE REGISTER       | Master register of decisions       |
|    | ERP_ARCHITECTURE_DECISION_REGISTER_v2.0.1.md  |         |                  | (Reconciled by v2.0.2)| ADR-001 through ADR-024.           |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 12 | docs/unified-v2/                               | v2.0.1  | HISTORICAL CHANGELOG| AUDIT REFERENCE    | Detailed changelog of corrections  |
|    | ERP_DOCUMENTATION_CHANGELOG_v2.0.1.md         |         |                  |                       | introduced in v2.0.1.              |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 13 | docs/unified-v2/                               | v2.0.1  | SUPERSEDED       | SUPERSEDED            | Replaced by v2.0.2 Acceptance      |
|    | ERP_ACCEPTANCE_CRITERIA_v2.0.1.md              |         |                  |                       | Evidence Register.                 |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 14 | docs/unified-v2/                               | v2.0.1  | SUPERSEDED       | SUPERSEDED            | Replaced by v2.0.2 RLS & Database  |
|    | ERP_DATABASE_ARCHITECTURE_REVIEW_v2.0.1.md     |         |                  |                       | Role Verification.                 |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 15 | docs/unified-v2/                               | v2.0.1  | SUPERSEDED       | SUPERSEDED            | Replaced by v2.0.2 Hardware Bridge |
|    | ERP_SECURITY_AND_COMPLIANCE_AUDIT_v2.0.1.md   |         |                  |                       | Verification.                      |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 16 | docs/unified-v2/                               | v2.0.1  | SUPERSEDED       | SUPERSEDED            | Replaced by v2.0.2 Pilot Risk      |
|    | ERP_FINAL_GAP_AND_RISK_REGISTER_v2.0.1.md     |         |                  |                       | Acceptance Register.               |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 17 | docs/unified-v2/                               | v2.0.0  | HISTORICAL       | HISTORICAL            | Initial master architecture draft; |
|    | ERP_MASTER_ARCHITECTURE_v2.0.md              |         |                  |                       | superseded by v2.0.2.              |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 18 | docs/unified-v2/                               | v2.0.0  | HISTORICAL       | HISTORICAL            | Initial 10-domain module spec;     |
|    | ERP_MODULE_SPECIFICATION.md                  |         |                  |                       | superseded by v2.0.1 / v2.0.2.     |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 19 | docs/unified-v2/                               | v2.0.0  | HISTORICAL       | HISTORICAL            | Initial feature gap analysis.      |
|    | ERP_FEATURE_GAP_ANALYSIS.md                  |         |                  |                       |                                    |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 20 | docs/unified-v2/                               | v2.0.0  | HISTORICAL       | HISTORICAL            | Initial implementation roadmap;    |
|    | ERP_IMPLEMENTATION_ROADMAP_v2.md             |         |                  |                       | reconciled by v2.0.2.              |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
| 21 | docs/unified-v2/verification/                  | v2.0.0  | AUDIT EVIDENCE   | HISTORICAL AUDIT      | Independent verification reports   |
|    | (All 5 verification reports)                   |         |                  |                       | from previous phase.               |
+----+------------------------------------------------+---------+------------------+-----------------------+------------------------------------+
```

---

## 3. Usage & Cross-Reference Rules for Engineering Teams

1. **When reviewing architecture:** Start with [`ERP_MASTER_ARCHITECTURE_v2.0.2.md`](docs/unified-v2/ERP_MASTER_ARCHITECTURE_v2.0.2.md).
2. **When implementing domain modules:** Consult [`ERP_MODULE_SPECIFICATION_v2.0.1.md`](docs/unified-v2/ERP_MODULE_SPECIFICATION_v2.0.1.md) for data models and services, checking status against [`ERP_CROSS_DOCUMENT_RECONCILIATION_v2.0.2.md`](docs/unified-v2/ERP_CROSS_DOCUMENT_RECONCILIATION_v2.0.2.md).
3. **When implementing foreign trade/import:** Consult [`ERP_IMPORT_MANAGEMENT_SPECIFICATION.md`](docs/unified-v2/ERP_IMPORT_MANAGEMENT_SPECIFICATION.md).
4. **When verifying test cases & CI gates:** Consult [`ERP_ACCEPTANCE_EVIDENCE_REGISTER_v2.0.2.md`](docs/unified-v2/ERP_ACCEPTANCE_EVIDENCE_REGISTER_v2.0.2.md).
5. **When auditing security or database RLS:** Consult [`ERP_RLS_AND_DATABASE_ROLE_VERIFICATION_v2.0.2.md`](docs/unified-v2/ERP_RLS_AND_DATABASE_ROLE_VERIFICATION_v2.0.2.md) and [`ERP_HARDWARE_BRIDGE_VERIFICATION_v2.0.2.md`](docs/unified-v2/ERP_HARDWARE_BRIDGE_VERIFICATION_v2.0.2.md).
6. **When evaluating pilot launch criteria:** Consult [`ERP_PILOT_RISK_ACCEPTANCE_REGISTER_v2.0.2.md`](docs/unified-v2/ERP_PILOT_RISK_ACCEPTANCE_REGISTER_v2.0.2.md).

**Authoritative Status:** **DOCUMENTATION INDEX & HIERARCHY FORMALIZED (v2.0.2)**
