# Unified ERP v2.0.2: Import & International Trade Management Specification
**KC Retail ERP | Enterprise Foreign Trade, Customs Valuation, Multi-Currency & Landed Cost**  
**Controlling Work Order:** `docs/master-work-order/KC_Retail_ERP_Unified_v2.0_Specification_Work_Order.md`  
**Revision:** v2.0.2 (Comprehensive Statutory & Technical Refinement)  
**Publication Date:** November 2026  
**Module ID:** `MOD-IMP-INT`  
**Status:** `SPECIFICATION RECONCILED - TARGET: RELEASE WAVE 2`  
**Compliance Standards:** Bangladesh Customs Act 2023 / 1969, VAT Act 2012, Foreign Exchange Regulation Act 1947, Bangladesh Bank Foreign Exchange Guideline (Vol 1 & 2), IAS 21 (The Effects of Changes in Foreign Exchange Rates), IAS 2 (Inventories)  

> [!IMPORTANT]
> **Professional Statutory Validation Disclaimer:**
> Statutory customs tariff schedules, duty rates (CD, RD, SD, VAT, AIT, AT), minimum assessable valuation bases, and rebate eligibility rules cited herein are modeled on Bangladesh National Board of Revenue (NBR) statutory frameworks. Actual tariff rates vary by specific 8-digit HS Code, SRO exemptions, and fiscal budget enactments. All tariff formulas are configurable and MUST be validated by a licensed Bangladesh Customs Clearing Agent (C&F) and Chartered Tax Professional prior to commercial deployment.

---

## 1. Executive Summary & Enterprise Foreign Trade Architecture

`MOD-IMP-INT` provides a comprehensive international trade management subsystem designed specifically for Bangladesh commercial importers, distributors, and retailers. It governs the end-to-end foreign procurement lifecycle:
1. International Supplier Proforma Invoices (PI) with 8-digit HS Code mapping.
2. Commercial banking instruments (LC margin, opening commission, amendments, retirement, advance TT).
3. Multi-stage foreign currency snapshots and IAS 21 realized/unrealized FX gain and loss posting.
4. Logistics, container tracking (20ft, 40ft, 40HQ), CBM volumetric allocation, and shipping line demurrage.
5. Dynamic, versioned customs duty schedules calculating CD, RD, SD, VAT, AIT, and AT.
6. Statutory Bill of Entry (B/E) matching, assessment overrides, and C&F port clearance.
7. Landed cost inventory absorption into moving-average AVCO and FIFO stock layers.
8. Landed cost clearing account (GL 1550) reconciliation and shortage/damage claims.

---

## 2. Dynamic Tariff Configuration & Versioned Customs Schedules

### 2.1 HS Code & Effective-Date-Based Tariff Schedules
Rather than hardcoding static tax percentages, the system models customs duties as versioned, effective-date-driven tariff records linked to 8-digit Harmonized System (HS) Codes:

```
+-----------------------------------------------------------------------------------------------------------------------------------------------+
|                                            CUSTOMS TARIFF SCHEDULE DATA SCHEMA (SAMPLE)                                                       |
+----------+-----------------------+------------+------------+---------+---------+---------+----------+----------+---------+------------------+
| HS Code  | Description           | Valid From | Valid To   | CD Rate | RD Rate | SD Rate | VAT Rate | AIT Rate | AT Rate | SRO Exemption Ref|
+----------+-----------------------+------------+------------+---------+---------+---------+----------+----------+---------+------------------+
| 8471.30  | Laptops & Tablets     | 2026-07-01 | 2027-06-30 | 0.00%   | 0.00%   | 0.00%   | 15.00%   | 5.00%    | 5.00%   | SRO 142-AIN/2026 |
| 8528.52  | Computer Monitors     | 2026-07-01 | 2027-06-30 | 5.00%   | 0.00%   | 0.00%   | 15.00%   | 5.00%    | 5.00%   | Standard Tariff  |
| 3926.90  | Plastic Accessories   | 2026-07-01 | 2027-06-30 | 25.00%  | 3.00%   | 20.00%  | 15.00%   | 5.00%    | 5.00%   | Standard Tariff  |
+----------+-----------------------+------------+------------+---------+---------+---------+----------+----------+---------+------------------+
```

### 2.2 Configurable Statutory Duty Calculation Engine
Customs assessment adheres to the mathematical sequence prescribed by the Bangladesh Customs Act 2023:
1. **Invoice CIF Value in Foreign Currency:**
   $$\text{CIF}_{\text{FC}} = \text{FOB Value} + \text{International Freight} + \text{Marine Insurance}$$
2. **Assessable Value (AV) in BDT:**
   $$\text{AV}_{\text{BDT}} = (\text{CIF}_{\text{FC}} \times \text{Customs Exchange Rate}) \times (1 + \text{Landing Charge Rate})$$
   *(Note: Landing charge is typically 1.0% of CIF BDT unless explicitly waived).*
3. **Duty Components:**
   * **Customs Duty (CD):** $\text{CD} = \text{AV}_{\text{BDT}} \times \text{Rate}_{\text{CD}}$
   * **Regulatory Duty (RD):** $\text{RD} = \text{AV}_{\text{BDT}} \times \text{Rate}_{\text{RD}}$
   * **Supplementary Duty (SD):** $\text{SD} = (\text{AV}_{\text{BDT}} + \text{CD} + \text{RD}) \times \text{Rate}_{\text{SD}}$
   * **Value Added Tax (VAT):** $\text{VAT} = (\text{AV}_{\text{BDT}} + \text{CD} + \text{RD} + \text{SD}) \times \text{Rate}_{\text{VAT}}$
   * **Advance Income Tax (AIT):** $\text{AIT} = \text{AV}_{\text{BDT}} \times \text{Rate}_{\text{AIT}}$
   * **Advance Tax (AT):** $\text{AT} = (\text{AV}_{\text{BDT}} + \text{CD} + \text{RD} + \text{SD}) \times \text{Rate}_{\text{AT}}$
4. **Total Statutory Customs Dues Payable:**
   $$\text{Total Dues} = \text{CD} + \text{RD} + \text{SD} + \text{VAT} + \text{AIT} + \text{AT}$$

### 2.3 Import Assessment Overrides & Audit Trail
In practice, Bangladesh Customs Assessment Officers frequently reject declared commercial invoice values and impose higher **Customs Value Benchmarks** or reassess tariff codes:
* The system allows authorized users (`Customs Assessment Override Permission`) to adjust assessable values or specific duty amounts to match the physical **Bill of Entry Assessment Notice**.
* Every override requires a mandatory justification code, supporting document attachment (PDF copy of B/E assessment), and dual authorization from the CFO or Head of Accounts.
* All changes are permanently recorded in `import_assessment_audit_logs`.

---

## 3. Commercial Banking, LC Lifecycle & Multi-Stage FX Snapshots

### 3.1 Multi-Stage Exchange Rate Snapshots
Because an import cycle in Bangladesh spans 30 to 120 days from contract to warehouse delivery, foreign exchange fluctuations occur across multiple milestones:

```
+---------------------------------------------------------------------------------------------------------------+
|                                    MULTI-STAGE EXCHANGE RATE SNAPSHOT MATRIX                                  |
+----+----------------------+-------------------+-----------------------+---------------------------------------+
| #  | Milestone            | Rate Captured     | Source of Rate        | Accounting & Operational Impact       |
+----+----------------------+-------------------+-----------------------+---------------------------------------+
| 1  | PI Contract Booking  | Booking Spot Rate | Commercial Bank       | Estimated landed cost calculation.    |
+----+----------------------+-------------------+-----------------------+---------------------------------------+
| 2  | LC Opening           | Margin FX Rate    | Issuing Bank          | Binds BDT cash margin deposit.        |
+----+----------------------+-------------------+-----------------------+---------------------------------------+
| 3  | Customs B/E Filing   | Customs Valuation | NBR Published Weekly  | Determines statutory tax in BDT.      |
+----+----------------------+-------------------+-----------------------+---------------------------------------+
| 4  | Bank Bill Retirement | Settlement Rate   | Commercial Bank Spot  | Actual BDT paid to foreign supplier.  |
+----+----------------------+-------------------+-----------------------+---------------------------------------+
| 5  | Balance Sheet Date   | Revaluation Rate  | Bangladesh Bank Mid   | Unrealized FX gain/loss on open AP.   |
+----+----------------------+-------------------+-----------------------+---------------------------------------+
```

### 3.2 Realized vs Unrealized FX Accounting (IAS 21)
1. **At LC Retirement / Supplier Payment:**
   * If $\text{Settlement Rate} > \text{Booking Rate}$: Realized Foreign Exchange Loss (Debit GL 8100).
   * If $\text{Settlement Rate} < \text{Booking Rate}$: Realized Foreign Exchange Gain (Credit GL 7100).
2. **At Accounting Period Close:**
   * Open foreign supplier payables are revalued against the closing rate.
   * Unrealized FX gain/loss is posted to `GL 8150 - Unrealized Foreign Exchange Gain/Loss` and reversed on the first day of the subsequent period.

### 3.3 LC Amendments & Banking Charges
* Tracks LC amendments (validity extension, value amendment, partial shipment amendment, port changes).
* Captures bank commissions, SWIFT charges, VAT on bank charges (15%), and stamp duties:
  ```
  Debit:  5400 - Banking Charges (Commission + SWIFT)
  Debit:  1310 - Rebatable Input VAT (15% on Bank Charges)
  Credit: 1010 - Bank Current Account
  ```

---

## 4. Multi-Line PIs, Partial Shipments & Container Tracking

### 4.1 Multiple PI Lines & Split Commercial Shipments
* An Import Contract / Proforma Invoice supports dozens of SKU lines.
* **Partial Shipments:** A single LC may be executed across multiple partial shipments (`import_shipments`):
  - Shipment 1: 500 units of SKU-A and 200 units of SKU-B on Vessel X (Container ABCD1234567).
  - Shipment 2: Remaining 500 units of SKU-A on Vessel Y (Container EFGH9876543).
* Each shipment possesses its own Bill of Lading (B/L), Bill of Entry (B/E), and C&F expense ledger.

### 4.2 Container & CBM Logistics Allocation
* **Container Metrics:** 20ft ($28 \text{ CBM}$ / $21.5 \text{ MT}$), 40ft ($58 \text{ CBM}$ / $26.5 \text{ MT}$), 40HQ ($68 \text{ CBM}$ / $26.5 \text{ MT}$).
* Freight and clearing costs are apportioned across items based on user-selected strategies:
  1. **Volumetric (CBM):** Standard for bulky, lightweight items (e.g., plastics, apparel, packaging).
  2. **Gross Weight (KG):** Standard for heavy items (e.g., hardware, steel, liquids).
  3. **Commercial Invoice Value:** Standard for high-value electronic goods.

---

## 5. Landed Cost Absorption & Clearing Account Reconciliation

### 5.1 Landed Cost Clearing Account (GL 1550) Mechanics
All capitalized import expenses are accumulated in **`GL 1550 - Landed Cost Clearing Account`** prior to physical goods receipt:

```
+---------------------------------------------------------------------------------------------------------------+
|                                    IMPORT EXPENSE ALLOCATION & GL ROUTING TABLE                               |
+----+----------------------------+----------+-----------------------+------------------------------------------+
| #  | Expense Component          | Rebate?  | Target GL Account     | Capitalized into Inventory Unit Cost?    |
+----+----------------------------+----------+-----------------------+------------------------------------------+
| 1  | FOB Product Purchase Price | NO       | 1550 Landed Cost Clr  | **YES (Direct Purchase Cost)**           |
| 2  | Ocean / Air Freight        | NO       | 1550 Landed Cost Clr  | **YES (Capitalized to Inventory)**       |
| 3  | Marine Cargo Insurance     | NO       | 1550 Landed Cost Clr  | **YES (Capitalized to Inventory)**       |
| 4  | Customs Duty (CD)          | NO       | 1550 Landed Cost Clr  | **YES (Capitalized to Inventory)**       |
| 5  | Regulatory Duty (RD)       | NO       | 1550 Landed Cost Clr  | **YES (Capitalized to Inventory)**       |
| 6  | Supplementary Duty (SD)    | NO       | 1550 Landed Cost Clr  | **YES (Capitalized to Inventory)**       |
| 7  | Import VAT (15%)           | **YES**  | 1310 Input VAT Asset  | **NO (Rebated via Mushak 9.1)**          |
| 8  | Advance Income Tax (AIT)   | **YES**  | 1330 AIT Tax Asset    | **NO (Offset against Corp Income Tax)**  |
| 9  | Advance Tax (AT 5%)        | **YES**  | 1320 AT Tax Asset     | **NO (Rebated via Mushak 9.1)**          |
| 10 | Port Demurrage & Dues      | NO       | 1550 Landed Cost Clr  | **YES (Capitalized to Inventory)**       |
| 11 | C&F Agency Clearance Fees  | NO       | 1550 Landed Cost Clr  | **YES (Capitalized to Inventory)**       |
| 12 | Inland Transport to Depot  | NO       | 1550 Landed Cost Clr  | **YES (Capitalized to Inventory)**       |
+----+----------------------------+----------+-----------------------+------------------------------------------+
```

### 5.2 Inventory Absorption & Clearing Reconciliation
Upon container destuffing and Central Warehouse Goods Receipt Note (GRN) posting:
1. The **Landed Cost Allocation Service** calculates the true unit cost per SKU:
   $$\text{Landed Unit Cost} = \frac{\text{FOB Value} + \text{Allocated Non-Rebatable Duty} + \text{Allocated Freight \& Port Charges}}{\text{Actual Sound Received Quantity}}$$
2. **The Absorption Journal:**
   ```
   Debit:  1400 - Merchandise Inventory Asset (at True Landed Cost)
   Credit: 1550 - Landed Cost Clearing Account (Full absorbed balance)
   ```
3. **Reconciliation Guarantee:** Once all stock layers from a shipment are absorbed, the balance of `GL 1550` for that shipment job returns to exactly **0.00 BDT**. Any residual variance caused by minor rounding is cleared to `GL 5990 - Landed Cost Variance`.

---

## 6. Shortage, Damage & Insurance Claim Handling

When physical container destuffing reveals missing or damaged items:
* **Goods Receipt Note (GRN):** Records three distinct quantity fields: `Shipped Quantity`, `Sound Received Quantity`, and `Damaged / Missing Quantity`.
* **Sound Stock:** Capitalized into inventory at true landed cost.
* **Damaged / Missing Stock:**
  ```
  Debit:  1390 - Marine Insurance Claims Receivable (at Landed Cost)
  Credit: 1550 - Landed Cost Clearing Account
  ```
  The business files a formal claim against the marine insurance underwriter without distorting sound stock inventory valuation.

---

## 7. Relational Schema Architecture (PostgreSQL 17)

```sql
-- 1. Versioned Customs Tariff Schedules
CREATE TABLE customs_tariff_schedules (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL,
    hs_code VARCHAR(16) NOT NULL,
    description VARCHAR(255) NOT NULL,
    valid_from DATE NOT NULL,
    valid_to DATE NOT NULL,
    cd_rate NUMERIC(6, 4) NOT NULL DEFAULT 0.0000,
    rd_rate NUMERIC(6, 4) NOT NULL DEFAULT 0.0000,
    sd_rate NUMERIC(6, 4) NOT NULL DEFAULT 0.0000,
    vat_rate NUMERIC(6, 4) NOT NULL DEFAULT 0.1500,
    ait_rate NUMERIC(6, 4) NOT NULL DEFAULT 0.0500,
    at_rate NUMERIC(6, 4) NOT NULL DEFAULT 0.0500,
    sro_reference VARCHAR(128),
    created_at TIMESTAMPTZ NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL,
    UNIQUE (tenant_id, hs_code, valid_from)
);

-- 2. Import Shipments (Partial Shipment Header)
CREATE TABLE import_shipments (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL,
    company_id UUID NOT NULL,
    contract_id UUID NOT NULL REFERENCES import_contracts(id),
    shipment_number VARCHAR(64) NOT NULL,
    bill_of_lading_number VARCHAR(64) NOT NULL,
    bl_date DATE NOT NULL,
    vessel_name VARCHAR(128),
    voyage_number VARCHAR(64),
    port_of_loading VARCHAR(64),
    port_of_discharge VARCHAR(64),
    eta_date DATE,
    status VARCHAR(32) NOT NULL DEFAULT 'in_transit',
    created_at TIMESTAMPTZ NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL,
    UNIQUE (tenant_id, shipment_number)
);

-- 3. Bill of Entry Customs Assessment
CREATE TABLE bill_of_entries (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL,
    company_id UUID NOT NULL,
    shipment_id UUID NOT NULL REFERENCES import_shipments(id),
    be_number VARCHAR(64) NOT NULL,
    be_date DATE NOT NULL,
    customs_house VARCHAR(64) NOT NULL,
    assessable_value_bdt NUMERIC(18, 4) NOT NULL,
    customs_duty NUMERIC(18, 4) NOT NULL,
    regulatory_duty NUMERIC(18, 4) NOT NULL,
    supplementary_duty NUMERIC(18, 4) NOT NULL,
    import_vat NUMERIC(18, 4) NOT NULL,
    advance_income_tax NUMERIC(18, 4) NOT NULL,
    advance_tax NUMERIC(18, 4) NOT NULL,
    is_overridden BOOLEAN NOT NULL DEFAULT FALSE,
    override_reason TEXT,
    override_approved_by UUID,
    out_of_charge_date DATE,
    created_at TIMESTAMPTZ NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL,
    UNIQUE (tenant_id, be_number)
);
```

**Authoritative Status:** **IMPORT MANAGEMENT SPECIFICATION RECONCILED & REFINED (v2.0.2)**
