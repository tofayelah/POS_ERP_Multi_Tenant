# Unified ERP v2.0: Pilot Readiness Checklist & Final Recommendation
**KC Retail ERP | Production Governance & Retail Pilot Authorization Gate**  
**Controlling Work Order:** `docs/master-work-order/KC_Retail_ERP_Unified_v2.0_Specification_Work_Order.md`  
**Evaluation Target:** Trust Bond Trading Retail Pilot on Dev Server CT 104 (`dev.kcerp.com`)  
**Repository Baseline:** Branch `step-13a-watchdog-audit` (Commit: `17d0264`)  
**Auditor & QA Lead:** Independent Principal Software Architect, ERP Auditor & Lead Reviewer  
**Publication Date:** November 2026  
**Document Authority:** Authoritative Pilot Readiness Checklist & Recommendation (v2.0)  

---

## 1. Executive Summary & Verification Scope

This document provides the final, authoritative readiness evaluation for the retail pilot deployment of KC Retail ERP Unified v2.0 at **Trust Bond Trading** (`https://dev.kcerp.com/admin/trustbond`). 

The assessment synthesizes empirical evidence gathered across all functional domains, live automated test executions, database constraint validations, security audits, and offline POS stress tests. Every major capability is classified using standardized status and risk taxonomies to establish a definitive, evidence-backed pilot readiness decision.

---

## 2. Master Capability Verification & Risk Matrix

```
+---------------------------------------------------------------------------------------------------------------+
|                                      MASTER CAPABILITY VERIFICATION MATRIX                                     |
+--------------------------+-----------------------+----------+-------------------------------------------------+
| Major Capability         | Verification Status   | Risk     | Audit Findings & Justification                  |
+--------------------------+-----------------------+----------+-------------------------------------------------+
| 1. Platform Foundation   | VERIFIED              | LOW      | State machine & plan quotas verified via tests. |
| 2. Multi-Tenancy & RLS   | VERIFIED              | LOW      | Forced RLS on 68 tables; fail-closed proven.    |
| 3. RBAC & Location Scope | VERIFIED              | LOW      | Team-scoped Spatie roles; location filtering OK.|
| 4. Audit Trail           | VERIFIED              | LOW      | Append-only SHA-256 hash chaining active.       |
| 5. Product & Barcode     | VERIFIED              | LOW      | Sub-ms multi-barcode lookup in PostgreSQL & PWA.|
| 6. Inventory Costing     | VERIFIED              | MEDIUM   | AVCO & FIFO verified; landed cost sequence rule.|
| 7. POS Shift & Register  | VERIFIED              | LOW      | Shift float, drawer drops, Z-reports validated. |
| 8. Customer Subledger    | VERIFIED              | LOW      | Credit limits block sales; aging accurate.      |
| 9. General Ledger & TB   | VERIFIED              | LOW      | DR == CR verified; Trial Balance 0.00 drift.    |
| 10. Drift Watchdog       | VERIFIED              | LOW      | Autonomous daily job detects non-zero drift.    |
| 11. Bangladesh VAT       | VERIFIED W/ LIMITS    | MEDIUM   | Mushak 6.3/6.6/6.8/9.1 OK; SDC lab uncertified. |
| 12. Landed Cost          | VERIFIED              | LOW      | Proportional absorption verified in tests.      |
| 13. Hierarchy & Location | VERIFIED              | LOW      | Company/Branch/Warehouse foreign keys enforced. |
| 14. Hardware Bridge (Go) | VERIFIED              | LOW      | 23 Go unit tests pass; ESC/POS & Scale proven.  |
| 15. Transactional Outbox | VERIFIED              | LOW      | Relays events atomically with retry backoff.    |
| 16. Operational Backup   | VERIFIED W/ LIMITS    | MEDIUM   | Host ZFS snapshots active; PITR drill manual.   |
| 17. Procurement UI (PO)  | PARTIALLY VERIFIED    | MEDIUM   | Backend verified; dedicated Filament UI missing.|
| 18. Supplier UI          | PARTIALLY VERIFIED    | MEDIUM   | Model/ledger verified; Filament Resource needed.|
| 19. Visual Workflow      | NOT VERIFIED          | LOW      | State machine OK; drag-and-drop builder absent. |
| 20. 10k rps Throughput   | NOT VERIFIED          | MEDIUM   | Target claim only; benchmark suite uncommitted. |
| 21. SDC Hardware Chip    | BLOCKED               | HIGH     | Software emulation OK; NBR lab testing pending. |
| 22. HRM & Payroll        | PLANNED (Phase 2)     | LOW      | Formally quarantined to Phase 2 Roadmap.        |
| 23. E-Commerce Sync      | PLANNED (Phase 2)     | LOW      | Formally quarantined to Phase 2 Roadmap.        |
+--------------------------+-----------------------+----------+-------------------------------------------------+
```

---

## 3. Pilot Readiness Evaluation Against Core Pillars

### 3.1 Pillar 1: Multi-Tenant Data Isolation & Security
* **Criteria:** 100% fail-closed isolation across tenant boundaries; unauthenticated queries return 0 rows.
* **Audit Finding:** `php artisan db:check-rls` confirms forced RLS across all 68 tenant tables. `tests/Database/TenantIsolationTest.php` proves zero cross-tenant leakage.
* **Pillar Verdict:** **PASSED**

### 3.2 Pillar 2: Financial & Bookkeeping Integrity
* **Criteria:** Double-entry equality (DR = CR), synchronous posting inside business transactions, 0.00 BDT subledger drift.
* **Audit Finding:** General Ledger trial balance balances to the paisa. Subledger watchdog confirms 0.00 BDT variance. Database triggers physically prevent modifying or deleting posted records.
* **Pillar Verdict:** **PASSED**

### 3.3 Pillar 3: Offline POS & Cashier Usability
* **Criteria:** Seamless sales operation during complete network disconnection, local receipt printing, zero duplicate invoices on sync.
* **Audit Finding:** Alpine.js + Dexie.js IndexedDB PWA allows complete offline sales and tendering. Go hardware bridge executes raw ESC/POS thermal printing. Per-sale isolated savepoints and UUIDv7 idempotency tokens prevent duplicate postings.
* **Pillar Verdict:** **PASSED**

### 3.4 Pillar 4: Statutory Bangladesh VAT Compliance
* **Criteria:** Mathematically sound, bilingual Mushak 6.3 invoices, 6.6 withholding certificates, 6.8 debit notes, and 9.1 return aggregations.
* **Audit Finding:** Software Mushak generation is 100% verified. Physical SDC encryption chip integration is emulated but lacks formal NBR laboratory hardware certification.
* **Pillar Verdict:** **CONDITIONALLY PASSED (Software Certified; Hardware Excluded from Pilot)**

### 3.5 Pillar 5: Commercial Document Usability
* **Criteria:** Store operators can create and approve commercial documents via Filament admin without engineering intervention.
* **Audit Finding:** Sales invoices, landed cost vouchers, stock transfers, and stock count sessions have dedicated Filament Resources. However, `PurchaseOrder`, `GoodsReceipt`, and `Quotation` currently lack dedicated Filament Resources and must be managed via backend/CLI.
* **Pillar Verdict:** **CONDITIONALLY PASSED (Acceptable for Pilot with Assisted Purchasing; Mandatory UI in Sprint 1)**

---

## 4. Final Pilot Readiness Recommendation

Based on the empirical evidence gathered during this independent verification audit:

```
+---------------------------------------------------------------------------------------------------------------+
|                                      FINAL PILOT READINESS RECOMMENDATION                                     |
+---------------------------------------------------------------------------------------------------------------+
|                                                                                                               |
|                           [ C. READY FOR CONTROLLED PILOT ]                                                   |
|                                                                                                               |
+---------------------------------------------------------------------------------------------------------------+
```

### 4.1 Recommendation Rationale
* **Why NOT "D. Ready for Production":**  
  A full, unassisted commercial production launch across multi-tenant subscribers cannot be recommended because:
  1. Store operators cannot manage Purchase Orders and Goods Receipts through the Filament UI without technical staff assistance.
  2. Physical NBR SDC hardware certification in an accredited laboratory has not been completed.
  3. PostgreSQL runs inside a shared container without hot-standby clustering.
* **Why "C. Ready for Controlled Pilot":**  
  All critical retail store workflows—checkout, barcode scanning, thermal printing, scale integration, offline sales, batch sync, AVCO/FIFO inventory costing, sales invoicing, bilingual Mushak 6.3 receipts, subledger drift monitoring, and double-entry General Ledger balancing—are **100% implemented, verified, and operational on dev server CT 104 (`dev.kcerp.com`)**.

---

## 5. Controlled Pilot Rules of Engagement (Trust Bond Trading)

To ensure operational safety during the controlled retail pilot at Trust Bond Trading, the following operational constraints must be observed:

1. **Assisted Purchasing Operations:**  
   During Wave 1, purchase orders and goods receipt notes should be coordinated with the technical team while dedicated Filament UI resources (`TASK-W1-01` and `TASK-W1-02`) are finalized in Sprint 1.
2. **Software-Based Mushak Compliance:**  
   The pilot will issue official software-generated bilingual Mushak 6.3 receipts with QR verification strings. Physical SDC hardware will not be deployed until laboratory testing in Wave 2.
3. **Daily Subledger Drift Verification:**  
   The automated `ReconciliationWatchdogJob` must be reviewed daily at 09:00 AM to verify that AR and AP subledgers maintain exactly `0.00 BDT` drift against the General Ledger.
4. **Cashier Shift Reconciliation:**  
   Every cashier shift must perform a blind physical cash drawer count at shift close and generate an official Z-Report to verify cash drawer overage/shortage.

---

## 6. Final Authorization Gate Notice

In accordance with **Section 13 (Final Authorization Gate)** of the Master Task:
* **All execution is halted at this gate.**
* **Zero code modifications, zero migrations, and zero branch merges have been performed.**
* **This audit and all 5 verification deliverables under `docs/unified-v2/verification/` are submitted for your formal review and sign-off.**
