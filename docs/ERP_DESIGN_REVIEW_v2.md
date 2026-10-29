# ERP System Design v1.1.0: Remediation Review

| | |
|---|---|
| **Document reviewed** | `ERP_SYSTEM_DESIGN-v2.md` (design v1.1.0, "Build-Ready Architectural Remediation") |
| **Checked against** | `ERP_DESIGN_REVIEW.md` v1.1 (100 findings, 29 documentation fixes, 23 functional gaps) |
| **Review date** | 26 October 2026 |
| **Verdict** | Substantial progress, especially on the POS, tenancy and accounting design. **Still not build-ready**, and the header's claim that every finding has been remediated isn't accurate. |

---

## Contents

1. [Summary](#1-summary)
2. [New issues introduced in v1.1.0](#2-new-issues-introduced-in-v110)
3. [Status of the 100 findings from review v1.1](#3-status-of-the-100-findings-from-review-v11)
4. [Documentation fixes](#4-documentation-fixes)
5. [Functional gaps](#5-functional-gaps)
6. [ADRs](#6-adrs)
7. [Product-owner questions](#7-product-owner-questions)
8. [Recommended next steps](#8-recommended-next-steps)
9. [Sources](#9-sources)

---

## 1. Summary

### Scorecard

| Status | Critical | High | Medium | Total |
|---|---|---|---|---|
| Resolved | 3 | 9 | 12 | 24 |
| Partly resolved | 5 | 23 | 24 | 52 |
| Not addressed | 0 | 4 | 17 | 21 |
| Descoped or unclear | 0 | 0 | 3 | 3 |
| **Total** | **8** | **36** | **56** | **100** |

v1.1.0 also introduces **12 new issues** (2 Critical, 6 High, 4 Medium), described in [section 2](#2-new-issues-introduced-in-v110). Of the 29 documentation fixes, 20 were applied, 4 partly and 5 not at all, and the new version needs 12 more.

The header says the document is "Approved", "Build-Ready" and that all findings are remediated. Only 3 of the 8 Critical findings are fully resolved, and the two most important new fixes (the ledger triggers) contain defects that reopen Critical risks.

### What improved

- **Accounting is the foundation:** operational modules depend on its posting contract, and journals post synchronously in the same transaction.
- **Tenancy hardening:** composite tenant foreign keys on the core financial tables, fail-closed RLS, defined database roles, and session-level tenant context with teardown.
- **POS rebuilt** as a client-side Alpine.js and Dexie app, with an offline policy matrix, offline PIN sign-in, persistent storage, kiosk mode and database-enforced idempotency.
- **Compliance moved forward:** the EFD/SDC adapter is in MVP scope, the VAT schedule includes 10% and turnover tax, and the design adds MRP, tax-inclusive price lists and a PDPO section.
- **Inventory and concurrency:** a stock movement ledger, `NULLS NOT DISTINCT`, sorted lock ordering at checkout, and a genuinely parallel concurrency test.
- **Platform:** Laravel 13, Filament v5 and PHP 8.4; notification, export, settings and real-time services; a full OWASP matrix; a costed bill of materials; and a 6-week pilot scheduled after the accounting work.

### What still blocks a build

1. The immutability triggers don't work, and posted entries can still gain new lines (NEW-02, Critical).
2. The balance check can be bypassed by posting a draft in a separate transaction (NEW-03, Critical).
3. Composite foreign keys are missing on most operational tables, including `stock_movements`, `stock_balances` and `sales_invoices` (TEN-02).
4. RLS policies cover only the `app_rw` role, so reporting, Super-Admin and background jobs either see nothing or would need `BYPASSRLS` (NEW-04).
5. The fiscal device flow has no failure or offline behaviour, and NBR hasn't confirmed per-terminal invoice series (TAX-01, POS-03).
6. The local print and fiscal bridge has no security, packaging or Chrome permission design (NEW-06).
7. Database connections will run out under peak load (NEW-05), and the budget covers production only (OPS-01).
8. The roadmap runs to 27 weeks, not 24, and scope grew without cut lines (PLAN-01, PLAN-03).

---

## 2. New issues introduced in v1.1.0

### NEW-01 · High · The status line overstates the remediation (Header, §2.3, §20 changelog)
- **Issue:** The header says the document is "Approved" and "Build-Ready" and that all 100 findings and 29 documentation fixes are remediated. This check finds 24 findings resolved, 52 partly resolved and 21 not addressed, and two of the new fixes contain defects (NEW-02, NEW-03). Three open questions still await decisions in weeks 2–4, and two of them depend on legal advice. A team reading the header would start building on assurances the document doesn't yet support.
- **Fix:** Set the status to "Draft for review", replace the blanket claim with a per-finding status table (section 3 of this review can seed it), and name the approvers.

### NEW-02 · Critical · The immutability triggers don't work (§9.3)
- **Issue:** The triggers have three defects:
  - The same function is attached to `journal_lines`, but it reads `OLD.is_posted`, a column `journal_lines` doesn't have. Every update or delete of a journal line will raise an error, including edits to draft lines; posted lines are protected only by accident.
  - On `DELETE`, `NEW` is null, so the function returns null. In a `BEFORE` row trigger that silently skips the row: deleting a draft journal entry does nothing and reports no error.
  - Neither trigger covers `INSERT`, so new lines can still be added to a posted entry. A balanced pair passes the balance trigger and changes posted figures.
- **Fix:** Use separate functions. On `journal_entries`, branch on `TG_OP`, return `OLD` for deletes, and block any change once `OLD.is_posted` is true. On `journal_lines`, look up the parent entry's `is_posted` for `INSERT`, `UPDATE` and `DELETE`, and block all three when it's posted. Revoke `TRUNCATE` from `app_rw`. Add a test for each case.

### NEW-03 · Critical · The balance check can be bypassed (§9.2)
- **Issue:** The deferred trigger fires only when `journal_lines` rows are inserted or updated. A draft whose lines were saved in an earlier transaction can be posted later by updating only the header (`is_posted = TRUE`). No line trigger fires, so an unbalanced entry posts. Entries with no lines, or a single line, also pass.
- **Fix:** Add a deferred constraint trigger on `journal_entries` that runs when `is_posted` becomes true and checks that the entry has at least two lines and balances in base currency. Keep the line-level trigger for edits made inside the posting transaction.

### NEW-04 · High · RLS policies only cover `app_rw` (§6.1, §6.3, §5.1, §14)
- **Issue:** The sample policy is `TO app_rw`. When RLS is forced and no policy applies to a role, that role sees no rows. `reporting_ro` (replica exports and the Reporting module) and `platform_ro` (Super-Admin and metering) will therefore get empty results. The obvious shortcut, granting them `BYPASSRLS`, would remove tenant isolation from tenant-facing exports. The outbox poller and the hourly integrity watchdog also work across tenants, and neither has a role assigned.
- **Fix:** Apply the tenant policies to `reporting_ro` too and set tenant context on replica connections. Give `platform_ro` explicit read-only policies on the specific tables it needs, on a separate audited connection. Assign roles to the outbox poller and the watchdog jobs, and extend the CI check to verify policy coverage for every role.

### NEW-05 · High · Database connections will run out before CPU does (§4.2, §13.2, RSK-08)
- **Issue:** PHP-FPM holds one PostgreSQL connection per busy worker, and PgBouncer in session mode doesn't multiplex, so open connections equal concurrent PHP workers. The web tier scales to 3 instances in the bill of materials and 5 in RSK-08, alongside Horizon workers, Reverb and replica traffic. A managed PostgreSQL plan with 1 vCPU and 2 GB RAM typically allows only a few dozen connections, so an Eid peak would exhaust connections first. RSK-08's "Redis connection pooling" doesn't help, because the limit is on PostgreSQL.
- **Fix:** Write a connection budget (instances × `pm.max_children` + workers + Reverb + admin tools) against the plan's connection limit, cap `pm.max_children` to fit, size the database plan accordingly, and load-test the Eid scenario. If the budget doesn't fit, switch to transaction pooling with `set_config(..., true)` at the start of every transaction, the alternative described in TEN-01.

### NEW-06 · High · The local print and fiscal bridge needs its own design (§11.3, §8.2 #3, ADR-006)
- **Issue:**
  - WebSockets aren't covered by the browser's same-origin policy, so any website open on the terminal could connect to `ws://127.0.0.1:9191` and print receipts or open the cash drawer, unless the bridge checks the `Origin` header and requires a pairing token.
  - Chrome's Local Network Access rules now apply to WebSocket connections from public HTTPS sites to localhost (since Chrome 147), so the POS will trigger a permission prompt unless the origin is pre-approved through managed-browser policy (`LocalNetworkAccessAllowedForUrls`).
  - The bridge is a native program. It needs a language, an installer, code signing, auto-update, monitoring and support for every terminal operating system, and none of that is in the team plan, roadmap or bill of materials. The design also doesn't say whether the EFD/SDC "local HTTP/serial bridge" is the same program.
- **Fix:** Write an ADR for the bridge covering the origin allow-list and pairing token, managed Chrome policy for Local Network Access, packaging and updates, supported operating systems, and whether it also hosts the fiscal adapter. Add it to the roadmap and give it an owner.

### NEW-07 · High · Cached PIN verifiers can be cracked offline (§11.1)
- **Issue:** Cashier PINs are checked against "salted password hashes (Argon2id)" stored in IndexedDB on shared terminals. A 4–6 digit PIN has at most a million combinations, so anyone who copies the browser profile can recover PINs offline in minutes, whatever the hash function. If these are users' account password hashes, the exposure is worse.
- **Fix:** Use a POS-only PIN that is never the account password and is valid only on that device and branch. Store verifiers encrypted with a non-extractable device key (WebCrypto), lock out locally after a few failed attempts, clear the cache at shift close, and never cache account password hashes on terminals.

### NEW-08 · High · Client and server must calculate tax identically (A-04, A-05, §11.1, §11.4, §12.2)
- **Issue:** The offline POS calculates tax-inclusive VAT in JavaScript using integer minor units, the server uses `brick/math` at four decimal places, and the fiscal device signs whatever amounts the client sends. Any rounding difference means the fiscalized receipt, the ledger and the Mushak registers disagree. The design's own wireframe shows the risk: for mustard oil (৳280 at 5%) and soap (৳130 at 15%), the included VAT is ৳13.33 + ৳16.96 = ৳30.29, but the screen shows ৳28.30.
- **Fix:** Write one tax-calculation specification (rounding per line, rounding mode, cash rounding) with shared golden test vectors that run against both the PHP and JavaScript implementations in CI. Treat the fiscalized receipt as the document of record: the server stores the client's amounts and flags differences instead of silently recalculating them.

### NEW-09 · Medium · Business date and tax date are conflated (§7.5)
- **Issue:** §7.5 keeps sales made after midnight on the shift's opening date in order to avoid "corrupting monthly VAT returns". A tax invoice, though, should carry its actual date of issue; moving a 1 a.m. sale on the first of the month into the previous month puts it in the wrong VAT period. `sales_invoices` already has separate `invoice_date` and `business_date` columns, but the text doesn't say which one drives VAT.
- **Fix:** State that `invoice_date` (the local calendar date of issue) drives Mushak registers and VAT periods, and that `business_date` is only for shift and operational reporting. Confirm this with the VAT consultant.

### NEW-10 · Medium · Online and offline stock rules contradict each other (§2.3 OQ-02, §10.1, §16.2, RSK-05)
- **Issue:** OQ-02 says checkout is never rejected and shortfalls become supervised negative variances. The error example in §10.1 and the test in §16.2 reject an online checkout for insufficient stock, and RSK-05 still lists two alternative policies.
- **Fix:** State both rules explicitly: how online checkout behaves (reject, or allow negative stock as a tenant setting) and that offline sync always accepts. Align the example, the test and RSK-05.

### NEW-11 · Medium · Idempotent sync needs implementation rules (§9.1, §10.2)
- **Issue:** In PostgreSQL a unique violation aborts the whole transaction, so "safely catching" it only works if each sale has its own transaction or savepoint; a batch sync in one transaction would lose every sale after the duplicate. Also, `terminal_id` and `client_txn_id` are nullable, and NULLs are distinct in unique constraints, so a POS sale missing either value isn't protected.
- **Fix:** Insert with `ON CONFLICT DO NOTHING ... RETURNING` (or one savepoint per sale), return a result per sale, and add `CHECK (terminal_id IS NULL OR client_txn_id IS NOT NULL)`.

### NEW-12 · Medium · No branch manager or supervisor role, and discount limits conflict (§3.2, §11.2, §2.3)
- **Issue:** POS flows rely on a "supervisor PIN", a "manager discount override" of up to 15% and alerts to the "Branch Manager", but the permission matrix has no such role. Discount authority is inconsistent: cashiers up to 5%, managers up to 15% offline, Tenant Admin "Disallowed", Tenant Owner "Override". The matrix also still has no Sales Manager or Procurement Manager columns.
- **Fix:** Add Branch Manager and Shift Supervisor roles, define a single discount and override authority table, and complete the matrix.

---

## 3. Status of the 100 findings from review v1.1

Status values: **Resolved** (the fix is substantially in place; any notes are minor), **Partly** (key parts of the fix are missing), **Not addressed**, **Descoped** (removed from scope) and **Unclear**.

### 3.1 Technology baseline

| ID | Severity | Status | Notes |
|---|---|---|---|
| TECH-01 | High | Resolved | Laravel 13 baseline, recorded in ADR-007. |
| TECH-02 | Medium | Resolved | Filament v5 with Livewire 4. |
| TECH-03 | Medium | Partly | PHP 8.4 adopted. PostgreSQL 17 was chosen, but §17 and ADR-007 credit it with native `uuidv7()`, which arrived in PostgreSQL 18 (see DATA-01). |
| TECH-04 | Medium | Resolved | PHP-FPM chosen; Octane is no longer mentioned. |

### 3.2 Multi-tenancy and data isolation

| ID | Severity | Status | Notes |
|---|---|---|---|
| TEN-01 | Critical | Resolved | Session-level `set_config(..., false)` with PHP-FPM or session-mode PgBouncer, cleared in `terminate()`. Also rely on PgBouncer's `server_reset_query` (`DISCARD ALL`), because `terminate()` doesn't run if a worker dies. Connection limits are a new concern (NEW-05). |
| TEN-02 | Critical | Partly | Composite keys added for companies, fiscal years, periods, chart of accounts, journals and the stock-movement → company link. Still no foreign keys on `stock_movements` (warehouse, variant), `stock_balances` (any), `sales_invoices` (any), `journal_lines` (partner, tax code, branch), `chart_of_accounts.parent_id` or `journal_entries.reversed_entry_id`, and no DDL for branches, warehouses, products or customers. §6.2's "physically impossible" claim doesn't hold yet. |
| TEN-03 | High | Partly | Four roles defined, but policies grant only `app_rw` (NEW-04), there's no CI check for RLS coverage and no list of global tables. `migrator` is described as having no data access, but as table owner it does; the rule should be that its credentials are never used at runtime. |
| TEN-04 | High | Partly | Context is cleared in job loops, but jobs don't carry `tenant_id` in their payload or restore it, and scheduled tasks don't iterate tenants. |
| TEN-05 | High | Partly | The header is now only an assertion check. Custom-domain verification, TLS issuance and cookie scoping are still missing. |
| TEN-06 | High | Not addressed | No tenant export, single-tenant restore or offboarding procedure. The PDPO export in §12.3 covers one person's data, not a tenant's. |
| TEN-07 | Medium | Unclear | The dedicated-database tier is no longer mentioned. State whether it's deferred or dropped. |
| TEN-08 | Medium | Not addressed | No indexing or partitioning strategy for tenant tables. |
| TEN-09 | High | Partly | Cache keys, the permission cache and S3 paths are tenant-scoped. Sessions, queues and search aren't covered. Teams mode alone doesn't make laravel-permission's cache safe under RLS: confirm its cache key varies by tenant, or keep its tables outside RLS. No isolation tests beyond SQL. |
| TEN-10 | High | Partly | Global users, tenant memberships and location scopes added. But platform admins are a flag (`is_platform_admin`) on the shared users table, which contradicts §1.2's separate guard; roles live in two places (`tenant_memberships.role` and laravel-permission); and the choice between Filament's tenancy and `TenantContext` still isn't made. |
| TEN-11 | Medium | Not addressed | Active, In-Arrears and Suspended appear only as values in a DDL comment. |

### 3.3 Module architecture

| ID | Severity | Status | Notes |
|---|---|---|---|
| MOD-01 | High | Resolved | Accounting is foundational; operational modules depend on its posting contract. |
| MOD-02 | High | Partly | The contract now covers Filament resources, scheduled tasks, settings schema, default data and health checks. Migrations, install/upgrade/uninstall hooks and policies are still missing. |
| MOD-03 | Medium | Resolved | Separate platform and tenant lifecycles; disabling is blocked by dependents and open documents. |
| MOD-04 | Medium | Resolved | Platform-wide versions with Pennant flags per tenant. Derive the flags from subscription records so they can't drift from billing. |
| MOD-05 | High | Resolved | The Reporting module reads views and the replica. Replica access stays blocked until NEW-04 is fixed. |
| MOD-06 | High | Resolved | Synchronous posting in the same transaction; events only for side effects. |
| MOD-07 | Medium | Partly | Schema and `SKIP LOCKED` polling defined. Missing: the poller's role under RLS (NEW-04), ordering per aggregate and cleanup of published rows. The outbox still uses `ON DELETE CASCADE`. |
| MOD-08 | Medium | Descoped | The plugin marketplace is out of scope. Keep the public API OAuth-ready in case it returns. |
| MOD-09 | Medium | Not addressed | No architecture tests; ADR-001 still relies on typed interfaces and code review. |

### 3.4 Accounting and financial integrity

| ID | Severity | Status | Notes |
|---|---|---|---|
| ACC-01 | Critical | Partly | Deferred balance trigger added, but it can be bypassed (NEW-03). |
| ACC-02 | Critical | Partly | Immutability triggers added and `CASCADE` replaced on financial tables, but the triggers are defective (NEW-02). |
| ACC-03 | High | Partly | Fiscal year and period tables, plus `period_id` on journals. Nothing checks that the posting date falls inside an open period; year-end close and opening balances are still missing. |
| ACC-04 | High | Partly | Currency, rate and base amounts on journal lines. Foreign-currency supplier bills and realized FX gains and losses aren't described. |
| ACC-05 | High | Partly | Decimals, `brick/math` and string amounts in JSON. Still no rounding policy, and the JavaScript and PHP calculations can diverge (NEW-08). |
| ACC-06 | Medium | Not addressed | AR/AP still appear in Sales, Procurement and Accounting (§8.2 items 4, 6 and 7). |
| ACC-07 | Medium | Not addressed | Only POS invoice series are defined; there's no number-series service or document state machine. |

### 3.5 Inventory

| ID | Severity | Status | Notes |
|---|---|---|---|
| INV-01 | High | Partly | `stock_movements` added. No cost-layer table for FIFO, nothing stops movements being updated or deleted, no foreign keys to warehouse or variant, and the stock ledger is reconciled to balances but not to the GL inventory account. |
| INV-02 | High | Resolved | `UNIQUE NULLS NOT DISTINCT`. |
| INV-03 | Medium | Partly | Warehouses at company level and stock locations added, but movements and balances have no location column. |
| INV-04 | Medium | Partly | Negative-stock policy decided (OQ-02), though it contradicts the online path (NEW-10). No reservation records. |
| INV-05 | High | Resolved | Sorted lock order with retries. Laravel also retries deadlocks (SQLSTATE 40P01), not only 40001; handle the race when two checkouts insert the first balance row for a variant. |
| INV-06 | Medium | Partly | Count sessions mentioned. FEFO allocation and landed costs that arrive after the sale aren't covered, and cycle-count automation is Phase 2. |

### 3.6 POS and offline operation

| ID | Severity | Status | Notes |
|---|---|---|---|
| POS-01 | Critical | Resolved | Client-side Alpine.js and Dexie PWA (ADR-004). |
| POS-02 | Critical | Resolved | Unique key on `(tenant_id, terminal_id, client_txn_id)`. Implementation rules are still needed (NEW-11). |
| POS-03 | Critical | Partly | Pre-allocated terminal number blocks. NBR acceptance is still pending (OQ-03, Legal/Tax, week 2). Not defined: fiscal-year reset, what happens when a block runs out offline, voided numbers, and whether the SDC issues its own invoice numbers. |
| POS-04 | High | Partly | Offline policy matrix added. Per-transaction sync results, a maximum offline duration and service-worker updates during a shift aren't covered. |
| POS-05 | Medium | Resolved | Device token and PIN sign-in replace the MAC address. |
| POS-06 | Medium | Partly | The print bridge is now the primary path, with raster Bangla and cash drawer kick. The bridge itself needs a design (NEW-06), and customer displays and label printers are still missing. |
| POS-07 | High | Resolved | Offline PIN sign-in and a separate device credential for sync. PIN storage needs hardening (NEW-07). |
| POS-08 | High | Partly | `persist()`, kiosk mode and a 4-hour alert. The browser can refuse `persist()` and the design doesn't check the result; beyond a manual export at 80% storage there's no printed or exported journal for reconstruction. |
| POS-09 | High | Not addressed | No sync API versioning or client update policy. "Signed PWA updates" in §12.1 doesn't cover compatibility. |
| POS-10 | Medium | Not addressed | No clock-skew handling or backoff for mass reconnects. |
| POS-11 | Medium | Resolved | Kiosk mode, key suppression and the cart saved on every keystroke. Scanner focus handling is still missing. |

### 3.7 Bangladesh tax and regulation

| ID | Severity | Status | Notes |
|---|---|---|---|
| TAX-01 | Critical | Partly | Fiscal adapter in MVP scope. Not defined: behaviour when the SDC is unavailable or the terminal is offline, the order of fiscalization and server commit, NBR's integration specification and any certification or software-listing requirement, and SDC-issued numbers (POS-03). The roadmap gives it three weeks shared with checkout and the hardware bridge. |
| TAX-02 | High | Partly | 10% and turnover tax added. Supplementary duty is missing from the rate schedule, tax codes aren't effective-dated, and §12.2 states the turnover-tax rate as 4% although published sources disagree. |
| TAX-03 | High | Partly | Mushak 6.5, 6.6 and 9.1 listed in §12.2, but §1.3 puts 6.5 and full 9.1 in Phase 2. The BDT 25,000 buyer-BIN rule is still unsourced and is now repeated in §12.3. Central vs. unit registration and input-credit tracking are still missing. |
| TAX-04 | High | Partly | Import duties moved explicitly to Phase 2. That's acceptable only if import and trading tenants are kept out of the pilot; LC management is still missing. |
| TAX-05 | High | Partly | Consent, data minimization, subject-access export and anonymization added. Breach notification, retention schedules, a DPO, data classification and the legal basis for hosting in Singapore are missing; the hosting decision is pending (OQ-04) while the schema already defaults to `sgp-1`. |
| TAX-06 | Medium | Not addressed | No retention periods. |
| TAX-07 | High | Partly | MRP field and tax-inclusive price lists added. Nothing blocks selling above MRP, rounding isn't defined, tariff values aren't covered, and the wireframe's VAT figure is wrong (NEW-08). |
| TAX-08 | Medium | Descoped | The global-extensibility claim was removed; the scope is now explicitly Bangladesh. |

### 3.8 Security

| ID | Severity | Status | Notes |
|---|---|---|---|
| SEC-01 | High | Partly | Five SoD rules added. The matrix still lacks Sales Manager and Procurement Manager columns, and there's no supervisor role (NEW-12). |
| SEC-02 | High | Resolved | Consent switch, 4-hour limit, ticket reference, banner and read-only by default. |
| SEC-03 | Medium | Partly | Hash chain and a daily WORM anchor. Concurrent writes for one tenant will read the same previous hash and fork the chain unless writes are serialized per tenant (e.g. with an advisory lock); hashing needs a canonical JSON form; no partitioning. |
| SEC-04 | Medium | Partly | Envelope encryption is named, but there's no key-management or rotation design. |
| SEC-05 | Medium | Partly | Timestamped HMAC and IPv4 private ranges. Missing: IPv6 ranges (`::1`, `fc00::/7`, `fe80::/10`), `0.0.0.0/8` and `100.64.0.0/10`, connecting to the validated IP to stop DNS rebinding, dual-secret rotation and inbound webhook verification. |
| SEC-06 | Medium | Partly | Login throttling, TOTP and Argon2id. Passkeys, an SMS OTP fallback, token expiry and OAuth2 are missing. |
| SEC-07 | Medium | Partly | Tenant prefixes and 60-second presigned URLs. Malware scanning and quotas are missing. |
| SEC-08 | Medium | Partly | `composer audit`, `npm audit`, Trivy and PHPStan added. No penetration test or dynamic scanning, and absolute claims remain ("physically impossible", "mathematically eliminate"). |
| SEC-09 | High | Partly | Location-scope middleware and a scope table. Middleware alone doesn't filter queries: enforcement belongs in query scopes and policies on every surface, with tests. |
| SEC-10 | Medium | Partly | All ten categories are mapped, but from the 2021 list. The 2025 edition folds SSRF into Broken Access Control and adds Software Supply Chain Failures and Mishandling of Exceptional Conditions. CSP, `frame-ancestors` and service-worker scope aren't covered. |

### 3.9 Data architecture

| ID | Severity | Status | Notes |
|---|---|---|---|
| DATA-01 | Medium | Not addressed | The DDL still uses `gen_random_uuid()` (v4) and installs the unused `uuid-ossp` extension. Native `uuidv7()` needs PostgreSQL 18, not 17. |
| DATA-02 | Medium | Partly | Entity model reconciled (no Organization entity). DDL is still missing for branches, warehouses, locations, products, variants, customers, suppliers, users, memberships, tax codes, number series and payments. |
| DATA-03 | Medium | Not addressed | No custom fields. |
| DATA-04 | Medium | Not addressed | No capacity estimates or partitioning plan. |
| DATA-05 | Medium | Partly | `pg_trgm` enabled. No transliterated search keys or ICU collation. |
| DATA-06 | Medium | Partly | `is_reconcilable` and journal-line dimensions added. Still no check constraints on status columns, audit columns, `lock_version`, soft-delete policy or polymorphic integrity checks. |
| DATA-07 | High | Resolved | Business date derived in the branch time zone, with a shift cutover rule. The text conflates business date with tax date (NEW-09). |
| DATA-08 | Medium | Partly | Bilingual names for products, categories and tax codes. Customers and the Bangla-digits option aren't covered. |

### 3.10 API and integration

| ID | Severity | Status | Notes |
|---|---|---|---|
| API-01 | Medium | Partly | OpenAPI 3.1 and RFC 9457 errors. No versioning and deprecation policy, pagination convention or rate-limit headers. |
| API-02 | Medium | Partly | SMS in MVP; couriers and direct MFS in Phase 2. Bangla QR and stock allocation between POS and online channels are missing. |
| API-03 | Medium | Not addressed | Listed as resolved, but keys aren't tied to a request hash, so reuse with a different body isn't detected. |

### 3.11 Infrastructure, cost and reliability

| ID | Severity | Status | Notes |
|---|---|---|---|
| OPS-01 | High | Partly | A $170 bill of materials, but for production only: C-01 covers development and staging too, and §15.2 requires a separate staging database. Reverb, the print bridge and self-hosted monitoring aren't costed, the provider is still "Cloud Run / DigitalOcean", and connection limits aren't considered (NEW-05). |
| OPS-02 | High | Partly | Read replica and Redis AOF. A read replica isn't an automatic-failover standby, so the "high availability" claim doesn't hold; there's no failover runbook or measured restore time. |
| OPS-03 | Medium | Partly | Catalog delta sync and Reverb pushes to terminals. Server-side cache invalidation isn't described. |
| OPS-04 | Medium | Not addressed | No per-tenant job concurrency limits or UI/API rate limits; only login throttling. |
| OPS-05 | Medium | Partly | 99.9% availability target added. No measurement points or error budget, fiscalization isn't in the latency budget, and one 30-minute recovery would use most of the month's 43-minute allowance. |
| OPS-06 | Medium | Not addressed | Disaster recovery still covers only PostgreSQL. |

### 3.12 Observability

| ID | Severity | Status | Notes |
|---|---|---|---|
| OBS-01 | High | Partly | Sentry, OpenTelemetry, slow-query alerts and integrity watchdogs. Missing: uptime checks, queue-depth and failed-job alerts, p95 dashboards and per-terminal sync-backlog telemetry. The watchdog needs a role that can read across tenants (NEW-04). |

### 3.13 DevOps and testing

| ID | Severity | Status | Notes |
|---|---|---|---|
| QA-01 | Medium | Resolved | A real multi-process test. It can't use Laravel's `RefreshDatabase`, which wraps each test in a transaction the child processes can't see: use `DatabaseMigrations` or truncation, and set tenant context in the child processes. |
| QA-02 | Medium | Partly | Playwright with offline simulation. Property-based accounting tests, VAT golden vectors and migration tests are missing. |
| QA-03 | Medium | Partly | Expand/contract migrations and an isolated, sanitized staging database. Infrastructure as code, secrets management and a release calendar are missing. |

### 3.14 Roadmap and delivery

| ID | Severity | Status | Notes |
|---|---|---|---|
| PLAN-01 | High | Not addressed | The chart is still titled 24 weeks, but its bars run to week 27 (pilot in weeks 21–26, go-live in week 27), while Milestone 5 says the pilot completes in week 24. The first task is still marked done and the second active, although work starts on 2 November. Milestone 2 (week 10) comes before the VAT engine finishes (week 12). Subscription billing, the Super-Admin panel, onboarding, data import, reporting and the print bridge still have no tasks. |
| PLAN-02 | High | Partly | Accounting and VAT now come before POS checkout, and the pilot is 6 weeks. Isolation and concurrency testing are still a block at the end (weeks 19–20). |
| PLAN-03 | High | Not addressed | No cut lines. MVP scope grew (fiscal adapter, cash management, units of measure, MRP, VDS, PDPO features, Reverb, settings engine, multi-company) while the team and timeline stayed the same. |
| PLAN-04 | Medium | Partly | Nine risks with owners. Missing: changes to VAT rates and data law (RSK-03 covers only fiscal devices), framework upgrades, data migration and adoption, dependence on one provider, and scope creep. |

### 3.15 UI/UX

| ID | Severity | Status | Notes |
|---|---|---|---|
| UX-01 | Medium | Not addressed | Still two wireframes and no design tokens. |
| UX-02 | Medium | Resolved | mPDF with Bangla fonts. Test conjunct rendering on every template. |
| UX-03 | Medium | Not addressed | No page-weight budgets or slow-network testing. |
| UX-04 | Medium | Not addressed | No device and browser support matrix; kiosk Chrome is implied but not stated as a requirement. |

### 3.16 Core platform services

| ID | Severity | Status | Notes |
|---|---|---|---|
| CORE-01 | Medium | Resolved | Channels, bilingual templates and SMS metering. Per-user preferences and delivery tracking are still open. |
| CORE-02 | Medium | Resolved | Queued, chunked exports with expiring links. §6.4 says presigned URLs last 60 seconds while §7.2 promises a 24-hour link; clarify that the link issues a fresh short-lived URL on each use. |
| CORE-03 | Medium | Resolved | Cascading settings with audit and cache invalidation. |
| CORE-04 | Medium | Resolved | Laravel Reverb with tenant- and branch-scoped channels. Channel authorization must check location scope, and Reverb needs a line in the bill of materials. |

### 3.17 Document governance

| ID | Severity | Status | Notes |
|---|---|---|---|
| DOC-01 | Medium | Not addressed | No requirements appendix, glossary or named owner and approvers. The status says "Approved" without saying by whom. |

---

## 4. Documentation fixes

### 4.1 Status of the 29 fixes from review v1.1

**Applied (20):** Filament version named; the §4.1 diagram node fixed; outbox name unified; Octane removed; synchronous posting stated; separate lifecycles; RLS wording corrected; decimals chosen over integers; risk register cites the real trigger; §12.3 test case removed; cipher statement corrected; reservations no longer queued; §16.2 test rewritten; 10% VAT added; open questions have owners and dates; ADRs have alternatives; layers named instead of numbered; MFS in MVP recorded by reference; staging uses its own database; all ten OWASP categories mapped (but see SEC-10).

**Partly applied or not applied (9):**

| Fix | Status | What's left |
|---|---|---|
| Three different hierarchies | Partly | The entity model is fixed, but the dashboard wireframe and §7.3 still refer to an "Organization". |
| ADR-003 vs. schema | Partly | Triggers added but defective (NEW-02). |
| §11.4 wireframe | Partly | VAT is now inclusive and the typo is fixed, but the VAT total is wrong (NEW-08) and real brand names remain: Shwapno, a real supermarket chain, and Lifebuoy. |
| LaTeX and escaped `\$` | Partly | Latency targets are plain text now, but `\$` and `$\rightarrow$` remain in §1.1, §5.3, §7.3, §13.2, §14, §18 and the ADRs. |
| "Mathematically impossible" | Not applied | Now "physically impossible" (§6.2), "mathematically eliminate" (§13.1) and "mathematically enforce" (§9.2), plus "bulletproof", "flawlessly" and "zero event loss". |
| Roadmap weeks and statuses | Not applied | See PLAN-01. |
| Personas missing from the matrix | Not applied | See NEW-12. |
| "10x faster" claim | Not applied | Now "5x development speed" (§17), still unsupported. |
| Mermaid validation in CI | Not applied | Not mentioned. |

### 4.2 New documentation fixes in v1.1.0

| Location | Problem | Fix |
|---|---|---|
| §6.4 vs §7.2 | 60-second presigned URLs vs. 24-hour download links | Say the link issues a fresh presigned URL on each use |
| §17, ADR-007 | PostgreSQL 17 credited with native `uuidv7()`; it arrived in PostgreSQL 18 | Correct it, or move to PostgreSQL 18 |
| §9.1 | `uuid-ossp` extension installed but never used | Remove it |
| §6.1 | `migrator` said to have no table-data access; a table owner always does | Say its credentials are never used at runtime |
| RSK-08 vs §13.2, §4.1 | The web tier scales to 5 instances in RSK-08 but 3 in the bill of materials; §4.1 shows a "Redis Cluster" while the bill of materials has one 1 GB instance | Align them |
| §1.3 vs §12.2 | Mushak 6.5 and full 9.1 are Phase 2 in §1.3, but §12.2 says the system already satisfies them | State the phase per form |
| §11.4 | Included VAT shown as ৳28.30; the correct figure is ৳30.29 | Recalculate |
| Header, §2.3 | "Approved" with three open questions pending and no approvers; OQ-01, a legal question, is owned by the Tech Lead; OQ-03 records a resolution before its decision date | See NEW-01 |
| §4.1 | An arrow from Redis to PostgreSQL implies Redis writes to the database | Remove or relabel it |
| §13.1 | Retries mention only SQLSTATE 40001; deadlocks are 40P01 | Mention both |
| §12.1 | Category names from OWASP Top 10:2021 | Use the 2025 list |
| §1.3 | "Providend Fund" | "Provident Fund" |

---

## 5. Functional gaps

Status of the 23 functional gaps listed in review v1.1.

**Covered in MVP (4):** units of measure, customer credit ("baki"), cash management, and an explicit out-of-scope list.

**Scheduled for Phase 2 (4):** weighing-scale barcodes, loyalty, serial numbers and warranty, and courier integration.

**Still open (15):**

| Capability | Status | Notes |
|---|---|---|
| SaaS subscription billing | Not addressed | Mentioned as a Super-Admin function only. |
| Data import and migration | Not addressed | No import of products, opening stock, balances or customers. |
| Promotions and discounts | Not addressed | Only a discount percentage at the POS. |
| Cheque management | Not addressed | |
| Expenses and petty cash | Not addressed | |
| Approval engine design | Not addressed | SoD rules exist, but there's no multi-level approval design. |
| In-app help and support | Not addressed | |
| Custom fields | Not addressed | Same as DATA-03. |
| Barcode and label generation | Not addressed | |
| Training mode and sandbox tenants | Not addressed | |
| Tenant onboarding wizard | Partly | `seedDefaultData()` exists in the module contract, but there's no wizard or sign-up abuse controls. |
| Withholding (VDS, TDS/AIT) | Partly | VDS (Mushak 6.6) covered; withholding on supplier payments isn't. |
| Document templates | Partly | Mushak forms and mPDF, but no template system. |
| Pricing rules | Partly | Tier price lists only; no customer- or branch-specific prices, effective dates or precedence rules. |
| Digital receipts | Partly | SMS receipt consent is mentioned (§12.3), but the feature isn't scoped or phased. |

---

## 6. ADRs

Status of the 17 ADRs recommended in review v1.1.

- **Recorded as ADRs (3):** framework baseline (ADR-007), POS front-end technology (ADR-004), and receipt and PDF rendering (ADR-006 and §17).
- **Decided in the body text but not recorded as ADRs (9):** tenant context and pooling (§4.2), database roles (§6.1), money representation (A-04; rounding still missing), synchronous GL posting (§5.4), invoice numbering and fiscal integration (OQ-03, §12.2; pending legal), offline policy (§11.2), module versioning and flags (§5.3), user identity (§6.5; Filament tenancy still undecided), and business dates (§7.5).
- **Still undecided (5):** hosting provider and region (OQ-04), primary key strategy (DATA-01), tenant export and restore (TEN-06), search (DATA-05), and sync API versioning (POS-09).

Add an ADR for the local print and fiscal bridge as well (NEW-06).

---

## 7. Product-owner questions

- **Answered (5):** turnover-tax businesses are supported (Q2); terminals must work offline for at least 8–12 hours, with a policy matrix (Q3); multi-company is in MVP (Q4), which adds to PLAN-03; one login works across tenants (Q9); retail shelf prices are tax-inclusive (Q10).
- **Pending a decision (2):** in-country hosting (Q5, legal, week 3) and NBR acceptance of per-terminal invoice series (Q6, legal and tax, week 2).
- **Unanswered (3):** which retail segment goes first (Q1; supermarkets are implied but not stated), whether POS-only and accounting-only plans exist (Q7), and how pilot tenants pay (Q8).

---

## 8. Recommended next steps

1. **Fix the ledger triggers** (NEW-02, NEW-03) and add tests for every insert, update, delete and posting path.
2. **Finish the database layer:** composite foreign keys and DDL for every MVP table (TEN-02, DATA-02), and RLS policies for every role, including reporting, platform and background jobs (NEW-04).
3. **Close the hardware and fiscal path:** an ADR for the bridge (NEW-06), fiscal-device failure and offline behaviour plus the NBR integration specification (TAX-01), NBR confirmation of terminal series (POS-03), and a shared tax-calculation specification with golden tests (NEW-08).
4. **Re-plan:** correct the roadmap arithmetic, add the missing workstreams (billing, Super-Admin, onboarding, data import, bridge) and set cut lines (PLAN-01, PLAN-03); write a connection budget and a bill of materials covering all three environments (NEW-05, OPS-01).
5. **Correct the status line** (NEW-01) and track each finding's status in the design document.
6. **Then the remaining High findings** that aren't addressed at all: tenant export and offboarding (TEN-06) and sync API versioning (POS-09).

---

## 9. Sources

- [OWASP Top 10:2025 introduction](https://top10.owasp.org/2025/0x00_2025-Introduction/)
- [Localtonet: Chrome Local Network Access and WebSockets](https://localtonet.com/blog/chrome-local-network-access-apis-websockets)
- [DbVisualizer: UUIDv7 in PostgreSQL 18](https://www.dbvis.com/thetable/uuidv7-in-postgresql-18-what-you-need-to-know/)
- Sources for framework versions and Bangladesh VAT, fiscal-device and data-protection rules are listed in `ERP_DESIGN_REVIEW.md` v1.1, section 9.

Regulatory statements in this review are a starting point for the design team, not legal or tax advice. Confirm them with a Bangladesh VAT consultant and a lawyer before relying on them.
