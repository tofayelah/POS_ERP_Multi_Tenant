# ERP System Design v1.3.0: Review

| | |
|---|---|
| **Document reviewed** | `ERP_SYSTEM_DESIGN-v4.md` (design v1.3.0, "Comprehensive Forensic Architecture Specification") |
| **Checked against** | Reviews of v1.2.0 (`ERP_DESIGN_REVIEW_v1.2.0.md`, NEW-13 to NEW-18), v1.1.0 (`ERP_DESIGN_REVIEW_v2.md`, NEW-01 to NEW-12) and the original review (`ERP_DESIGN_REVIEW.md` v1.1, 100 findings) |
| **Review date** | 26 October 2026 |
| **Verdict** | The closest to build-ready so far. The ledger is now properly protected, platform staff no longer see tenant ledgers, and the audit chain no longer blocks checkout. **Not build-ready yet:** the offline sync statement won't run against the new index, background jobs have no role that can read the data, the roadmap adds up to 30 weeks rather than the 27 it now states, and NBR confirmations are still outstanding. No new High issues were introduced. |

---

## Contents

1. [Summary](#1-summary)
2. [Status of the 18 issues from the last two reviews](#2-status-of-the-18-issues-from-the-last-two-reviews)
3. [New issues in v1.3.0](#3-new-issues-in-v130)
4. [Original 100 findings](#4-original-100-findings)
5. [Roadmap check](#5-roadmap-check)
6. [Documentation fixes](#6-documentation-fixes)
7. [Functional gaps, ADRs and product-owner questions](#7-functional-gaps-adrs-and-product-owner-questions)
8. [Recommended next steps](#8-recommended-next-steps)
9. [Sources](#9-sources)

---

## 1. Summary

| Item | Result |
|---|---|
| 18 issues from the v1.1.0 and v1.2.0 reviews | 8 resolved, 10 partly resolved (one regressed) |
| 100 original findings | 30 resolved, 48 partly, 19 not addressed, 3 descoped or unclear (4 newly resolved, including two Critical) |
| New issues in v1.3.0 | 4, all Medium |
| Documentation fixes | 39 of 45 applied, 4 partly, 2 not applied; 3 new ones needed |

### What improved

- **Ledger protection is complete:** posted entries can't be inserted, edited, deleted or have lines moved, and line changes lock the header so they can't race with posting. Critical findings ACC-01 and ACC-02 are now resolved.
- **Platform access:** `platform_ro` lost its direct ledger access and now reads only rollups and aggregate functions.
- **Audit chain:** audit rows are written without a lock, and a background worker builds the hash chain, so checkouts no longer queue behind one another.
- **Schema:** a `tax_codes` table with effective dates and supplementary duty, a `customers` table, SKUs scoped per company, and back-office invoices allowed without a terminal.
- **Roles:** Sales Manager and Procurement Manager columns complete the permission matrix.
- **Fixes to earlier write-ups:** a realistic connection budget (44 connections against DigitalOcean's 47), the OWASP table aligned with the 2025 edition, a dedicated QA and security stream restored, fiscal fallback fraud controls, both SDC and terminal invoice numbers printed, and the LaTeX formatting removed.

### What still blocks a build

1. The offline sync `INSERT ... ON CONFLICT` doesn't match the new partial unique index, so every batch sync will fail with an error (NEW-19).
2. The integrity watchdog, rollup jobs and outbox poller have no role that can read tenant data under forced RLS (NEW-04).
3. The roadmap adds up to 30 weeks, not 27, and four of the seven milestones fall before their tasks finish (PLAN-01).
4. The budget allows two web instances, but the cost model and RSK-08 still autoscale to three, which exceeds the connection limit (NEW-05).
5. Schema gaps remain: invoice lines, payments, price lists, barcodes and units, suppliers, tax-rate periods and company consistency (TEN-02, NEW-16, NEW-17, NEW-20, NEW-22).
6. NBR still hasn't confirmed terminal invoice series or provisional receipts (POS-03, TAX-01, NEW-18).

---

## 2. Status of the 18 issues from the last two reviews

| ID | Severity | Status | Notes |
|---|---|---|---|
| NEW-01 | High | Partly | The status is now "Pending Final Product Owner Review", but the header still claims "Complete Resolution", §9.2 is still titled "Complete, Defect-Free", and the changelog claims milestones are "strictly aligned" (they aren't; see section 5) and a price-list schema that doesn't exist. |
| NEW-02 | Critical | Resolved | Completed by the NEW-13 fixes. |
| NEW-03 | Critical | Resolved | Unchanged since v1.2.0. |
| NEW-04 | High | Partly | `reporting_ro` is covered and `platform_ro` is narrowed, but cross-tenant jobs now have no working role. The watchdog in §14 runs as `platform_ro`, which can no longer read the ledgers it checks. `SECURITY DEFINER` functions owned by the table owner don't help, because `FORCE ROW LEVEL SECURITY` applies RLS to the owner too, so with no tenant context they return nothing. The rollup job and the outbox poller still have no role. Run these jobs per tenant as `app_rw` with tenant context set for each iteration, or create a dedicated aggregates role with `BYPASSRLS` that owns only reviewed functions. |
| NEW-05 | High | Partly | 44 connections against DigitalOcean's 47 fits for two web containers plus a deploy surge. But §13.2 and RSK-08 still autoscale to three instances, which adds 12 more (56). §4.2 itself says more than two instances needs transaction pooling, so cap autoscaling at two until then. With only 24 PHP workers in total, also load-test throughput; the capacity targets from v1.0 are no longer in the document. Confirm the AWS figure quoted in §4.2 against the instance actually chosen. |
| NEW-06 | High | Partly | The `Origin` allow-list now includes custom domains. Installer and code signing, auto-update, supported terminal operating systems and health monitoring are still not covered. |
| NEW-07 | High | Resolved | Unchanged. A 6-digit PIN is still worth considering. |
| NEW-08 | High | Partly | Unchanged: rounding mode, cash rounding, discounts on tax-inclusive lines, and "the fiscalized receipt is the document of record" are still not stated. |
| NEW-09 | Medium | Resolved | Unchanged. |
| NEW-10 | Medium | Partly | RSK-05 still lists two alternative stock policies. |
| NEW-11 | Medium | Partly | Regressed: the switch to a partial unique index breaks the batch sync statement (NEW-19). |
| NEW-12 | Medium | Resolved | The matrix now includes all roles. Define what happens offline when a discount above 15% needs the Tenant Owner. |
| NEW-13 | High | Resolved | Insert guard on headers, `journal_entry_id` can't change, `FOR SHARE` lock on the header, `TRUNCATE` revoked. |
| NEW-14 | High | Resolved | Direct ledger policy removed. The replacement aggregates need a working role (see NEW-04). |
| NEW-15 | High | Resolved | The lock is gone and a background worker builds the chain. For implementation: let the worker assign the chain order as it processes rows (UUIDv7 order doesn't match commit order, so late-committing rows would otherwise be skipped); run one worker per tenant; define a canonical serialization for hashing; and publish the `audit_logs` DDL with nullable hash columns and a guard that permits only the one-time hash update. |
| NEW-16 | Medium | Partly | Nullable terminal with a check constraint and a `customers` table. Invoice-line and payment tables are still missing, and the new index breaks sync (NEW-19). `customers.current_due` adds another stored receivables balance (see ACC-06). |
| NEW-17 | Medium | Partly | Tax codes with effective dates and supplementary duty, a variant → tax-code foreign key and company-scoped SKUs. But each rate period is its own row, so variants still pin an old rate (NEW-20). There's still no price-list table (despite the changelog), no barcode table, no unit-conversion table and no invoice lines to snapshot rates on. |
| NEW-18 | Medium | Partly | Both the terminal and SDC numbers are printed, and fraud controls were added (an alert after 10 consecutive un-fiscalized sales, a lock after 60 minutes). Still open: NBR confirmation that provisional receipts are acceptable; whether the browser or the bridge owns the pending queue; a `sales_invoices` column for the SDC number; and the "consecutive" rule, which can be dodged by alternating, so count un-fiscalized sales per shift instead. |

---

## 3. New issues in v1.3.0

### NEW-19 · Medium · The sync `ON CONFLICT` doesn't match the new partial index (§9.1, §10.2)
- **Issue:** The idempotency key is now a partial unique index (`... WHERE terminal_id IS NOT NULL`). PostgreSQL can only use a partial index as an `ON CONFLICT` target if the statement repeats the index's `WHERE` clause. §10.2 uses `ON CONFLICT (tenant_id, terminal_id, client_txn_id) DO NOTHING` without it, so every batch sync will fail with "there is no unique or exclusion constraint matching the ON CONFLICT specification".
- **Fix:** Simplest: go back to a plain unique constraint on `(tenant_id, terminal_id, client_txn_id)`. Back-office rows have nulls in both columns, and nulls never conflict in an ordinary unique constraint, so the partial index isn't needed. Otherwise, add `WHERE terminal_id IS NOT NULL` to the conflict target. Add an integration test that replays a batch.

### NEW-20 · Medium · Tax-code rows are per rate period, so variants still pin an old rate (§9.1)
- **Issue:** Each `tax_codes` row carries its own `effective_from` and `effective_to`, and variants reference a specific row. When a Finance Act changes a rate, the new rate is a new row with a new ID, and every variant still points to the old one, so the mass update the effective dating was meant to avoid is still needed. Nothing prevents overlapping periods for the same code either.
- **Fix:** Split the table into `tax_codes` (a stable identity that variants reference) and `tax_code_rates` (rate, supplementary duty and effective period). Prevent overlaps with an exclusion constraint on the date range (which needs the `btree_gist` extension), resolve the rate by invoice date, and snapshot the rate and amounts on each invoice line.

### NEW-21 · Medium · Reversals aren't constrained (§9.1, §9.2)
- **Issue:** Nothing stops an entry from being reversed twice, a draft from being "reversed", or a reversal from targeting another company's entry, since the foreign key only checks the tenant. Separately, posting still doesn't check that the period is open or that the posting date falls inside it (ACC-03).
- **Fix:** Add a unique index on `(tenant_id, reversed_entry_id)` where it isn't null. In the posting trigger, check that a reversal targets a posted entry in the same company and mirrors its amounts, and that the period is open and contains the posting date.

### NEW-22 · Medium · Company consistency isn't enforced inside a tenant (§9.1)
- **Issue:** Composite keys include `tenant_id` but not `company_id`. In a multi-company tenant (in MVP scope), a journal line can post to another company's account, a variant can use another company's tax code or belong to another company's product, and an invoice can reference another company's branch or customer. Each company has its own BIN and its own Mushak registers, so cross-company references put figures on the wrong VAT returns. `journal_lines` has no `company_id` at all.
- **Fix:** Add `UNIQUE (tenant_id, company_id, id)` to company-scoped tables and include `company_id` in their foreign keys (adding the column to `journal_lines`), or enforce it with check triggers. Cover it with tests.

---

## 4. Original 100 findings

### 4.1 Scorecard

| Status | Critical | High | Medium | Total | Change since v1.2.0 |
|---|---|---|---|---|---|
| Resolved | 5 | 11 | 14 | 30 | +4 |
| Partly resolved | 3 | 21 | 24 | 48 | −4 |
| Not addressed | 0 | 4 | 15 | 19 | 0 |
| Descoped or unclear | 0 | 0 | 3 | 3 | 0 |
| **Total** | **8** | **36** | **56** | **100** | |

### 4.2 Status changes since v1.2.0

| ID | Severity | Was | Now | Notes |
|---|---|---|---|---|
| ACC-01 | Critical | Partly | Resolved | Balance is checked when posting and at commit, and lines can no longer be moved or slipped into posted entries. |
| ACC-02 | Critical | Partly | Resolved | Insert, update, delete, line moves and `TRUNCATE` are all covered. |
| SEC-01 | High | Partly | Resolved | All personas are in the matrix, alongside the five SoD rules. Alerting the owner on privileged role changes is still worth adding. |
| SEC-02 | High | Partly | Resolved | The `platform_ro` ledger policy was removed, so consented elevation is again the only route to tenant data. |

### 4.3 Other open findings that v1.3.0 changed

| ID | Severity | Status | What changed and what's left |
|---|---|---|---|
| TEN-02 | Critical | Partly | Added `tax_codes` and `customers` with composite keys, plus foreign keys from periods to companies, invoices to customers and journal lines to tax codes. Still missing: suppliers, categories, invoice lines, payments, price lists, barcodes, unit conversions, stock locations, purchase orders and GRNs, cost layers, number series, and users, memberships and location scopes; foreign keys for `products.category_id` and `journal_lines.partner_id`; and company consistency (NEW-22). |
| POS-03 | Critical | Partly | Unchanged: no block table for offline number ranges, and NBR confirmation is still pending. |
| TAX-01 | Critical | Partly | Numbering decided and fraud controls added; the remaining items are in NEW-18, plus NBR's integration specification. |
| TAX-02 | High | Partly | Effective-dated tax codes and supplementary duty added, but modelled one row per period (NEW-20), and there are no invoice lines to hold per-line VAT. |
| TEN-03 | High | Partly | The `migrator` wording is fixed and `platform_ro` is limited to rollups, but cross-tenant jobs have no working role (NEW-04) and there's no CI check of policy coverage. |
| ACC-03 | High | Partly | Posting still doesn't check period status or dates (NEW-21). |
| OBS-01 | High | Partly | The watchdog can no longer read the tables it checks (NEW-04). Uptime checks, queue alerts, p95 dashboards and sync telemetry are still missing. |
| OPS-01 | High | Partly | $195 a month leaves $5 of headroom. Reverb hosting, a monitoring server, and code signing and update hosting for the bridge aren't costed, and the cost model still calls a read replica "high availability". |
| PLAN-01 | High | Not addressed | Now titled 27 weeks, but the dependencies add up to 30 (section 5). |
| PLAN-02 | High | Partly | The QA and security stream is back but still a block at the end (weeks 22–23), and the outbox still arrives in weeks 20–21. |
| ACC-06 | Medium | Not addressed | `customers.current_due` adds yet another place receivables are stored. |
| MOD-07 | Medium | Partly | The outbox poller still has no role (NEW-04) and is scheduled late. |
| SEC-03 | Medium | Partly | The lock problem is gone; the canonical serialization, `audit_logs` DDL and partitioning are still needed (see NEW-15). |
| SEC-10 | Medium | Partly | The OWASP table now matches the 2025 list. CSP, `frame-ancestors` and service-worker scope are still not covered. |
| TEN-07 | Medium | Unclear | The Super-Admin wireframe now shows a "Database Tier" of "Dedicated-01" for an Enterprise tenant, but the architecture describes only shared tenancy. Design the tier or remove it from the wireframe. |
| DATA-02 | Medium | Partly | See TEN-02. |
| UX-01 | Medium | Partly | The dashboard label is fixed; there are still no tenant admin configuration, onboarding or field-sales screens, and no design tokens. |

### 4.4 Full status list

**Resolved (30)**
- Critical: TEN-01, ACC-01, ACC-02, POS-01, POS-02
- High: TECH-01, MOD-01, MOD-05, MOD-06, INV-02, INV-05, POS-07, POS-08, SEC-01, SEC-02, DATA-07
- Medium: TECH-02, TECH-03, TECH-04, MOD-03, MOD-04, POS-05, POS-11, UX-02, QA-01, CORE-01, CORE-02, CORE-03, CORE-04, DATA-01

**Partly resolved (48)**
- Critical: TEN-02, POS-03, TAX-01
- High: TEN-03, TEN-04, TEN-05, TEN-09, TEN-10, MOD-02, ACC-03, ACC-04, ACC-05, INV-01, POS-04, TAX-02, TAX-03, TAX-04, TAX-05, TAX-07, SEC-09, OPS-01, OPS-02, OBS-01, PLAN-02
- Medium: MOD-07, INV-03, INV-04, INV-06, POS-06, SEC-03, SEC-04, SEC-05, SEC-06, SEC-07, SEC-08, SEC-10, DATA-02, DATA-05, DATA-06, DATA-08, API-01, API-02, OPS-03, OPS-05, QA-02, QA-03, PLAN-04, UX-01

**Not addressed (19)**
- High: TEN-06, POS-09, PLAN-01, PLAN-03
- Medium: TEN-08, TEN-11, MOD-09, ACC-06, ACC-07, POS-10, TAX-06, DATA-03, DATA-04, API-03, OPS-04, OPS-06, UX-03, UX-04, DOC-01

**Descoped or unclear (3)**
- Medium: MOD-08 and TAX-08 (descoped), TEN-07 (unclear)

---

## 5. Roadmap check

The roadmap is now titled 27 weeks, and the changelog says all seven milestones are "strictly aligned" with task end dates. Working through the Gantt dependencies from the 2 November 2026 start gives 30 weeks, because the VAT task grew to 4 weeks and the QA stream adds 2 more:

| Task | Weeks |
|---|---|
| Architecture signoff and CI/CD | 1–2 |
| Multi-tenancy, composite keys and RLS | 3–5 |
| IAM, global users and SoD | 6–8 |
| Super-Admin billing and onboarding wizard | 9–10 |
| GL, chart of accounts and periods | 6–9 |
| VAT engine and Mushak forms | 10–13 |
| AP/AR subledgers and bank reconciliation | 14–15 |
| Product master, units, MRP and price lists | 6–8 |
| Multi-warehouse, movements and costing | 9–12 |
| Stock counts, transfers and landed cost | 13–15 |
| Offline POS PWA shell | 9–12 |
| Print and fiscal bridge (Go) | 14–16 |
| POS sync engine and conflict queue | 17–19 |
| Data import tooling and outbox | 20–21 |
| QA, concurrency tests and security audits | 22–23 |
| Live pilot | 24–29 |
| Go-live | 30 |

| Milestone | Stated week | Actual week | Aligned? |
|---|---|---|---|
| M1: Tenancy, composite keys and RLS | 5 | 5 | Yes |
| M2: GL and posting contract | 9 | 9 | Yes |
| M3: VAT engine and EFD/SDC adapter | 13 | 13 for VAT; the fiscal adapter lives in the bridge, which finishes in week 16 | No |
| M4: POS PWA and hardware bridge | 16 | 16 | Yes |
| M5: End-to-end flow, data import and security audits | 20 | Sync 19, data import 21, audits 23 | No |
| M6: Pilot complete | 26 | 29 | No |
| M7: Go-live | 27 | 30 | No |

**One way to reach 27 weeks:** start the bridge after the POS shell instead of after the VAT engine (weeks 13–15), and run the QA and security stream alongside data import (weeks 19–20). The pilot then runs in weeks 21–26 and go-live is week 27. It's also worth running isolation and concurrency tests from the first sprint rather than only in a late block, and scheduling the outbox, notification gateway and settings engine early, since sales and posting emit events from week 6.

---

## 6. Documentation fixes

### 6.1 Status of earlier fixes

Of the 45 fixes requested so far, 39 are applied. Newly applied in v1.3.0: the ledger trigger set now matches ADR-003, all personas are in the matrix, the OWASP table uses the 2025 list, the LaTeX formatting is gone, the `migrator` wording is corrected, SKU scope is clarified, and the roadmap no longer marks future tasks as done.

| Fix | Status | What's left |
|---|---|---|
| "Organization" remnants | Partly | The dashboard is fixed; §7.3 still says "Tenant Organization". |
| Absolute claims | Partly | "Complete, Defect-Free" (§9.2), "Complete Resolution" (header), "eliminating application-level leakage risks" (§1.1) and "100% accurate" (§11.3) remain. |
| Roadmap weeks | Partly | Statuses are fixed, but the arithmetic and milestones aren't (section 5). |
| Changelog matches content | Partly | It claims aligned milestones and a price-list schema that aren't in the document. |
| Mermaid validation in CI | Not applied | Not mentioned. |
| "DB Cluster" / "Database Tier" in the Super-Admin wireframe | Not applied | See TEN-07. |

### 6.2 New documentation fixes in v1.3.0

| Location | Problem | Fix |
|---|---|---|
| §4.2 vs §13.2, RSK-08 | §4.2 says more than two web instances needs transaction pooling; the cost model and RSK-08 autoscale to three | Cap autoscaling at two, or plan transaction pooling now |
| §12.2 vs §9.1 | §12.2 says the SDC invoice number is recorded on the invoice, but `sales_invoices` has no column for it | Add the column |
| §4.2 | The AWS connection figure has no source | Quote the provider's documented limit for the instance chosen |

---

## 7. Functional gaps, ADRs and product-owner questions

**Functional gaps:** unchanged since v1.2.0: 4 covered in MVP, 4 scheduled for Phase 2, 7 partly addressed, 8 not addressed (cheque management, promotions, expenses and petty cash, approval engine, in-app help, custom fields, barcode and label generation, training mode and sandbox tenants).

**ADRs:** unchanged: 3 of the 17 recommended are recorded as ADRs (plus ADR-008 for the bridge), 10 are decided in the body text, and 4 are still undecided: hosting provider and region, tenant export and restore, search, and sync API versioning.

**Product-owner questions:** the document is now "Pending Final Product Owner Review", which is a good moment to answer the three questions still open (the first retail segment, POS-only or accounting-only plans, and how pilot tenants pay) and to chase the two legal decisions (in-country hosting and NBR acceptance of terminal series).

---

## 8. Recommended next steps

1. **Fix the sync statement** (NEW-19). It's a one-line change, but sync fails without it.
2. **Give cross-tenant jobs a working role:** the watchdog, rollups and outbox poller (NEW-04).
3. **Make the roadmap add up:** either accept 30 weeks or adopt a schedule like the one in section 5, and align every milestone (PLAN-01).
4. **Cap autoscaling at two instances** until transaction pooling is in place, and load-test throughput with 24 PHP workers (NEW-05).
5. **Finish the schema:** tax-rate periods (NEW-20), reversal and period rules (NEW-21), company-consistent keys (NEW-22), invoice lines, payments, price lists, barcodes and units (NEW-16, NEW-17), and the remaining tables and foreign keys (TEN-02).
6. **Get the NBR answers** on terminal series, SDC numbering and provisional receipts (POS-03, TAX-01, NEW-18).
7. **Then the High findings still not addressed:** tenant export and offboarding (TEN-06) and sync API versioning (POS-09).

---

## 9. Sources

- [DigitalOcean: PostgreSQL limits (backend connections by plan size)](https://docs.digitalocean.com/products/databases/postgresql/details/limits/)
- [OWASP Top 10:2025 introduction](https://top10.owasp.org/2025/0x00_2025-Introduction/)
- Earlier sources are listed in `ERP_DESIGN_REVIEW.md` v1.1, `ERP_DESIGN_REVIEW_v2.md` and `ERP_DESIGN_REVIEW_v1.2.0.md`.

Regulatory statements in this review are a starting point for the design team, not legal or tax advice. Confirm them with a Bangladesh VAT consultant and a lawyer before relying on them.
