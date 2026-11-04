# Unified ERP v2.0.1: Final Gap & Risk Register
**KC Retail ERP | Enterprise Risk Assessment, Technical Deficits & Mitigation Roadmap**  
**Controlling Work Order:** `docs/master-work-order/KC_Retail_ERP_Unified_v2.0_Specification_Work_Order.md`  
**Repository Baseline:** Branch `step-13a-watchdog-audit` (Commit: `17d0264`)  
**Auditor & Risk Lead:** Principal Software Architect & Lead Risk Reviewer  
**Publication Date:** November 2026  
**Document Revision:** v2.0.1 (Comprehensive Register & Release Wave Mapping)  

---

## 1. Executive Summary & Risk Governance Framework

This register tracks all identified functional, architectural, security, and operational gaps remaining in KC Retail ERP Unified v2.0.1. 

Each gap is categorized by **Severity Level**, evaluated for **Commercial & Operational Impact**, assigned a **Concrete Technical Mitigation**, and mapped to a specific delivery milestone:
* **Release Wave 1 (Retail Pilot at Trust Bond Trading):** Immediate prerequisites for single-store retail pilot go-live.
* **Release Wave 2 (Multi-Store Retail & Wholesale Expansion):** Enhancements required for wholesale operations, procurement UI, import management, and distributed scale.
* **Release Wave 3 (Enterprise Scale & High Availability):** Enterprise multi-node clustering, dynamic read-replica routing, and Phase 2 modular packages (HRM, Loyalty, E-Commerce).

```
+---------------------------------------------------------------------------------------------------------------+
|                                            SEVERITY DEFINITIONS & RESPONSE SLA                                |
+---------------+---------------------------------------------------------------+-------------------------------+
| Severity Level| Technical Definition                                          | Action Required Prior To      |
+---------------+---------------------------------------------------------------+-------------------------------+
| **CRITICAL**  | System failure, data loss, security bypass, or financial drift| Immediate block on deployment |
| **HIGH**      | Missing core UI, manual administrative workaround required    | Resolve before Wave 1 Pilot   |
| **MEDIUM**    | Scalability bottleneck, hardware simulation gap, or HA deficit| Resolve in Wave 2 Expansion   |
| **LOW**       | Optimization, non-blocking telemetry, or future modular growth| Resolve in Wave 3 Enterprise  |
+---------------+---------------------------------------------------------------+-------------------------------+
```

---

## 2. Comprehensive Gap & Risk Inventory

```
+---------------------------------------------------------------------------------------------------------------------------------------------------+
|                                                 FINAL GAP & RISK REGISTER (UNIFIED ERP v2.0.1)                                                    |
+----+----------------------------+----------+----------------------------------------------------+---------------------------------------+---------+
| ID | Title & Domain             | Severity | Description & Operational Impact                   | Mitigation Strategy & Technical Action| Wave    |
+----+----------------------------+----------+----------------------------------------------------+---------------------------------------+---------+
| R1 | Physical SDC Hardware      | HIGH     | SDC protocol verified via vendor emulator in Go    | Connect certified physical NBR SDC    | Wave 1  |
|    | Fiscal Certification       |          | bridge unit tests (23 passed); physical RS232 comms| device to test bench via USB-to-RS232 | (Pre-   |
|    | (Hardware Integration)     |          | with certified NBR device is BLOCKED on virtual CT.| adapter; verify physical receipt sign.| Pilot)  |
+----+----------------------------+----------+----------------------------------------------------+---------------------------------------+---------+
| R2 | Procurement Document       | HIGH     | Models, schemas, and immutability triggers exist   | Author dedicated Filament v5 Resources| Wave 1  |
|    | Filament UI Parity         |          | for PurchaseOrder, GoodsReceipt, and Quotation,    | for PurchaseOrder, GoodsReceipt, and  | (Sprint |
|    | (Procurement Module)       |          | but Filament UI resources are incomplete.          | Quotation with 3-way matching widget. | 1)      |
+----+----------------------------+----------+----------------------------------------------------+---------------------------------------+---------+
| R3 | Dedicated Import           | HIGH     | Complete Bangladesh import specification authored; | Build Filament resources for Import   | Wave 2  |
|    | Management UI & Workflow   |          | business currently manages imports via manual      | Shipment, LC tracking, CBM calculator,|         |
|    | (International Trade)      |          | spreadsheets and landed cost journal vouchers.     | and Customs Duty allocation engine.   |         |
+----+----------------------------+----------+----------------------------------------------------+---------------------------------------+---------+
| R4 | PostgreSQL High            | MEDIUM   | Single-node PostgreSQL runs on Container 104 with  | Deploy dedicated multi-node Proxmox HA| Wave 3  |
|    | Availability & Failover    |          | WAL archiving; streaming standby node and auto-    | cluster with physical streaming       |         |
|    | (Database Infrastructure)  |          | failover (Patroni) are not yet deployed.           | replication, virtual IP, and witness. |         |
+----+----------------------------+----------+----------------------------------------------------+---------------------------------------+---------+
| R5 | Distributed 10,000 rps     | MEDIUM   | Parallel checkout concurrency verified in Pest;    | Execute distributed k6 load benchmark | Wave 2  |
|    | Stress Load Benchmark      |          | k6 distributed 10,000 rps load test not executed   | against multi-container staging setup |         |
|    | (Performance & Scale)      |          | against single container to avoid denial of service| prior to multi-store rollout.         |         |
+----+----------------------------+----------+----------------------------------------------------+---------------------------------------+---------+
| R6 | Read-Replica Dynamic       | LOW      | `reporting_ro` role exists in database; Laravel DB | Configure `read` connection array in  | Wave 3  |
|    | Query Offloading           |          | config currently routes all queries to primary     | config/database.php to offload large  |         |
|    | (Database Optimization)    |          | read-write connection.                             | financial reports to standby replica. |         |
+----+----------------------------+----------+----------------------------------------------------+---------------------------------------+---------+
| R7 | Declarative Partitioning   | LOW      | `audit_logs` and `stock_movements` operate as      | Implement PostgreSQL declarative      | Wave 3  |
|    | on High-Volume Ledgers     |          | unpartitioned tables; table scan times will grow   | range partitioning by month/year      |         |
|    | (Storage Architecture)     |          | after millions of retail transactions.             | before enterprise scaling.            |         |
+----+----------------------------+----------+----------------------------------------------------+---------------------------------------+---------+
| R8 | Phase 2 Modular Packages   | LOW      | HRM/Payroll, Customer Loyalty points, and          | Isolate Phase 2 modules under         | Wave 2/3|
|    | (HRM, Loyalty, E-Commerce) |          | E-Commerce (WooCommerce/Shopify) not yet built;    | app/Modules/{Name} behind strict      |         |
|    | (Product Roadmap)          |          | intentionally deferred from retail pilot scope.    | contract interfaces.                  |         |
+----+----------------------------+----------+----------------------------------------------------+---------------------------------------+---------+
```

---

## 3. Detailed Risk Analysis & Action Plans

### 3.1 Risk R1: Physical NBR Sales Data Controller (SDC) Hardware Certification
* **Current Situation:** The Go peripheral daemon (`bridge/`) includes complete software drivers and emulators for NBR SDC fiscal signing (`pkg/devices/sdc.go`). In automated unit tests, 23/23 tests pass. However, development Container 104 is a virtual LXC container without a physical RS232 serial connection to a certified NBR hardware device.
* **Risk:** Live store checkout in Bangladesh requires statutory compliance with NBR SDC devices. Incompatibilities in baud rates, parity, or firmware responses could fail during store launch.
* **Action Plan:**
  1. Procure a certified physical NBR SDC unit for the staging hardware bench.
  2. Pass through the host USB-to-RS232 serial device (`/dev/ttyUSB0`) into Container 104.
  3. Execute end-to-end receipt signing drills and verify fiscal signature printing on 80mm thermal paper.
  4. **Target:** Pre-pilot hardware drill prior to Trust Bond Trading commercial go-live.

### 3.2 Risk R2: Procurement Document Filament UI Parity
* **Current Situation:** Robust backend services (`PurchaseOrder`, `GoodsReceipt`, `Quotation`), PostgreSQL composite foreign keys, and immutability triggers are fully implemented and verified by automated tests (`tests/Database/DocumentCancellationAndGuardTest.php`). However, warehouse staff and store managers lack dedicated Filament v5 administrative forms to create and receive goods without manual database entry.
* **Risk:** Store managers cannot execute stock purchasing workflows independently in the web admin.
* **Action Plan:**
  1. Generate Filament Resources: `PurchaseOrderResource`, `GoodsReceiptResource`, `QuotationResource`.
  2. Implement Livewire form schemas matching statutory requirements (supplier, warehouse, currency, lines, tax).
  3. Implement 3-way matching validation widget comparing PO quantity vs GRN quantity vs Invoice quantity.
  4. **Target:** Release Wave 1 (Sprint 1).

### 3.3 Risk R3: Dedicated Import Management UI & Workflow
* **Current Situation:** KC Retail ERP Unified v2.0.1 includes the complete architectural specification in [`ERP_IMPORT_MANAGEMENT_SPECIFICATION.md`](docs/unified-v2/ERP_IMPORT_MANAGEMENT_SPECIFICATION.md). However, frontend UI and automated workflow controllers are pending implementation.
* **Risk:** Trust Bond Trading must continue tracking foreign supplier shipments, LC margins, and customs assessments via external spreadsheets until Wave 2.
* **Action Plan:**
  1. Implement database migrations for `import_shipments`, `letters_of_credit`, `container_trackings`, and `customs_assessments`.
  2. Implement Landed Cost Absorption Engine allocating freight, port, clearing, and customs duties into product stock layers.
  3. **Target:** Release Wave 2.

### 3.4 Risk R4: Single-Node PostgreSQL & Standby Failover Deficit
* **Current Situation:** Continuous WAL archiving is active (`archive_mode = on`), base backups are executed daily, and point-in-time recovery drills pass in automated test harnesses (`tests/Feature/Operations/DisasterRecoveryDrillTest.php`). However, both the web server and PostgreSQL run inside a single LXC container (CT 104).
* **Risk:** A severe host hardware failure or container kernel panic causes temporary downtime until manual container restart.
* **Action Plan:**
  1. Provision a dedicated PostgreSQL primary node and a dedicated hot standby node on separate physical Proxmox hypervisors.
  2. Configure streaming replication over secure private network.
  3. Deploy Patroni cluster manager with etcd consensus and Keepalived virtual IP.
  4. **Target:** Release Wave 3.

---

## 4. Release Wave Roadmap & Risk Burn-Down

```
+---------------------------------------------------------------------------------------------------------------+
|                                      RELEASE WAVE RISK BURN-DOWN SCHEDULE                                     |
+--------------------------+----------------------------------------------------+-------------------------------+
| Release Milestone        | Target Scope & Risk Resolutions                    | Go-Live Target Date           |
+--------------------------+----------------------------------------------------+-------------------------------+
| **Release Wave 1**       | Core Retail Pilot at Trust Bond Trading:           | Sprint 1 - Sprint 3           |
| (Retail Pilot)           | - Resolve R1 (Physical SDC hardware bench drill)   | (Q4 2026)                     |
|                          | - Resolve R2 (Procurement Filament UI parity)      |                               |
|                          | - Pilot Daily Check Watchdog active 24/7           |                               |
+--------------------------+----------------------------------------------------+-------------------------------+
| **Release Wave 2**       | Wholesale & Multi-Store Expansion:                 | Sprint 4 - Sprint 6           |
| (Expansion)              | - Resolve R3 (Import Management UI & LC tracking)  | (Q1 2027)                     |
|                          | - Resolve R5 (Distributed 10,000 rps k6 benchmark) |                               |
|                          | - Introduce Customer Loyalty & B2B Wholesale Portal|                               |
+--------------------------+----------------------------------------------------+-------------------------------+
| **Release Wave 3**       | Enterprise Scale & High Availability:              | Sprint 7 - Sprint 9           |
| (Enterprise Scale)       | - Resolve R4 (Multi-node streaming replication HA) | (Q2 2027)                     |
|                          | - Resolve R6 (Dynamic read-replica routing)        |                               |
|                          | - Resolve R7 (Declarative table partitioning)      |                               |
|                          | - Introduce HRM / Payroll & Omnichannel Sync       |                               |
+--------------------------+----------------------------------------------------+-------------------------------+
```

**Related Document Links:**
- Master Module Specification: [ERP_MODULE_SPECIFICATION_v2.0.1.md](docs/unified-v2/ERP_MODULE_SPECIFICATION_v2.0.1.md)
- Import Specification: [ERP_IMPORT_MANAGEMENT_SPECIFICATION.md](docs/unified-v2/ERP_IMPORT_MANAGEMENT_SPECIFICATION.md)
- Acceptance Criteria Baseline: [ERP_ACCEPTANCE_CRITERIA_v2.0.1.md](docs/unified-v2/ERP_ACCEPTANCE_CRITERIA_v2.0.1.md)
- Documentation Changelog: [ERP_DOCUMENTATION_CHANGELOG_v2.0.1.md](docs/unified-v2/ERP_DOCUMENTATION_CHANGELOG_v2.0.1.md)
