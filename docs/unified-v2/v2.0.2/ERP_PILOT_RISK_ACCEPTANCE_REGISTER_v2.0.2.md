# Unified ERP v2.0.2: Pilot Risk Acceptance & Governance Register
**KC Retail ERP | Statutory Compliance, Operational Boundaries & Residual Risk Sign-Off**  
**Controlling Work Order:** `docs/master-work-order/KC_Retail_ERP_Unified_v2.0_Specification_Work_Order.md`  
**Target Pilot:** Trust Bond Trading (`https://dev.kcerp.com/admin/trustbond`)  
**Repository Baseline:** Branch `step-13a-watchdog-audit` | Commit: `17d0264`  
**Auditor & Risk Governance Lead:** Principal Software Architect & Lead Risk Auditor  
**Publication Date:** November 2026  
**Document Revision:** v2.0.2 (Authoritative Risk Governance Protocol)  

---

## 1. Executive Summary & Legal / Operational Pilot Framework

A critical contradiction identified in earlier documentation was the coexistence of two mutually incompatible claims:
1. **Risk R1 (Physical SDC Hardware Certification)** was categorized as a **HIGH Severity / Pre-Pilot blocker**.
2. The Pilot Readiness report declared the system **READY FOR IMMEDIATE CONTROLLED PILOT**.

This document resolves this conflict through a formal **Risk Governance Framework**:
* **The Legal Reality under Bangladesh VAT Law:** Under the **Value Added Tax and Supplementary Duty Act 2012** and **VAT & SD Rules 2016 (Rule 40)**, retail and trading enterprises are permitted to issue computerized **Mushak 6.3 Tax Invoices** via software, provided the invoices feature gapless alphanumeric serial numbers, bilingual text (Bengali/English), statutory tax/SD breakdowns, and verified NBR QR codes. However, for retail establishments formally notified by the National Board of Revenue (NBR) to deploy Electronic Fiscal Device (EFD) / Sales Data Controller (SDC) hardware, physical integration with an NBR-authorized physical SDC is mandatory.
* **Governance Rule:** A **HIGH-severity risk cannot silently be treated as accepted**. The pilot at Trust Bond Trading may proceed **strictly as a Controlled Software-Based Mushak 6.3 Pilot**, provided that all residual risks are formally accepted in writing by authorized stakeholders with explicit review dates and mitigation protocols.

---

## 2. Formally Accepted Residual Risks Register

```
+---------------------------------------------------------------------------------------------------------------------------------------------------------------+
|                                              FORMALLY ACCEPTED RESIDUAL RISKS REGISTER (PILOT PHASE)                                          |
+----+----------------------------+----------+--------------------+-----------------------+--------------------+----------------------+---------+---------------+
| ID | Risk Title & Scope         | Severity | Risk Owner         | Mitigation Protocol   | Monitoring Freq    | Escalation Path      | Expiry  | Formal Status |
+----+----------------------------+----------+--------------------+-----------------------+--------------------+----------------------+---------+---------------+
| R1 | Physical NBR SDC Hardware  | HIGH     | Retail Operations  | Issue software-based  | Daily review of    | Escalate to Managing | 30 Days | CONDITIONAL   |
|    | Absence (Live Signing)     |          | Director & CFO     | bilingual Mushak 6.3  | fiscal queue in    | Director if NBR      | from    | ACCEPTANCE    |
|    | Scope: In-store checkout   |          |                    | invoices with QR code;| POS terminal shifts| inspection scheduled.| Launch  | (Written Sign)|
|    | at Trust Bond Trading      |          |                    | log fiscal payloads.  |                    |                      |         |               |
+----+----------------------------+----------+--------------------+-----------------------+--------------------+----------------------+---------+---------------+
| R2 | Procurement Document UI    | HIGH     | Head of Supply     | Warehouse managers use| Weekly procurement | Escalate to Tech Lead| Sprint 1| CONDITIONAL   |
|    | Filament Absence           |          | Chain & Lead DBA   | backend document seed | voucher audit and  | if purchasing delays | (Wave 1)| ACCEPTANCE    |
|    | Scope: PO / GRN forms      |          |                    | or assisted Livewire  | subledger review.  | exceed 24 hours.     |         | (Sprint 1 UI) |
|    | in admin web panel         |          |                    | review during pilot.  |                    |                      |         |               |
+----+----------------------------+----------+--------------------+-----------------------+--------------------+----------------------+---------+---------------+
| R4 | Single-Node PostgreSQL     | MEDIUM   | Lead DevOps Eng &  | Continuous WAL archive| Automated daily ZFS| Container failover   | Wave 3  | ACCEPTED      |
|    | Host Container             |          | Principal DBA      | active; daily base    | snapshot check;    | drill to standby host| Rollout | FOR PILOT     |
|    | Scope: Container 104       |          |                    | backup verified via   | weekly PITR test.  | if downtime > 15 min.|         | (Single Store)|
|    | hosting web & database     |          |                    | automated drill script|                    |                      |         |               |
+----+----------------------------+----------+--------------------+-----------------------+--------------------+----------------------+---------+---------------+
| R5 | Unexecuted 10,000 rps      | MEDIUM   | Principal Systems  | Single-store retail   | Real-time latency  | Enable Redis queue   | Wave 2  | ACCEPTED      |
|    | Stress Load Benchmark      |          | Architect          | traffic estimated at  | monitoring via     | throttling if CPU    | Rollout | FOR PILOT     |
|    | Scope: Distributed peak    |          |                    | < 50 rps; concurrency | Horizon dashboard; | utilization > 75%.   |         | (< 50 rps cap)|
|    | load capacity              |          |                    | proven in Pest tests. | alert at P95 > 1s. |                      |         |               |
+----+----------------------------+----------+--------------------+-----------------------+--------------------+----------------------+---------+---------------+
```

---

## 3. Detailed Governance Protocols for Accepted Risks

### 3.1 Protocol R1: Physical NBR SDC Hardware Absence
* **Condition of Acceptance:** 
  1. The retail pilot is conducted exclusively at Trust Bond Trading's pilot facility under software Mushak 6.3 compliance.
  2. The POS PWA continues formatting ESC/POS receipts with NBR QR codes, taxpayer BIN, sequential invoice number, and tax amounts.
  3. All unfiscalized transactions are queued in IndexedDB and backend databases with `fiscal_status = 'pending_physical_sdc'`.
  4. A certified physical SDC test bench must be assembled within 30 days of pilot launch to finalize physical serial driver certification.
* **Sign-Off Gate:** Requires countersignature from **Managing Director** and **Chief Financial Officer**.

### 3.2 Protocol R2: Procurement Filament UI Parity
* **Condition of Acceptance:**
  1. Core purchasing rules, 3-way matching logic, and immutability triggers are active in the database.
  2. Standard stock inward receipts are processed using existing inventory adjustment or assisted seed commands during Sprint 1.
  3. Dedicated Filament v5 Resources (`PurchaseOrderResource`, `GoodsReceiptResource`) must be delivered in Sprint 1 as the first engineering deliverable.
* **Sign-Off Gate:** Requires approval from **Head of Supply Chain**.

### 3.3 Protocol R4: Single-Container Hosting
* **Condition of Acceptance:**
  1. Single container hosting on CT 104 is accepted strictly for single-store pilot operations (Trust Bond Trading).
  2. Automated daily ZFS snapshots and continuous WAL archiving must remain active 24/7.
  3. `php artisan pilot:daily-check` must run every 24 hours to prove zero financial and subledger drift.
  4. Multi-node streaming replication must be deployed before onboarding Store #2.
* **Sign-Off Gate:** Requires approval from **Principal Database Administrator**.

---

## 4. Formal Stakeholder Risk Acceptance Form

```
+---------------------------------------------------------------------------------------------------------------+
|                                      FORMAL PILOT RESIDUAL RISK ACCEPTANCE FORM                               |
+--------------------------+------------------------------------+-----------------------+-----------------------+
| Governance Role          | Authorized Representative          | Acceptance Status     | Formal Signature Date |
+--------------------------+------------------------------------+-----------------------+-----------------------+
| **Managing Director**    | Executive Sponsor                  | PENDING HUMAN SIGN-OFF| Required Before Pilot |
+--------------------------+------------------------------------+-----------------------+-----------------------+
| **Chief Financial Officer| Finance & Tax Governance Lead      | PENDING HUMAN SIGN-OFF| Required Before Pilot |
+--------------------------+------------------------------------+-----------------------+-----------------------+
| **Retail Operations Head**| Store Usability & Cashier Lead    | PENDING HUMAN SIGN-OFF| Required Before Pilot |
+--------------------------+------------------------------------+-----------------------+-----------------------+
| **Principal Architect**  | Technical & Systems Lead           | **RECOMMENDED WITH    | November 4, 2026      |
|                          |                                    |   CONDITIONS**        |                       |
+--------------------------+------------------------------------+-----------------------+-----------------------+
```

**Authoritative Status:** **PILOT RISK ACCEPTANCE PROTOCOL FORMALIZED**
