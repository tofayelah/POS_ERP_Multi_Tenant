# ERP System Design v1.4.0: Review

| | |
|---|---|
| **Document reviewed** | `ERP_SYSTEM_DESIGN-v5.md` (design v1.4.0, "Build-Ready Engineering Architecture Specification") |
| **Checked against** | Reviews of v1.3.0, v1.2.0 and v1.1.0 (issues NEW-01 to NEW-22) and the original review `ERP_DESIGN_REVIEW.md` v1.1 (100 findings) |
| **Review date** | 26 October 2026 |
| **Verdict** | Good enough to **start the first workstreams** (CI/CD, tenancy and RLS in weeks 1–5), but **not yet a frozen baseline**. The roadmap finally adds up, offline sync works, and background jobs have a working role. Two new High issues remain: issued tax invoices and stock movements can still be edited or deleted, and the Eid peak isn't planned for. Most other open items affect later streams, so they can be closed while weeks 1–5 run; section 8 lists what to settle before each stream starts. |

---

## Contents

1. [Summary](#1-summary)
2. [Status of the 22 issues from the last three reviews](#2-status-of-the-22-issues-from-the-last-three-reviews)
3. [New issues in v1.4.0](#3-new-issues-in-v140)
4. [Original 100 findings](#4-original-100-findings)
5. [Roadmap check](#5-roadmap-check)
6. [Documentation fixes](#6-documentation-fixes)
7. [Functional gaps, ADRs and product-owner questions](#7-functional-gaps-adrs-and-product-owner-questions)
8. [What to settle before each stream starts](#8-what-to-settle-before-each-stream-starts)
9. [Sources](#9-sources)

---

## 1. Summary

| Item | Result |
|---|---|
| 22 issues from the last three reviews | 12 resolved, 10 partly resolved |
| 100 original findings | 33 resolved, 46 partly, 18 not addressed, 3 descoped (3 newly resolved, including the roadmap) |
| New issues in v1.4.0 | 3 (2 High, 1 Medium) |
| Documentation fixes | 46 of 48 applied, 1 partly, 1 not applied; 3 new ones needed |

### What improved

- **Roadmap:** the dependencies now add up to 27 weeks, and all eight milestones match their tasks' end dates.
- **Offline sync:** a plain unique constraint means `ON CONFLICT` now matches, so batch sync works.
- **Background jobs:** the watchdog, rollups and outbox run as `app_rw` and set tenant context for each tenant they process.
- **Connections:** web instances are capped at two and the cost model agrees, so the 44-connection budget holds.
- **Schema:** tax codes are split from their effective-dated rates, journal lines and most company-scoped tables carry company-consistent keys, and there's a `sales_invoice_lines` table with rate snapshots and an `sdc_invoice_number` column.
- **Ledger rules:** posting checks that the period is open and that the posting date falls inside it, and an entry can be reversed only once.
- **Documentation:** the "Defect-Free" heading, the dedicated-database wireframe and most other earlier write-up problems are gone.

### What still needs work

1. Issued tax invoices, their lines and stock movements have no immutability protection, and deleting an invoice cascades to its lines (NEW-23).
2. Peak capacity is fixed at 24 PHP workers with no load test, and go-live lands about two weeks before Eid-ul-Adha (NEW-25).
3. Tax codes, terminals and invoice lines still escape company consistency (NEW-22).
4. NBR still hasn't confirmed terminal invoice series or provisional receipts (POS-03, TAX-01, NEW-18).
5. Schema gaps remain: suppliers, payments, price lists, barcodes and units, purchase orders, cost layers and number series (TEN-02).
6. Two High findings are still not addressed at all: tenant export and offboarding (TEN-06) and sync API versioning (POS-09).

---

## 2. Status of the 22 issues from the last three reviews

| ID | Severity | Status | Notes |
|---|---|---|---|
| NEW-01 | High | Partly | The changelog now largely matches the content, and the "Defect-Free" heading is gone. But the status is "Build-Ready Baseline" while three Critical findings are partly resolved, the open questions are still awaiting decisions in weeks 2–4, and the product-owner questions are unanswered. "Ready to start Stream A" would be accurate; "build-ready baseline" isn't yet. |
| NEW-02 | Critical | Resolved | Unchanged. |
| NEW-03 | Critical | Resolved | Unchanged. |
| NEW-04 | High | Resolved | Cross-tenant jobs run as `app_rw` and set tenant context per tenant. As tenants grow, make the hourly watchdog incremental (check only entries posted since its last run) and reset context between iterations. |
| NEW-05 | High | Resolved | Autoscaling is capped at two instances and the cost model and RSK-08 agree. Capacity under peak load is now a separate concern (NEW-25). |
| NEW-06 | High | Partly | Unchanged: installer and code signing, auto-update, supported terminal operating systems and health monitoring for the Go bridge still aren't covered. |
| NEW-07 | High | Resolved | Unchanged. |
| NEW-08 | High | Partly | Unchanged: rounding mode, cash rounding, discounts on tax-inclusive lines, and "the fiscalized receipt is the document of record" still aren't stated. |
| NEW-09 | Medium | Resolved | Unchanged. |
| NEW-10 | Medium | Partly | RSK-05 still lists two alternative stock policies. |
| NEW-11 | Medium | Resolved | Batch sync works again now that NEW-19 is fixed. |
| NEW-12 | Medium | Resolved | Unchanged. |
| NEW-13 | High | Resolved | Line changes also can't change `company_id` now. |
| NEW-14 | High | Resolved | Unchanged. |
| NEW-15 | High | Resolved | Unchanged. The `audit_logs` DDL with the `pending_hash` status is still not shown (section 6.2). |
| NEW-16 | Medium | Partly | Invoice lines added and `customers.current_due` removed. A payments table is still missing. |
| NEW-17 | Medium | Partly | Tax codes and rates are separated and lines snapshot the VAT rate. There's still no price-list, barcode or unit-conversion table; `mrp_price`, `wholesale_price` and `is_tax_inclusive` remain on variants. |
| NEW-18 | Medium | Partly | The `sdc_invoice_number` column was added. Still open: NBR confirmation of provisional receipts, which component owns the pending queue (browser or bridge), and the "10 consecutive" alert, which can be dodged by alternating. |
| NEW-19 | Medium | Resolved | A plain unique constraint now matches the `ON CONFLICT` target. |
| NEW-20 | Medium | Partly | `tax_codes` and `tax_code_rates` are now split. `btree_gist` is installed, but no exclusion constraint uses it, so overlapping rate periods are still possible. `tax_code_rates` also doesn't tie the rate's company to the code's company. |
| NEW-21 | Medium | Partly | The open-period and date checks and the single-reversal index were added, and reversals must stay in the same company. Nothing yet checks that the reversed entry is posted or that the reversal mirrors its amounts, and no check keeps `is_reversal` consistent with `reversed_entry_id`. |
| NEW-22 | Medium | Partly | Most company-scoped tables now have `UNIQUE (tenant_id, company_id, id)` with matching foreign keys. Gaps: `tax_codes` has no company-scoped key, so variants, journal lines, invoice lines and tax rates can use another company's tax code; `pos_terminals` has no `company_id`, so an invoice's terminal can belong to another company; invoice lines reference invoices by tenant only; and `sales_invoices` has no company-scoped key. §6.2's claim that cross-company references are "impossible" doesn't hold yet. |

---

## 3. New issues in v1.4.0

### NEW-23 · High · Issued tax invoices and stock movements can be edited or deleted (§9.1)
- **Issue:** The ledger is now well protected, but the tax invoice is the legal document behind Mushak 6.3 and the Mushak 6.2 sales register, and nothing protects it. `app_rw` can update or delete `sales_invoices` rows after they're issued, and `sales_invoice_lines` uses `ON DELETE CASCADE`, so deleting an invoice silently removes its lines and leaves a gap in the gapless numbering. `stock_movements` is described as an immutable ledger but has no trigger either; only `TRUNCATE` is revoked.
- **Fix:** Add a status to invoices (draft, issued, cancelled) and triggers that block updates and deletes once issued. Allow only a one-time fill of the fiscal fields (`sdc_invoice_number`, `fiscal_signature`) for invoices fiscalized after sync. Make corrections through credit notes (Mushak 6.7). Replace the cascade with `RESTRICT`, give `stock_movements` the same insert-only protection, and add tests.

### NEW-24 · Medium · Invoice lines can't fully represent a Mushak 6.3 invoice (§9.1)
- **Issue:** `sales_invoice_lines` snapshots the VAT rate, but not the supplementary duty rate and amount, which a Mushak 6.3 invoice shows per line. It has no line discount and doesn't say whether `unit_price` includes tax, although A-05 supports both tax-inclusive and tax-exclusive pricing. It has no batch number, which returns and expiry recalls need when stock is batch-tracked. Nothing checks that the invoice totals equal the sum of the lines.
- **Fix:** Add the supplementary duty rate and amount, line discount, an explicit pricing basis (or net, tax and gross amounts) and the batch number. Check at issue time that the header totals match the lines.

### NEW-25 · High · Peak-season capacity isn't planned, and go-live lands just before Eid-ul-Adha (§4.2, §13.2, RSK-08, §18)
- **Issue:**
  - RSK-08's mitigation for a 10x Eid surge is now "capped at 44 connections; autoscaling capped at 2 instances". That prevents connection exhaustion, but it also fixes peak capacity at 24 PHP workers. The capacity targets from v1.0 were dropped in v1.1.0, so nothing shows that 24 workers can handle Eid-level traffic.
  - Following the Gantt from 2 November 2026, the pilot runs from about 22 March to 2 May 2027 and go-live is the week of 3 May. Eid-ul-Adha is expected around 17–19 May 2027, subject to moon sighting, so the pre-Eid rush arrives about two weeks after launch.
  - The sync engine, data import and QA weeks (about 15 February to 21 March) overlap Ramadan and Eid-ul-Fitr (expected around 10–12 March), when pilot retailers are busiest and least able to spend time on data migration.
- **Fix:** Set capacity targets (requests per second, checkouts per second and concurrent users per tier) and load-test at Eid-level volume before go-live. Schedule transaction pooling and a larger database plan before the first Eid after launch, or move go-live to after Eid-ul-Adha. Freeze releases around both Eids, and plan pilot data import with the retailers before Ramadan starts.

---

## 4. Original 100 findings

### 4.1 Scorecard

| Status | Critical | High | Medium | Total | Change since v1.3.0 |
|---|---|---|---|---|---|
| Resolved | 5 | 14 | 14 | 33 | +3 |
| Partly resolved | 3 | 19 | 24 | 46 | −2 |
| Not addressed | 0 | 3 | 15 | 18 | −1 |
| Descoped | 0 | 0 | 3 | 3 | 0 |
| **Total** | **8** | **36** | **56** | **100** | |

### 4.2 Status changes since v1.3.0

| ID | Severity | Was | Now | Notes |
|---|---|---|---|---|
| PLAN-01 | High | Not addressed | Resolved | The dependencies add up to 27 weeks and every milestone matches its task's end date. Reporting, the notification gateway, the settings engine and Reverb still have no tasks (see PLAN-02). |
| TEN-04 | High | Partly | Resolved | Jobs set tenant context per tenant, and scheduled cross-tenant work iterates tenants. Add tests, and reset context after every job. |
| TAX-02 | High | Partly | Resolved | Data-driven tax codes with effective-dated rates, supplementary duty, a per-company tax regime and per-line rate snapshots. Close the overlap gap (NEW-20) and confirm rates with a VAT consultant. |
| TEN-07 | Medium | Unclear | Descoped | The Super-Admin wireframe now shows every tenant on a shared pool. State in ADR-002 that there's no dedicated-database tier. |

### 4.3 Other open findings that v1.4.0 changed

| ID | Severity | Status | What changed and what's left |
|---|---|---|---|
| TEN-02 | Critical | Partly | Company-consistent keys and invoice lines added. Still missing: suppliers, categories, payments, price lists, barcodes, unit conversions, stock locations, purchase orders and GRNs, cost layers, number series, and users, memberships and location scopes; foreign keys for `products.category_id` and `journal_lines.partner_id`; and the NEW-22 gaps. |
| ACC-03 | High | Partly | Posting now checks that the period is open and the date is in range. Year-end close and opening-balance import are still missing. |
| INV-01 | High | Partly | Movements are still mutable (NEW-23); there's no cost-layer table and no reconciliation to the GL inventory account. |
| TEN-03 | High | Partly | Job roles are defined. There's still no CI check of policy coverage and no list of global tables. |
| OBS-01 | High | Partly | The watchdog works now. Uptime checks, queue alerts, p95 dashboards and per-terminal sync telemetry are still missing. |
| PLAN-02 | High | Partly | QA runs alongside data import, but it's still a block at the end (weeks 19–20). The pilot depends only on data import, not on QA sign-off. The outbox still arrives in weeks 19–20, and the notification gateway, settings engine, Reporting module and Reverb have no tasks. |
| MOD-07 | Medium | Partly | The poller has a working role. Ordering per aggregate and cleanup of published rows are still missing, and polling every active tenant on each cycle will get slow as tenants grow. |
| PLAN-04 | Medium | Partly | RSK-08's mitigation is weaker than before (NEW-25). |
| ACC-06 | Medium | Not addressed | `customers.current_due` was removed, but receivables still appear in Sales, Procurement and Accounting. |
| DATA-06 | Medium | Partly | Status columns still have no check constraints, and nothing keeps `is_reversal` consistent with `reversed_entry_id`. |
| DATA-02 | Medium | Partly | See TEN-02. |

### 4.4 Full status list

**Resolved (33)**
- Critical: TEN-01, ACC-01, ACC-02, POS-01, POS-02
- High: TECH-01, TEN-04, MOD-01, MOD-05, MOD-06, INV-02, INV-05, POS-07, POS-08, TAX-02, SEC-01, SEC-02, DATA-07, PLAN-01
- Medium: TECH-02, TECH-03, TECH-04, MOD-03, MOD-04, POS-05, POS-11, UX-02, QA-01, CORE-01, CORE-02, CORE-03, CORE-04, DATA-01

**Partly resolved (46)**
- Critical: TEN-02, POS-03, TAX-01
- High: TEN-03, TEN-05, TEN-09, TEN-10, MOD-02, ACC-03, ACC-04, ACC-05, INV-01, POS-04, TAX-03, TAX-04, TAX-05, TAX-07, SEC-09, OPS-01, OPS-02, OBS-01, PLAN-02
- Medium: MOD-07, INV-03, INV-04, INV-06, POS-06, SEC-03, SEC-04, SEC-05, SEC-06, SEC-07, SEC-08, SEC-10, DATA-02, DATA-05, DATA-06, DATA-08, API-01, API-02, OPS-03, OPS-05, QA-02, QA-03, PLAN-04, UX-01

**Not addressed (18)**
- High: TEN-06, POS-09, PLAN-03
- Medium: TEN-08, TEN-11, MOD-09, ACC-06, ACC-07, POS-10, TAX-06, DATA-03, DATA-04, API-03, OPS-04, OPS-06, UX-03, UX-04, DOC-01

**Descoped (3)**
- Medium: MOD-08, TAX-08, TEN-07

---

## 5. Roadmap check

Working through the Gantt dependencies from the 2 November 2026 start now gives 27 weeks, and every milestone matches:

| Task | Weeks | Approximate dates |
|---|---|---|
| Architecture signoff and CI/CD | 1–2 | 2–15 Nov 2026 |
| Multi-tenancy, composite keys and RLS (M1) | 3–5 | 16 Nov – 6 Dec |
| IAM, global users and SoD | 6–8 | 7–27 Dec |
| Super-Admin billing and onboarding wizard | 9–10 | 28 Dec – 10 Jan 2027 |
| GL, chart of accounts and periods (M2) | 6–9 | 7 Dec – 3 Jan |
| VAT engine and Mushak forms (M3) | 10–13 | 4–31 Jan |
| AP/AR subledgers and bank reconciliation | 14–15 | 1–14 Feb |
| Product master, units and price lists | 6–8 | 7–27 Dec |
| Multi-warehouse, movements and costing | 9–12 | 28 Dec – 24 Jan |
| Stock counts, transfers and landed cost | 13–15 | 25 Jan – 14 Feb |
| Offline POS PWA shell | 9–12 | 28 Dec – 24 Jan |
| Print and fiscal bridge (M4) | 13–15 | 25 Jan – 14 Feb |
| POS sync engine (M5) | 16–18 | 15 Feb – 7 Mar |
| Data import and outbox / QA and security (M6) | 19–20 | 8–21 Mar |
| Live pilot (M7) | 21–26 | 22 Mar – 2 May |
| Go-live (M8) | 27 | 3–9 May 2027 |

**Remaining roadmap points:**
- The pilot depends only on data import; make it depend on QA and security sign-off too (the dates don't change, since both end in week 20).
- Four streams run in parallel in weeks 9–12 for a team of 3–5 that includes a tech lead and a QA/DevOps engineer, and the frontend developer moves from the PWA straight to a Go daemon in week 13. Assign people to streams to confirm this is achievable.
- The calendar overlaps Ramadan, Eid-ul-Fitr and the run-up to Eid-ul-Adha (NEW-25).
- The outbox, notification gateway, settings engine, Reporting module and Reverb still need tasks (PLAN-02).

---

## 6. Documentation fixes

### 6.1 Status of earlier fixes

Of the 48 fixes requested so far, 46 are applied. Newly applied in v1.4.0: "Tenant Organization" wording, roadmap arithmetic, changelog accuracy, the dedicated-tier wireframe, autoscaling aligned across sections, the SDC number column, and the unsourced AWS figure removed.

| Fix | Status | What's left |
|---|---|---|
| Absolute claims | Partly | The header's "Build-Ready" and "Comprehensive Resolution", §6.2's "impossible", ADR-002's "cryptographic isolation" (RLS isn't cryptographic) and ADR-003's "100% auditability" remain. |
| Mermaid validation in CI | Not applied | Not mentioned. |

### 6.2 New documentation fixes in v1.4.0

| Location | Problem | Fix |
|---|---|---|
| ADR-002 | Rejects database-per-tenant "for standard tiers", which implies an enterprise tier that no longer exists | Say there's no dedicated tier in Phase 1 |
| §18 Gantt | The pilot (f1) depends only on data import (e1), not on QA (e2) | Add the QA dependency |
| §12.4 | Refers to an `audit_logs.status = 'pending_hash'` column, but no `audit_logs` DDL appears anywhere | Add the DDL (see NEW-15) |

---

## 7. Functional gaps, ADRs and product-owner questions

**Functional gaps:** unchanged since v1.2.0: 4 covered in MVP, 4 scheduled for Phase 2, 7 partly addressed, 8 not addressed (cheque management, promotions, expenses and petty cash, approval engine, in-app help, custom fields, barcode and label generation, training mode and sandbox tenants).

**ADRs:** unchanged: 3 of the 17 recommended are recorded as ADRs (plus ADR-008 for the bridge), 10 are decided in the body text, and 4 are still undecided: hosting provider and region, tenant export and restore, search, and sync API versioning.

**Product-owner questions:** still open: the first retail segment, POS-only or accounting-only plans, and how pilot tenants pay. The legal decisions on in-country hosting and NBR acceptance of terminal series are also still pending. These are the main reason not to call the document a baseline yet.

---

## 8. What to settle before each stream starts

Most remaining items affect streams that start in week 6 or later, so work can begin now while they're closed.

| Before | Settle | Findings |
|---|---|---|
| **Week 1 (now)** | Relabel the status as ready for Stream A; answer the three product-owner questions; start the NBR and hosting legal questions, since their answers are needed by week 10 | NEW-01, OQ-01 to OQ-04 |
| **Week 6** (GL, catalog and IAM start) | Remaining schema (suppliers, payments, price lists, barcodes and units, number series); invoice and stock-movement immutability; tax-rate overlap and company gaps; reversal checks; identity and Filament tenancy decision | TEN-02, NEW-23, NEW-20, NEW-22, NEW-21, ACC-07, TEN-10 |
| **Week 9** (POS shell and warehouse start) | Rounding specification with shared golden tests; complete invoice-line columns; remaining offline policy items | NEW-08, NEW-24, POS-04 |
| **Week 10–13** (VAT engine and bridge) | NBR answers on terminal series, SDC numbering and provisional receipts; fallback queue ownership; bridge packaging and updates | POS-03, TAX-01, NEW-18, NEW-06 |
| **Week 16** (sync engine) | Sync API versioning, clock-skew handling, and a single stock policy | POS-09, POS-10, NEW-10 |
| **Week 21** (pilot) | Capacity targets and an Eid-level load test; tenant export and offboarding; alerting; failover runbook; external penetration test | NEW-25, TEN-06, OBS-01, OPS-02, SEC-08 |

---

## 9. Sources

- [Bangla Date Today: Bangladesh government holidays 2027 (expected Eid dates)](https://www.bangladatetoday.com/blog/bangladesh-government-holidays-2027)
- [DigitalOcean: PostgreSQL limits](https://docs.digitalocean.com/products/databases/postgresql/details/limits/)
- Earlier sources are listed in `ERP_DESIGN_REVIEW.md` v1.1 and the reviews of v1.1.0, v1.2.0 and v1.3.0.

Eid dates depend on moon sighting and can shift by a day. Regulatory statements in this review are a starting point for the design team, not legal or tax advice; confirm them with a Bangladesh VAT consultant and a lawyer.
