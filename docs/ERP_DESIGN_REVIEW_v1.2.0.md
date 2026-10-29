# ERP System Design v1.2.0: Review

| | |
|---|---|
| **Document reviewed** | `ERP_DESIGN_REVIEW_v3.md`, which contains design v1.2.0 ("Engineering Architecture Specification") despite its file name |
| **Checked against** | `ERP_DESIGN_REVIEW_v2.md` (review of design v1.1.0, issues NEW-01 to NEW-12) and `ERP_DESIGN_REVIEW.md` v1.1 (100 original findings) |
| **Review date** | 26 October 2026 |
| **Verdict** | Good progress on the issues raised last time: the balance-check bypass, PIN storage, date handling and batch sync are fixed, and the bridge now has a security design. **Still not build-ready.** Three new High issues sit in the ledger, platform access and audit logging, and the roadmap still runs to 27 weeks although the changelog says it was corrected to 24. |

---

## Contents

1. [Summary](#1-summary)
2. [Status of the 12 issues raised in the v1.1.0 review](#2-status-of-the-12-issues-raised-in-the-v110-review)
3. [New issues in v1.2.0](#3-new-issues-in-v120)
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
| 12 issues from the v1.1.0 review | 4 resolved, 8 partly resolved |
| 100 original findings | 26 resolved, 52 partly, 19 not addressed, 3 descoped or unclear (4 improved since v1.1.0, 1 regressed) |
| New issues in v1.2.0 | 6 (3 High, 3 Medium) |
| Documentation fixes | 32 of 41 applied, 5 partly, 4 not applied; 4 new ones needed |

### What improved

- **Ledger triggers rewritten:** separate header and line functions, `DELETE` returns `OLD`, inserts into posted entries are blocked, and posting now checks line count and balance on the header (NEW-03 fixed).
- **Schema:** branches, warehouses, terminals, products and variants now have DDL with composite tenant keys, and stock, invoice, chart-of-accounts and reversal references gained foreign keys. IDs are generated as UUIDv7 in the application, and the incorrect PostgreSQL 17 claim is gone.
- **POS:** a separate 4-digit POS PIN with a device-bound encrypted verifier and lockouts; the `persist()` result is checked; batch sync uses `ON CONFLICT DO NOTHING` with per-item results.
- **Bridge:** a Go daemon with `Origin` checks, a pairing token, Chrome Local Network Access policy, its own roadmap task and ADR-008.
- **Tax and dates:** the tax-inclusive formula is written out, the wireframe VAT is now correct (৳30.29), and `invoice_date` explicitly drives VAT while `business_date` covers shifts.
- **Roles:** Branch Manager and Shift Supervisor roles with one set of discount ceilings (5%, 10%, 15%, owner above that).
- **Operations:** a connection budget, a cost model covering all three environments, and reporting-role RLS policies.

### What still blocks a build

1. A journal line can still be moved out of a posted entry, and a line inserted concurrently with posting can slip in (NEW-13).
2. `platform_ro` can read every tenant's journal entries with no consent step (NEW-14).
3. The tenant-wide audit lock is held until each transaction commits, so all checkouts in a tenant queue behind one another (NEW-15).
4. The connection budget assumes a 100-connection limit; DigitalOcean's 2 GiB plan provides 47, and the budget ignores the third autoscaled instance and deploy overlap (NEW-05).
5. NBR hasn't confirmed terminal invoice series or provisional receipts, and the design now has two competing invoice numbers (POS-03, NEW-18).
6. The roadmap still runs to 27 weeks, milestones now fall before the tasks they depend on, and the QA and security workstream was removed (PLAN-01, PLAN-02).

---

## 2. Status of the 12 issues raised in the v1.1.0 review

| ID | Severity | Status | Notes |
|---|---|---|---|
| NEW-01 | High | Partly | Status is now "Draft ... Under Architectural Review". But the changelog still claims fixes the document doesn't contain (the roadmap "corrected to exactly 24 weeks", composite keys "across all tables", API-03 resolved), and §9.2 is titled "Complete, Defect-Free" although NEW-13 remains. |
| NEW-02 | Critical | Partly | Separate functions, `DELETE` returns `OLD`, line inserts into posted entries blocked, column error fixed. Two holes remain, plus `TRUNCATE` isn't revoked (NEW-13). |
| NEW-03 | Critical | Resolved | The header trigger checks line count and balance when `is_posted` changes to true. |
| NEW-04 | High | Partly | `reporting_ro` shares the tenant policy and the watchdog runs as `platform_ro`. The new `platform_ro` policy is far too broad (NEW-14), and the outbox poller still has no role. |
| NEW-05 | High | Partly | A budget now exists (75 connections), but it assumes a 100-connection limit that depends on the provider (DigitalOcean's 2 GiB plan has 47), counts two web containers while autoscaling allows three (another 25), and ignores the old and new containers running together during zero-downtime deploys. Pick the provider, use its real limit, and size for peak instances plus deploy overlap, or cap server connections in PgBouncer and accept queueing. |
| NEW-06 | High | Partly | `Origin` check, pairing token, Local Network Access policy, Go daemon, roadmap task and ADR-008. Not covered: installer and code signing, auto-update, supported terminal operating systems, health monitoring, and Go skills on a Laravel team. The `Origin` allow-list must include tenants' custom domains, not only subdomains. |
| NEW-07 | High | Resolved | POS-only PIN, PBKDF2 verifier encrypted with a non-extractable device key, local lockouts, no account password hashes on terminals. Consider a 6-digit PIN and apply the same lockout to supervisor-override PIN entry. |
| NEW-08 | High | Partly | Formula, corrected wireframe and shared golden vectors (RSK-12). Still not stated: rounding mode, cash rounding, how discounts apply to tax-inclusive lines, and that the fiscalized receipt is the document of record. |
| NEW-09 | Medium | Resolved | `invoice_date` drives VAT periods and registers; `business_date` is for shifts. |
| NEW-10 | Medium | Partly | OQ-02 now says online checkout rejects insufficient stock, but RSK-05 still lists two alternative policies. Also reconsider the rule itself: refusing to sell an item the customer is holding is usually worse for a retailer than recording negative stock, so a tenant setting may fit better. |
| NEW-11 | Medium | Resolved | Savepoint per invoice, `ON CONFLICT DO NOTHING`, per-item results, and both key columns `NOT NULL`. That last change creates a new problem (NEW-16). |
| NEW-12 | Medium | Partly | Branch Manager and Shift Supervisor roles and one discount table. The matrix still has no Sales Manager or Procurement Manager columns, and "over 15% requires Tenant Owner authorization" has no offline path. |

---

## 3. New issues in v1.2.0

### NEW-13 · High · The journal-line trigger still has two holes (§9.2)
- **Issue:**
  - On `UPDATE`, the line trigger checks only the entry in `NEW.journal_entry_id`. Changing a line's `journal_entry_id` from a posted entry to a draft passes, which removes the line from the posted entry. The deferred balance check also looks only at the new entry, so the posted entry becomes unbalanced without an error.
  - The line trigger reads the header without locking it. If one transaction posts an entry while another inserts lines into it, the inserter still sees the entry as a draft; a balanced pair of lines then passes the deferred check and lands in a posted entry.
  - Smaller gaps: nothing stops a header from being inserted already posted (the header trigger covers only `UPDATE` and `DELETE`), and `TRUNCATE` isn't revoked from `app_rw`.
- **Fix:** Reject any change to `journal_entry_id` (or check both the old and new entries). Lock the header in the line trigger with `SELECT ... FOR SHARE`, which conflicts with the posting `UPDATE` and serializes the two. Add a `BEFORE INSERT` trigger on `journal_entries` requiring `is_posted = FALSE`, revoke `TRUNCATE`, and add tests for each case.

### NEW-14 · High · `platform_ro` can read every tenant's ledger (§6.1)
- **Issue:** The policy `platform_read_journal_entries ... USING (TRUE)` gives the platform role unrestricted read access to every tenant's journal entries. That bypasses the consent-based, time-limited support access in SoD-05 (SEC-02) and the PDPO's data-minimization principle. Telemetry and billing need aggregates, not ledger rows.
- **Fix:** Remove direct policies for `platform_ro` on tenant financial tables. Expose aggregates through `SECURITY DEFINER` functions or rollup tables such as `tenant_usage_rollups`, keep row-level access only through the consented elevation path, and log platform queries at the database level.

### NEW-15 · High · The audit lock serializes every checkout in a tenant (§12.4, §13.1)
- **Issue:** `pg_advisory_xact_lock(...)` is held until the transaction ends. Audit rows are written inside business transactions such as checkout and posting, so every audited transaction in a tenant waits for the previous one to commit. All cashiers across a retail chain would queue behind one lock, which undoes the lock ordering in §13.1 and puts the 500 ms checkout and 10 checkouts-per-second targets out of reach.
- **Fix:** Insert audit rows inside the transaction without the hash, and have one background worker per tenant build the chain over committed rows in a stable order (for example, a per-tenant sequence, tolerating gaps from rollbacks). Alternatively, keep a separate chain per terminal or stream. Anchor daily digests as now.

### NEW-16 · Medium · `sales_invoices` now requires a POS terminal (§9.1, §8.2 #4)
- **Issue:** Making `terminal_id` and `client_txn_id` `NOT NULL` fixed NEW-11 for POS sales, but wholesale and back-office invoices from the Sales module (quotation → order → invoice) have no terminal and can't be stored. There's also still no invoice-line table, payments table or foreign key for `customer_id`.
- **Fix:** Either keep POS receipts and sales invoices in separate tables, or allow a null terminal with `CHECK ((terminal_id IS NULL) = (client_txn_id IS NULL))` and keep the unique key for POS rows. Add invoice-line, payment and customer tables with composite foreign keys.

### NEW-17 · Medium · Tax and prices are flat columns on product variants (§9.1)
- **Issue:** `vat_rate_percent` (default 15%), `is_tax_inclusive`, `mrp_price` and `wholesale_price` live on each variant. That conflicts with effective-dated tax codes (TAX-02) and with A-05, which puts tax treatment on price lists. A Finance Act rate change would mean mass-updating variants and losing the rate history needed to audit or reprint old invoices; the 15% default silently taxes exempt items created without a rate; and supplementary duty has nowhere to go. `journal_lines.tax_code_id` points to a tax-code table that doesn't exist. A single `barcode` column can't hold separate carton and piece barcodes, and `base_uom` is free text with no conversion table, although §8.2 promises unit-of-measure conversions.
- **Fix:** Add a `tax_codes` table with effective-dated rates (including supplementary duty) referenced by variants, a price-list table carrying the tax-inclusive flag, a barcode table per variant and unit, and a unit-conversion table. Snapshot the rate and amounts on each invoice line.

### NEW-18 · Medium · The fiscal fallback needs legal confirmation and fraud controls (§12.2)
- **Issue:** If the SDC doesn't answer within 2 seconds, the sale completes with a "provisional tax invoice". The design doesn't establish that NBR accepts provisional receipts, the customer never receives the fiscalized version, and unplugging the SDC becomes an easy way to skip fiscalization. The pending queue is in browser IndexedDB, yet §12.2 says the bridge replays it, and the bridge can't read browser storage. §12.2 also introduces an "official SDC invoice number" alongside the terminal series from OQ-03, without saying which one is the Mushak 6.3 number. The cited "General Order No. 17/Mushak/2019" has no source; this review couldn't verify it.
- **Fix:** Confirm the fallback and the numbering with NBR or a VAT consultant. Log every fallback with its duration, alert the branch manager and owner past a threshold, and block further sales after a set number of minutes or receipts. Decide whether the browser or the bridge owns the queue, and cite the order.

---

## 4. Original 100 findings

### 4.1 Scorecard

| Status | Critical | High | Medium | Total | Change since v1.1.0 |
|---|---|---|---|---|---|
| Resolved | 3 | 9 | 14 | 26 | +2 |
| Partly resolved | 5 | 23 | 24 | 52 | 0 |
| Not addressed | 0 | 4 | 15 | 19 | −2 |
| Descoped or unclear | 0 | 0 | 3 | 3 | 0 |
| **Total** | **8** | **36** | **56** | **100** | |

Most of the work in v1.2.0 went into the 12 issues from the last review, so the original findings moved only slightly.

### 4.2 Status changes since v1.1.0

| ID | Severity | Was | Now | Notes |
|---|---|---|---|---|
| TECH-03 | Medium | Partly | Resolved | PostgreSQL 17 with UUIDv7 generated in the application; the incorrect `uuidv7()` claim is gone. |
| DATA-01 | Medium | Not addressed | Resolved | No random-UUID defaults; IDs come from Laravel `HasUuids`. Raw SQL inserts and seeders must now supply IDs. |
| POS-08 | High | Partly | Resolved | The `persist()` result is checked and quota is monitored. A printed or exported journal for reconstruction is still worth adding. |
| UX-01 | Medium | Not addressed | Partly | A Super-Admin wireframe was added. Still no tenant admin configuration, onboarding or field-sales screens, and no design tokens. |
| SEC-02 | High | Resolved | Partly | Regressed: the `platform_ro` policy gives platform staff ledger access without the consent step (NEW-14). |

### 4.3 Other open findings that v1.2.0 changed

| ID | Severity | Status | What changed and what's left |
|---|---|---|---|
| TEN-02 | Critical | Partly | Much improved: DDL with composite keys for branches, warehouses, terminals, products and variants, and foreign keys on stock, invoices, chart-of-accounts parent, reversals and journal-line branch. Still missing: tables for customers, suppliers, categories, tax codes, invoice lines, payments, stock locations, users, memberships and location scopes, and foreign keys for `products.category_id`, `journal_lines.partner_id` and `tax_code_id`, `sales_invoices.customer_id` and `accounting_periods.company_id`. |
| ACC-01 | Critical | Partly | Posting now checks the header (NEW-03 fixed), but moving a line out can still unbalance a posted entry (NEW-13). |
| ACC-02 | Critical | Partly | Mostly fixed; the remaining holes are in NEW-13. |
| POS-03 | Critical | Partly | Terminals have `allocated_series_prefix` and `last_issued_sequence`, but there's no block table (range and fiscal year) for offline allocation, NBR confirmation is still pending, and the SDC number in §12.2 competes with the terminal number (NEW-18). |
| TAX-01 | Critical | Partly | A fallback is defined but needs legal and fraud controls (NEW-18). NBR's integration specification and certification requirements still aren't covered. |
| TEN-03 | High | Partly | Policies now cover `reporting_ro`; the `platform_ro` policy is too broad (NEW-14); the outbox poller has no role; no CI coverage check; the `migrator` "zero data access" claim is unchanged. |
| TAX-02 | High | Partly | VAT rates now sit on each variant instead of in tax codes (NEW-17); supplementary duty is still missing. |
| TAX-03 | High | Partly | Phasing is now consistent (Mushak 6.5 and full 9.1 in Phase 2). But Milestone 5 requires pilot outlets to close a VAT month, which needs return data, and a multi-outlet pilot will move stock between branches, which needs 6.5. Bring both into MVP or plan manual workarounds for the pilot. |
| TAX-07 | High | Partly | Formula and corrected wireframe. Rounding mode, discounts on tax-inclusive lines, an MRP ceiling check and tariff values are still missing. |
| SEC-01 | High | Partly | Branch Manager and Shift Supervisor added; Sales Manager and Procurement Manager columns still missing. |
| INV-01 | High | Partly | Foreign keys to warehouse and variant added. No cost-layer table, no protection against updating or deleting movements, and no reconciliation to the GL inventory account. |
| OPS-01 | High | Partly | All three environments are now costed, at exactly the $200 cap with no headroom. Reverb hosting, a monitoring server, and code signing and update hosting for the bridge aren't costed, and "full high availability" is still claimed for a read replica. |
| OBS-01 | High | Partly | The watchdog now has a role (`platform_ro`). Uptime checks, queue alerts, p95 dashboards and per-terminal sync telemetry are still missing. |
| PLAN-01 | High | Not addressed | The changelog says the chart was corrected to 24 weeks; it still runs to 27, and milestones now fall before their tasks finish (section 5). |
| PLAN-02 | High | Partly | Regressed within the status: the QA, security and concurrency workstream was removed from the roadmap. |
| MOD-07 | Medium | Partly | The outbox now uses `ON DELETE RESTRICT`. The poller still has no role, and the outbox is scheduled for weeks 19–20, after every module that emits events. |
| SEC-03 | Medium | Partly | The advisory lock prevents forks but serializes checkouts (NEW-15); canonical JSON hashing is still undefined. |
| SEC-05 | Medium | Partly | IPv6, `0.0.0.0/8` and `100.64.0.0/10` added. DNS-rebinding protection (connecting to the validated IP), dual-secret rotation and inbound webhook verification are still missing. |
| SEC-10 | Medium | Partly | The table is now titled OWASP Top 10:2025 but mixes the 2021 and 2025 lists (section 6.2). |
| DATA-02 | Medium | Partly | Five more tables defined; see TEN-02 for what's missing. |
| API-03 | Medium | Not addressed | Listed as resolved again, but keys still aren't tied to a request hash. |

### 4.4 Full status list

**Resolved (26)**
- Critical: TEN-01, POS-01, POS-02
- High: TECH-01, MOD-01, MOD-05, MOD-06, INV-02, INV-05, POS-07, POS-08, DATA-07
- Medium: TECH-02, TECH-03, TECH-04, MOD-03, MOD-04, POS-05, POS-11, UX-02, QA-01, CORE-01, CORE-02, CORE-03, CORE-04, DATA-01

**Partly resolved (52)**
- Critical: TEN-02, ACC-01, ACC-02, POS-03, TAX-01
- High: TEN-03, TEN-04, TEN-05, TEN-09, TEN-10, MOD-02, ACC-03, ACC-04, ACC-05, INV-01, POS-04, TAX-02, TAX-03, TAX-04, TAX-05, TAX-07, SEC-01, SEC-02, SEC-09, OPS-01, OPS-02, OBS-01, PLAN-02
- Medium: MOD-07, INV-03, INV-04, INV-06, POS-06, SEC-03, SEC-04, SEC-05, SEC-06, SEC-07, SEC-08, SEC-10, DATA-02, DATA-05, DATA-06, DATA-08, API-01, API-02, OPS-03, OPS-05, QA-02, QA-03, PLAN-04, UX-01

**Not addressed (19)**
- High: TEN-06, POS-09, PLAN-01, PLAN-03
- Medium: TEN-08, TEN-11, MOD-09, ACC-06, ACC-07, POS-10, TAX-06, DATA-03, DATA-04, API-03, OPS-04, OPS-06, UX-03, UX-04, DOC-01

**Descoped or unclear (3)**
- Medium: MOD-08 and TAX-08 (descoped), TEN-07 (unclear; the new Super-Admin wireframe shows a "DB Cluster" column, which suggests a dedicated-database tier still exists)

---

## 5. Roadmap check

The changelog says the Gantt chart was "corrected to exactly 24 weeks". Working through its dependencies from the 2 November 2026 start gives the same 27 weeks as v1.1.0:

| Task | Weeks |
|---|---|
| Architecture signoff and CI/CD (marked done) | 1–2 |
| Multi-tenancy, composite keys and RLS (marked active) | 3–5 |
| IAM, global users and SoD | 6–8 |
| Super-Admin billing and onboarding wizard | 9–10 |
| GL, chart of accounts and periods | 6–9 |
| VAT engine and Mushak forms | 10–12 |
| AP/AR subledgers and bank reconciliation | 13–14 |
| Product master, units, MRP and price lists | 6–8 |
| Multi-warehouse, movements and costing | 9–12 |
| Stock counts, transfers and landed cost | 13–15 |
| Offline POS PWA shell | 9–12 |
| Print and fiscal bridge (Go) | 13–15 |
| POS sync engine and conflict queue | 16–18 |
| Data import tooling and outbox | 19–20 |
| Live pilot | 21–26 |
| Go-live | 27 |

**Milestones that fall before the work they depend on:**
- Milestone 2 (week 10, "GL and VAT engine operational"): the VAT engine finishes in week 12.
- Milestone 3 (week 14, "hardware bridge operational"): the bridge finishes in week 15.
- Milestone 4 (week 17, "end-to-end flow complete"): the sync engine finishes in week 18.
- Milestone 5 (week 24, "pilot complete"): the pilot runs until week 26.

**Other roadmap problems:**
- The first two tasks are still marked done and active, although work starts on 2 November.
- The QA, security and concurrency stream from v1.1.0 is gone; nothing in the plan covers RLS audits or concurrency testing.
- The outbox arrives in weeks 19–20, but sales, posting and notifications emit events from week 6 onwards. The notification gateway and settings engine no longer have tasks at all.
- Billing and onboarding share 2 weeks, data import gets 2 weeks right before the pilot, and Reverb and the Reporting module still have no tasks.

---

## 6. Documentation fixes

### 6.1 Status of earlier fixes

Of the 41 fixes requested so far (29 in review v1.1 and 12 in the v1.1.0 review), 32 are applied. The newly applied ones include the corrected wireframe VAT and fictional brand names, removal of the "5x faster" claim, the export-link wording, the PostgreSQL 17 `uuidv7()` claim, the unused `uuid-ossp` extension, scaling figures aligned between RSK-08 and the cost model, consistent Mushak phasing, the Redis arrow, the 40P01 retry note, the "Providend" typo, and the status line and open-question owners.

| Fix | Status | What's left |
|---|---|---|
| "Organization" remnants | Partly | The dashboard wireframe and §7.3 still refer to an "Organization". |
| ADR-003 vs. schema | Partly | See NEW-13. |
| Absolute claims | Partly | "Mathematically" is gone, but "Complete, Defect-Free" (§9.2), "eliminating application-level leakage risks" (§1.1), "zero event loss" (§5.5) and "100% accurate" (§11.3) remain. |
| Personas missing from the matrix | Partly | Sales Manager and Procurement Manager columns are still missing. |
| OWASP edition | Partly | See section 6.2. |
| LaTeX and escaped `\$` | Not applied | Still present, and v1.2.0 adds more (`$$` formulas in §11.4, an inline sum in §4.2). |
| Roadmap weeks and statuses | Not applied | See section 5. |
| Mermaid validation in CI | Not applied | Not mentioned. |
| `migrator` data-access claim (§6.1) | Not applied | A table owner always has data access; say its credentials are never used at runtime. |

### 6.2 New documentation fixes in v1.2.0

| Location | Problem | Fix |
|---|---|---|
| §12.1 | Titled OWASP Top 10:2025 but numbered from the 2021 list: it keeps "Vulnerable & Outdated Components" and omits "Software or Data Integrity Failures". The 2025 order is A01 Broken Access Control, A02 Security Misconfiguration, A03 Software Supply Chain Failures, A04 Cryptographic Failures, A05 Injection, A06 Insecure Design, A07 Authentication Failures, A08 Software or Data Integrity Failures, A09 Security Logging & Alerting Failures, A10 Mishandling of Exceptional Conditions | Renumber against the 2025 list |
| Header, §20 changelog | Claims fixes that aren't in the document (24 weeks, "composite foreign keys across all tables", API-03) | Make the changelog match the content (NEW-01) |
| §11.4 Screen 3 | A "DB Cluster" column implies dedicated tenant databases | State whether the dedicated tier exists (TEN-07) |
| §9.1 `product_variants` | SKUs are unique per tenant, but products belong to a company, so companies in one tenant can't reuse SKUs | Confirm the intended scope |

---

## 7. Functional gaps, ADRs and product-owner questions

**Functional gaps (23 from review v1.1):** subscription billing, the onboarding wizard and data import now have roadmap tasks but no design, so they count as partly addressed. Totals: 4 covered in MVP, 4 scheduled for Phase 2, 7 partly addressed and 8 not addressed (cheque management, promotions, expenses and petty cash, approval engine, in-app help, custom fields, barcode and label generation, training mode and sandbox tenants).

**ADRs:** ADR-008 now records the bridge design. Of the 17 ADRs recommended in review v1.1, 3 are recorded as ADRs, 10 are decided in the body text (now including UUIDv7), and 4 are still undecided: hosting provider and region, tenant export and restore, search, and sync API versioning.

**Product-owner questions:** unchanged. The first retail segment, POS-only or accounting-only plans, and how pilot tenants pay are still unanswered; in-country hosting and NBR acceptance of terminal series are still pending legal decisions.

---

## 8. Recommended next steps

1. **Close the remaining ledger holes** (NEW-13) and **replace the tenant-wide audit lock** with background chaining (NEW-15). Both sit on the checkout path.
2. **Remove `platform_ro`'s direct ledger access** in favour of aggregates (NEW-14), and give the outbox poller its own role.
3. **Re-plan honestly:** accept 27 weeks or cut scope, restore the QA and security stream, move the outbox, notifications and settings earlier, align milestones with tasks, and decide how pilots handle Mushak 9.1 and 6.5 (PLAN-01, PLAN-02, PLAN-03, TAX-03).
4. **Choose the hosting provider** and redo the connection budget with its real limit, peak instance count and deploy overlap, and leave headroom in the cost model (NEW-05, OPS-01).
5. **Get the legal confirmations** on terminal series, SDC numbering and provisional receipts (POS-03, TAX-01, NEW-18).
6. **Finish the schema:** tax codes, price lists, barcodes and unit conversions (NEW-17); invoice lines, customers, payments and back-office invoices (NEW-16); and the remaining foreign keys (TEN-02).
7. **Then the High findings still not addressed:** tenant export and offboarding (TEN-06) and sync API versioning (POS-09).

---

## 9. Sources

- [DigitalOcean: PostgreSQL limits (backend connections by plan size)](https://docs.digitalocean.com/products/databases/postgresql/details/limits/)
- [OWASP Top 10:2025 introduction](https://top10.owasp.org/2025/0x00_2025-Introduction/)
- [The Daily Star: Businesses have to use electronic fiscal devices from Nov 1 (2018)](https://www.thedailystar.net/news/business/businesses-have-use-electronic-fiscal-devices-nov-1-1620505), which describes the NBR order without giving its number
- Earlier sources are listed in `ERP_DESIGN_REVIEW.md` v1.1 and `ERP_DESIGN_REVIEW_v2.md`.

Regulatory statements in this review are a starting point for the design team, not legal or tax advice. Confirm them with a Bangladesh VAT consultant and a lawyer before relying on them.
