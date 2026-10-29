# ERP System Design: Architecture Review

| | |
|---|---|
| **Document reviewed** | `ERP_SYSTEM_DESIGN.md` v1.0.0 (Baseline, 2026-10-26) |
| **Review date** | 26 October 2026 |
| **Review version** | 1.1 (second pass; see [revision history](#10-revision-history)) |
| **Verdict** | Strong, well-structured baseline. **Not yet build-ready.** |
| **Findings** | 8 Critical · 36 High · 56 Medium · 29 documentation fixes |

Finding IDs (e.g. `TEN-02`) are stable, so they can be referenced in tickets and in the design changelog.

---

## Contents

1. [Executive summary](#1-executive-summary)
2. [Severity scale](#2-severity-scale)
3. [Findings by area](#3-findings-by-area)
4. [Functional gaps](#4-functional-gaps)
5. [Internal inconsistencies and document fixes](#5-internal-inconsistencies-and-document-fixes)
6. [ADRs to add](#6-adrs-to-add)
7. [Remediation plan](#7-remediation-plan)
8. [Questions for the product owner](#8-questions-for-the-product-owner)
9. [Sources](#9-sources)
10. [Revision history](#10-revision-history)

---

## 1. Executive summary

The design makes several sound foundational choices: a modular monolith sized to the team and budget, shared-schema multi-tenancy backed by PostgreSQL Row-Level Security (RLS), ledger immutability, a transactional outbox, an offline-first POS and an early focus on Bangladesh VAT. The document is well organized and covers all 20 required sections, although some are thin: §7, for example, has no notification, reporting or search services.

The problems are mostly in the details that decide whether those choices actually work:

- Several database guarantees the document relies on are **not enforced by the DDL it shows**: balanced journals, cross-tenant references, immutability and unique stock rows.
- The **POS stack contradicts the offline requirement** (Livewire can't run offline), and the **idempotency and invoice-numbering design will produce duplicates or compliance gaps** once terminals go offline.
- **Bangladesh compliance is framed as future work** even though fiscal devices (EFD/SDC) are already mandatory for many retail categories, and the new personal data protection law isn't addressed.
- The **framework baseline is already behind**: Laravel 12 leaves security support during the planned build.
- The **roadmap doesn't add up** (29 weeks of work in a "24-week" plan) and leaves out several MVP modules.

### What the design gets right

- Modular monolith over microservices for a 3–5 person team (ADR-001).
- Defence in depth for tenancy: RLS plus an Eloquent global scope.
- A fail-closed RLS policy (`NULLIF(current_setting(..., true), '')`).
- Ledger corrections through reversals rather than edits (ADR-003).
- Transactional outbox for reliable webhooks (ADR-005).
- Explicit latency targets, RPO/RTO and noisy-neighbour controls.
- Real local conditions taken seriously: unreliable connectivity, ESC/POS printers, lakh/crore formatting, Bangla UI.

### Top 10 issues to fix first

| # | Issue | ID | Severity |
|---|---|---|---|
| 1 | `SET LOCAL` tenant context only works inside a transaction; as written, requests either see no data or leak context across pooled connections | TEN-01 | Critical |
| 2 | Foreign keys don't stop cross-tenant references, because RLS doesn't apply to FK checks | TEN-02 | Critical |
| 3 | Nothing enforces debits = credits per journal entry | ACC-01 | Critical |
| 4 | Immutability rules contradict the schema (`is_posted` updates, `ON DELETE CASCADE`) | ACC-02 | Critical |
| 5 | POS is specified on Livewire, which can't work offline | POS-01 | Critical |
| 6 | Redis-only idempotency with a 24-hour TTL can double-post offline sales | POS-02 | Critical |
| 7 | Gapless invoice numbering conflicts with offline receipts and checkout throughput | POS-03 | Critical |
| 8 | EFD/SDC fiscal devices treated as an open question, though already mandatory for many retail categories | TAX-01 | Critical |
| 9 | Laravel 12 baseline leaves security support during the build | TECH-01 | High |
| 10 | Roadmap totals 29 weeks, not 24, and omits several MVP modules | PLAN-01 | High |

### What the second pass added (v1.1)

A second, section-by-section read of the design added 30 findings (9 High, 21 Medium), 7 documentation fixes and 5 functional gaps. The most important additions:

- **Offline POS resilience:** cashier sign-in and token expiry while offline (POS-07), unsynced sales lost when the browser evicts local storage (POS-08), and older terminal versions failing to sync after a release (POS-09).
- **Isolation beyond the database:** caches and package caches that ignore tenants (TEN-09), and no user identity model for people who work across several tenants (TEN-10).
- **Branch and company scoping** inside a tenant, which RLS doesn't cover (SEC-09).
- **Checkout lock contention and deadlocks** on fast-moving items (INV-05).
- **Tax-inclusive MRP pricing**, the norm for packaged goods, which the design doesn't support (TAX-07).
- **Business dates derived from UTC**, which put sales made between midnight and 6 a.m. on the wrong day (DATA-07).

All new IDs: TEN-09 to TEN-11, MOD-09, ACC-07, INV-05, INV-06, POS-07 to POS-11, TAX-07, TAX-08, SEC-09, SEC-10, DATA-06 to DATA-08, API-03, OPS-05, OPS-06, PLAN-04, UX-03, UX-04, CORE-01 to CORE-04 and DOC-01.

---

## 2. Severity scale

| Severity | Meaning |
|---|---|
| **Critical** | Will cause data corruption, cross-tenant exposure, financial misstatement or legal non-compliance if built as written. Fix before development starts. |
| **High** | Causes major rework if not settled before the related phase begins. |
| **Medium** | Address in the design within the phase it affects. |
| **Low** | Documentation fixes and minor corrections, listed in [section 5](#5-internal-inconsistencies-and-document-fixes). |

---

## 3. Findings by area

### 3.1 Technology baseline

#### TECH-01 · High · Laravel 12 is already behind (Header, §17, ADR-001)
- **Issue:** Laravel 13 was released in March 2026 and supports PHP 8.3–8.5. Laravel 12's bug-fix window closed on 13 August 2026 and its security fixes end in February 2027, inside the planned build window and before the pilot.
- **Fix:** Baseline on Laravel 13. Add a standing "annual framework upgrade" item to the roadmap, since Laravel ships a new major version every year.

#### TECH-02 · Medium · "Filament v3/v4" is not a decision (Header, §17)
- **Issue:** v3, v4 and v5 are separate majors tied to different Livewire versions, so a project can't run "v3/v4". Filament v5 (January 2026) is functionally v4 plus Livewire 4 support. Plugin choices such as Filament Shield depend on the version picked.
- **Fix:** Choose Filament v5 with Livewire 4 (or v4 with Livewire 3, with a stated reason) and confirm every planned plugin supports it before committing.

#### TECH-03 · Medium · Runtime versions (Header)
- **Issue:** PHP 8.3 is in security-only support. PostgreSQL 16 lacks native `uuidv7()`, which arrived in PostgreSQL 18 (see DATA-01).
- **Fix:** PHP 8.4 (or 8.5) and PostgreSQL 17 or 18, subject to what the chosen managed provider offers.

#### TECH-04 · Medium · Octane is mentioned but never decided (§4.2 step 6)
- **Issue:** The request lifecycle refers to Laravel Octane/FrankenPHP, but the stack doesn't include it. Octane keeps the application in memory between requests, so a singleton `TenantContext` can leak into the next request unless the code is written for that model.
- **Fix:** Start on PHP-FPM. Adopt Octane only after measuring a need, through an ADR that includes Octane-safe coding rules and a context-leak test.

### 3.2 Multi-tenancy and data isolation

#### TEN-01 · Critical · Tenant context setting won't work as described (§4.2, §6.4)
- **Issue:** `SET LOCAL` only takes effect inside a transaction block. Laravel doesn't wrap every request in a transaction, so most reads (every Filament page, for example) would run with no tenant set and RLS would return zero rows. The obvious workaround, a session-level `SET`, is unsafe behind PgBouncer in transaction-pooling mode: the value stays on the server connection and the next client inherits it. Queue workers have the same problem (TEN-04).
- **Fix:** Choose one model and record it in an ADR.
  - **Recommended for MVP:** PHP-FPM with one connection per request and no transaction-mode pooler (direct connections or PgBouncer in session mode). Call `set_config('app.current_tenant_id', :id, false)` immediately after connecting and reset it on teardown.
  - **If transaction pooling is needed later:** open a transaction per unit of work and call `set_config(..., true)` as its first statement, enforced by a database connection wrapper rather than by convention.
  - Run the "connection pool bleed" test from §12.3 against the real pooler configuration.

#### TEN-02 · Critical · Foreign keys allow cross-tenant references (§9.2, §12.3)
- **Issue:** PostgreSQL's referential-integrity checks bypass row security. `journal_lines.account_id REFERENCES chart_of_accounts(id)` accepts another tenant's account ID if someone knows it. Many reference columns (`company_id`, `branch_id`, `warehouse_id`, `product_variant_id`, `posted_by`, `cost_center_id`) have no foreign key at all. Test Case 2 in §12.3 expects an FK violation that won't happen.
- **Fix:** Add `UNIQUE (tenant_id, id)` to every tenant-scoped table and use composite foreign keys, e.g. `FOREIGN KEY (tenant_id, account_id) REFERENCES chart_of_accounts (tenant_id, id)`. Add the missing foreign keys. Keep application-level validation as a second layer.

#### TEN-03 · High · Database roles and cross-tenant access are undefined (§6.3)
- **Issue:** `FORCE ROW LEVEL SECURITY` applies RLS to the table owner, but superusers and roles with `BYPASSRLS` still bypass it. The design doesn't say which roles exist or how the Super-Admin panel, outbox poller, metering job and scheduler read across tenants.
- **Fix:** Define the roles:
  - `migrator`: owns the schema and runs DDL.
  - `app_rw`: application role, RLS enforced, never `BYPASSRLS`.
  - `platform_ro`: cross-tenant reads for Super-Admin and metering, on a separate connection, with every query audited.
  - `reporting_ro`: read replica access.

  List the global tables that are exempt from RLS (`tenants`, `plans`, `modules`, currencies). Add a CI check that fails if any table with a `tenant_id` column lacks RLS and a policy.

#### TEN-04 · High · Tenant context outside HTTP requests (§4.2)
- **Issue:** Only the HTTP path is described. Horizon jobs, scheduled commands, artisan commands and inbound webhooks also need tenant context. Without it they silently process nothing, or process the wrong tenant.
- **Fix:** Serialize `tenant_id` into every job payload and restore it in job middleware, make scheduled tasks iterate tenants explicitly, and cover each path with tests.

#### TEN-05 · High · The `X-Tenant-ID` header must not select the tenant (§4.2)
- **Issue:** As written, any caller could choose a tenant by sending a header. Custom domains also need TLS issuance and ownership verification, and session cookies must be scoped so one tenant's subdomain can't read or fix another's session.
- **Fix:** Derive the tenant from the authenticated credential and use the header only as a consistency check. Specify custom-domain onboarding (DNS verification, on-demand TLS or a service such as Cloudflare for SaaS) and cookie domain rules.

#### TEN-06 · High · No single-tenant restore, export or offboarding (§6.2)
- **Issue:** The comparison table admits single-tenant restore is complex in a shared schema, but no procedure exists. Point-in-time recovery restores every tenant at once. Tenants will delete data by mistake, ask for exports, and leave.
- **Fix:** Build tenant-scoped logical export and import early. The same tooling covers single-tenant restore, offboarding, moving a tenant to a dedicated database, and producing sanitized staging data. Define the offboarding lifecycle: notice, export, statutory retention, then controlled purge.

#### TEN-07 · Medium · Dedicated-database tier is underspecified (§6.1)
- **Issue:** Missing: migration orchestration across many databases, version skew, connection management, metering and Super-Admin reporting across databases, and moving a tenant between tiers.
- **Fix:** Defer the dedicated tier until after MVP and build it on the export/import tooling from TEN-06.

#### TEN-08 · Medium · RLS performance isn't addressed (§6.3, §13)
- **Issue:** No indexing or partitioning strategy for tenant-scoped tables.
- **Fix:** Lead composite indexes with `tenant_id`. Partition the fastest-growing tables (audit logs, stock movements, journal lines) by time. Benchmark RLS overhead against the p95 targets before Phase 2.

#### TEN-09 · High · Isolation stops at the database (§6.4, §7.2, §13.2)
- **Issue:** RLS protects PostgreSQL rows only. Caches, sessions, queued jobs, uploaded files and search indexes also hold tenant data, and apart from the catalog cache and rate limits the design doesn't say how they're scoped. Packages that cache database rows under one global key will cache whatever rows RLS returned for the first tenant that loaded them. laravel-permission's permission cache, which Filament Shield (§7.2) relies on, works this way by default.
- **Fix:** Wrap the cache store so every key carries the tenant ID, run laravel-permission in teams mode with a tenant-aware cache key, and audit every package that caches models. Extend the isolation tests to cache, queue and file storage, not just SQL.

#### TEN-10 · High · The user identity model is undefined (§3, §7.1)
- **Issue:** The design doesn't say whether a user belongs to one tenant or several. External accountants, auditors and owners of more than one business need access to multiple tenants with a single login. It also doesn't say whether Super-Admins share the users table with tenant users, how each of the three panels authenticates, or whether Filament's built-in tenancy is used alongside the custom `TenantContext`. Supplier portal users (Phase 2) add a third kind of identity.
- **Fix:** Use a global `users` table with `tenant_memberships` (a role per tenant) and a tenant switcher, keep platform staff in a separate table with its own guard, and decide in an ADR whether Filament's tenancy or `TenantContext` is authoritative, making sure the two can't disagree.

#### TEN-11 · Medium · Tenant lifecycle states have no defined behaviour (§4.2)
- **Issue:** Tenant resolution checks for Active, In-Arrears and Suspended, but nothing says what each state allows. A suspension in the middle of a trading day could stop every POS terminal, and tenants still need access to records they're legally required to keep.
- **Fix:** Define each state's effect: grace period, read-only mode, POS behaviour (finish the current shift, block new ones), export rights while suspended, and notifications before each change.

### 3.3 Module architecture

#### MOD-01 · High · Accounting depends on the operational modules (§5.3)
- **Issue:** The dependency graph has Accounting depending on Purchasing, Sales and Inventory. That rules out an accounting-only plan, and every change in an operational module ripples into the ledger.
- **Fix:** Make Accounting foundational. It exposes a posting contract (a journal posting API plus configurable posting rules), and Sales, Purchasing, Inventory and POS depend on it. Review POS → Sales as well if a POS-only plan is wanted.

#### MOD-02 · High · The module contract is incomplete (§5.1)
- **Issue:** `ModuleInterface` has no migrations, install/upgrade/uninstall hooks, default data seeding (chart-of-accounts templates, tax codes, roles), settings schema, Filament resource registration, policies, scheduled tasks, API routes or health checks. `isEnabledForTenant()` puts entitlement logic inside every module.
- **Fix:** Expand the contract and move entitlement checks into one central `ModuleEntitlementService`.

#### MOD-03 · Medium · Enable/disable rules are undefined (§5.2)
- **Issue:** Nothing says what happens when a tenant disables a module that other enabled modules depend on, or one with open documents (e.g. Inventory while purchase orders await receipt). The state diagram mixes platform states (Discovered, Published, Deprecated) with tenant states (Enabled, Suspended), and the "Disabled → Deprecated" transition has no clear meaning.
- **Fix:** Split it into a platform release lifecycle and a tenant subscription lifecycle. Block disabling while dependents are enabled or documents are open, and define read-only access to retained data.

#### MOD-04 · Medium · Per-tenant module versions aren't possible in one deployable (§5.1, §5.2)
- **Issue:** Every tenant runs the same code, so `getVersion()` can't differ per tenant, and staged rollout needs another mechanism.
- **Fix:** State that module versions are platform-wide. Use feature flags per tenant or plan (e.g. Laravel Pennant) for staged rollout, and make per-module data migrations idempotent.

#### MOD-05 · High · Banning cross-module joins blocks reporting (§5.4, §8 #10)
- **Issue:** Margin by category, inventory valuation against the GL and customer profitability all need data from several modules. Assembling them through DTOs is slow and impractical.
- **Fix:** Create a Reporting module with an explicit exemption: read-only SQL views or materialized views over other modules' tables, versioned with the schema. Run heavy reports on a read replica rather than the primary that serves the POS.

#### MOD-06 · High · GL posting is described as both sync and async (§5.4 vs §11.3, R-03)
- **Issue:** §5.4 says Accounting posts "asynchronously or within the same transaction scope", while §11.3 and R-03 assume atomic posting. Asynchronous posting creates windows where an invoice exists without its journal entry.
- **Fix:** Post journals synchronously in the same transaction for all financial documents. Use events only for side effects (notifications, webhooks, search indexing). Add a nightly subledger-to-GL reconciliation job as a safety net.

#### MOD-07 · Medium · The outbox isn't specified (§4.2, §5.4, ADR-005)
- **Issue:** The table is called `transactional_outbox` in one place and `outbox` in others, and has no schema. Not covered: concurrent pollers, ordering per aggregate, RLS on the outbox versus a cross-tenant poller, cleanup, and event versioning.
- **Fix:** Define the table (event ID, tenant, aggregate, event type, schema version, payload, status, attempts, next attempt). Poll with `FOR UPDATE SKIP LOCKED` under the platform role, and version event schemas, since external webhook consumers depend on them.

#### MOD-08 · Medium · A sandboxed plugin marketplace can't run inside the monolith (§1.3, Phase 3)
- **Issue:** Third-party PHP code loaded into the application has the same database access as first-party code, so it can't be sandboxed.
- **Fix:** Plan third-party extensions as out-of-process apps: an OAuth-scoped API, webhooks and UI extension points (e.g. embedded iframes). This shapes the API design now, even though the marketplace is Phase 3.

#### MOD-09 · Medium · Module boundaries rely on code review (ADR-001, §5.4)
- **Issue:** ADR-001 depends on "disciplined code reviews" to prevent cross-module access, and reviews miss things. Cross-module foreign keys (invoice items → product variants, for example) also tie module schemas together, which makes removing a module hard.
- **Fix:** Enforce boundaries in CI with architecture tests (Pest `arch()` rules or Deptrac), so each module exposes only a public `Contracts` namespace. Allow foreign keys only toward foundational modules (Core, Catalog, Accounting) and document the rule.

### 3.4 Accounting and financial integrity

#### ACC-01 · Critical · Nothing enforces balanced journal entries (§9.2, R-03)
- **Issue:** `chk_debit_or_credit` only checks that each line is either a debit or a credit. No constraint checks that total debits equal total credits per entry. R-03 cites a `chk_debit_equals_credit` constraint that doesn't exist.
- **Fix:** Add a deferred constraint trigger (`DEFERRABLE INITIALLY DEFERRED`) that rejects the commit if a posted entry doesn't balance. Keep an application-level check and a nightly trial-balance check as well.

#### ACC-02 · Critical · Immutability rules contradict the schema (ADR-003, §9.2)
- **Issue:** ADR-003 revokes `UPDATE` on `journal_entries`, but `is_posted` defaults to `FALSE`, so posting a draft is itself an update. `ON DELETE CASCADE` from `tenants` → `journal_entries` → `journal_lines` means deleting a tenant or a journal header silently removes posted ledger lines.
- **Fix:** Either keep drafts in separate tables, or allow updates only while `is_posted = false` through a trigger that raises on any change to a posted row. Replace `CASCADE` with `RESTRICT` on all financial and audit tables, and purge tenants only through the controlled offboarding procedure (TEN-06).

#### ACC-03 · High · No fiscal periods or period close (§7, §8)
- **Issue:** There's no fiscal year (Bangladesh's runs July–June), no accounting periods, no period locking, no year-end close and no opening-balance process. Without period locks, a backdated entry can change a VAT return that has already been filed.
- **Fix:** Add fiscal year and period tables with a status (open, soft-closed, closed), validate posting dates against period status, and add year-end closing entries and an opening-balance import.

#### ACC-04 · High · No transaction-level multi-currency despite the import focus (§1.3, §9.2)
- **Issue:** Importers buy in USD or CNY, pay through letters of credit and book FX gains and losses. The only multi-currency item in scope is Phase 3 consolidation, and `journal_lines` has no transaction currency, exchange rate or base-amount columns. Retrofitting these later means migrating every financial table.
- **Fix:** Add transaction currency, exchange rate, transaction amount and base amount to financial lines now. Support foreign-currency supplier bills and realized FX gain/loss in MVP; revaluation and consolidation can wait.

#### ACC-05 · High · Monetary math rules are incomplete (A-05, §9.2, §10.3)
- **Issue:** A-05 allows "integers or `DECIMAL(18,4)`"; the design needs to choose one. PHP has no native decimal type: Eloquent decimal casts return strings, and arithmetic on them falls back to floats. No rounding policy is stated. The webhook sends `"total_amount": 12500.0000` as a JSON number, which many consumers parse as a float.
- **Fix:** Standardize on `DECIMAL` in the database and a Money/Decimal value object in PHP (e.g. `brick/money` or `brick/math`). Document rounding: per-line vs. per-invoice VAT, the rounding mode, cash rounding to whole taka at the POS, and how 4-decimal internal values map to 2-decimal documents. Send money as strings in JSON.

#### ACC-06 · Medium · Receivables and payables are owned in four places (§8)
- **Issue:** AR/AP appear in Sales (#4), Purchasing (#6), "Customer & Supplier Ledgers" (#7) and Accounting (#8).
- **Fix:** Make one module own the AR/AP subledgers (open items, allocations, aging). Sales and Purchasing post to it, and it reconciles to GL control accounts.

#### ACC-07 · Medium · Document lifecycles and numbering go beyond tax invoices (§8, §9.2)
- **Issue:** Only invoice numbering is discussed. Quotations, orders, delivery notes, GRNs, transfers, payments and journals all need number series too, and each document type needs a defined state machine (draft, approved, posted, cancelled) with rules for what can be cancelled or reversed at each step.
- **Fix:** Build one number-series service (per company, branch and document type, with fiscal-year reset and prefixes) and define a state machine for each document type, including which transitions post to the ledger.

### 3.5 Inventory

#### INV-01 · High · No stock movement ledger (§9.2)
- **Issue:** Only `stock_balances` is defined. There's no immutable movement ledger to audit or rebuild balances from, no cost-layer table for FIFO, and no defined scope for weighted average cost (per warehouse or per company).
- **Fix:** Add `stock_movements` as the source of truth, with balances as a projection updated in the same transaction. Add `cost_layers` for FIFO, set the valuation scope per company, and reconcile the stock ledger to the GL inventory account.

#### INV-02 · High · The unique constraint allows duplicate stock rows (§9.2)
- **Issue:** `batch_number` is nullable, and PostgreSQL treats NULLs as distinct in unique constraints, so products without batches can end up with several balance rows for the same warehouse.
- **Fix:** Use `UNIQUE NULLS NOT DISTINCT (...)` (PostgreSQL 15+) or a non-null sentinel batch value.

#### INV-03 · Medium · Warehouses are modelled as children of branches (§8.1)
- **Issue:** Central distribution centres, shared warehouses and virtual locations (in transit, damaged, quarantine) don't fit this model, and there are no bin locations.
- **Fix:** Model warehouses at company level with an optional branch link, plus a location hierarchy inside each warehouse.

#### INV-04 · Medium · Reservations and negative stock are left open (§2.3 OQ-03, R-02, §13.3)
- **Issue:** `quantity_reserved` has no reservation records, expiry or release rules. The negative-stock policy is still an open question, and §13.3 puts "stock reservations" on a queue even though checkout needs them synchronously.
- **Fix:** Add a reservations table linked to the owning document, with expiry. Make the negative-stock policy a per-tenant setting with an exception workflow, and keep reservations synchronous.

#### INV-05 · High · Checkout will contend on hot rows and deadlock (§13.2, §16.2)
- **Issue:** Every sale locks the stock rows it touches. Fast-moving items such as carrier bags appear in most baskets, so checkouts queue behind one another, and two carts that lock the same items in a different order deadlock. Holding those locks while the same transaction posts journals and allocates an invoice number makes it worse, which puts the 500 ms checkout target at risk.
- **Fix:** Lock stock rows in a fixed order (sorted by ID), keep checkout transactions short, retry on deadlock and serialization errors (`DB::transaction()` with attempts), and load-test with realistic baskets that share hot items.

#### INV-06 · Medium · Stock counts during trading, FEFO and late landed costs (§8 #5, #6)
- **Issue:** Retailers count stock without closing, but there's no snapshot-and-adjust process, cycle counting or variance approval. Batch and expiry data exist without a first-expiry-first-out (FEFO) allocation rule. Landed costs that arrive after the goods have been sold need a cost adjustment to COGS, which isn't covered.
- **Fix:** Add count sessions that snapshot quantities and apply movements made during the count, cycle-count schedules, variance approval, a FEFO allocation policy, and landed-cost adjustment rules for stock that has already been sold.

### 3.6 POS and offline operation

#### POS-01 · Critical · Livewire can't run offline (§11.3, §17, ADR-004)
- **Issue:** The stack builds the POS with Blade, Livewire and Alpine. Livewire makes a server round trip for its interactions, so it stops working offline and slows down on poor connections, which is the opposite of what ADR-004 and the 200 ms scan target require.
- **Fix:** Build the POS as a client-side app (Alpine-only, or a light framework such as Vue or Svelte, with IndexedDB through a wrapper like Dexie) that calls the REST API. Keep Filament and Livewire for the back office. Record this as an ADR and plan for the JavaScript skills a PHP-focused team will need.

#### POS-02 · Critical · Idempotency can't protect offline sales (§10.2)
- **Issue:** Idempotency keys live only in Redis with a 24-hour TTL. A terminal that's offline over a long weekend or during an Eid outage syncs after its keys have expired, and Redis can evict keys under memory pressure anyway. The result is duplicated sales, revenue and stock deductions.
- **Fix:** Enforce idempotency in PostgreSQL with a unique key on `(tenant_id, terminal_id, client_txn_id)` on the sales table. Redis can remain a fast-path cache.

#### POS-03 · Critical · Gapless numbering vs. offline sales and throughput (§12.2, §11.3)
- **Issue:** §12.2 requires gapless sequential invoice numbers per financial year, but offline receipts carry provisional IDs, so the receipt in the customer's hand isn't the numbered tax invoice. PostgreSQL sequences aren't gapless, and a single locked counter serializes every checkout against the 10-per-second target.
- **Fix:** Use number series per branch or terminal (confirm NBR accepts this), issue pre-allocated number blocks to terminals for offline use, lock counters per series, and record voided numbers instead of skipping them. Align this with the fiscal-device flow in TAX-01.

#### POS-04 · High · Offline scope and conflict handling are undefined (§11.3)
- **Issue:** The design doesn't say:
  - whether credit sales, returns, manual discounts, MFS payments (which can't be verified offline) or customer lookups work offline;
  - how long a terminal may stay offline;
  - how price changes during a shift are handled;
  - how the catalog syncs incrementally for supermarkets with tens of thousands of SKUs;
  - how service-worker updates are applied mid-shift;
  - how sales data stored on shared terminals is protected.

  The sequence diagram also has the server "validate stock" on sync, but a sale that has already happened can't be rejected.
- **Fix:** Write an offline policy matrix (operation × allowed offline × limits). The server accepts every synced sale and turns conflicts (negative stock, price mismatch) into an exception queue for supervisors. Return a result per transaction, not per batch.

#### POS-05 · Medium · Browsers can't read MAC addresses (§8.1)
- **Issue:** `pos_terminals.mac_address` assumes a device identifier that no browser exposes.
- **Fix:** Add a device-registration flow that issues a revocable device token tied to the terminal record, plus quick cashier switching on a registered device (PIN or badge) and till sessions.

#### POS-06 · Medium · Hardware and printing assumptions (A-01, ADR-004, R-04)
- **Issue:** WebUSB and WebSerial only work in Chromium-based browsers, and on Windows they often can't claim a printer that's already using a vendor driver. Most ESC/POS printers have no Bangla font or text shaping, so Bangla sent as text prints incorrectly. Cash drawers, customer displays, weighing scales with price-embedded barcodes and label printers aren't covered.
- **Fix:** Make the local print bridge the primary printing path rather than the fallback, render Bangla receipts as raster images, and add the missing peripherals to scope.

#### POS-07 · High · Offline sign-in and session expiry (§7.1, §11.3)
- **Issue:** POS sessions time out after 15 idle minutes and Sanctum tokens can expire, but the design doesn't say how a cashier signs in while the terminal is offline, or what happens when a token expires during an outage. If it has expired by the time connectivity returns, the queued sales can't sync.
- **Fix:** Let registered devices verify cashier PINs against locally cached hashes, separate the device credential (long-lived, revocable) from cashier sessions, allow sync with the device credential after a cashier session ends, and define how manager overrides work offline.

#### POS-08 · High · Unsynced sales can be lost on the device (§11.3)
- **Issue:** Browsers can evict IndexedDB data under storage pressure unless the site has persistent storage, and staff can clear browser data. Power cuts and device failures add to the risk. Any of these destroys sales that haven't synced, along with their tax records.
- **Fix:** Request persistent storage (`navigator.storage.persist()`) and monitor quota, sync immediately whenever a connection is available, run terminals in a managed browser profile that staff can't clear, alert supervisors when unsynced sales pass an age threshold, and keep a printed or exported journal to reconstruct from.

#### POS-09 · High · Older terminals must still be able to sync (§11.3, §15.2)
- **Issue:** An offline terminal can run an old app version for days. If a release changes the sync payload, that terminal's queued sales fail when it reconnects. Back-office users with pages open also hit errors when assets change during a deploy.
- **Fix:** Version the sync API and keep accepting the previous one or two client versions, send the client version with every request, apply updates only at shift close, set a minimum supported version, and add contract tests between older clients and the current API.

#### POS-10 · Medium · Device clocks and sync storms (§11.3)
- **Issue:** Offline IDs and sale times come from the device clock, which can drift or be changed. After an area-wide outage, hundreds of terminals reconnect and sync at the same moment.
- **Fix:** Store both device time and server-received time, detect clock skew at sign-in, and keep device clocks synchronized. Sync with exponential backoff and jitter, and add server-side admission control for sync bursts.

#### POS-11 · Medium · The POS key map collides with browser keys (§11.4)
- **Issue:** The wireframe assigns F1–F12, but browsers use several of them: F5 reloads the page mid-sale, F11 toggles full screen, F12 opens developer tools, F3 opens find, F7 toggles caret browsing and F10 can open the menu bar. Barcode scanners type into whichever field has focus, so focus handling matters as well.
- **Fix:** Run the POS in kiosk or installed-app mode, intercept keys where the browser allows it, make the key map configurable, save the cart to IndexedDB on every change so a reload loses nothing, and route scanner input to the cart regardless of focus.

### 3.7 Bangladesh tax and regulation

#### TAX-01 · Critical · Fiscal devices are a current requirement (§2.3 OQ-01, §12.2)
- **Issue:** OQ-01 treats fiscal signing as something NBR might mandate in future. NBR has already made Electronic Fiscal Devices (EFDs) mandatory for 25 business categories, including shops, restaurants, clothing, furniture and electronics outlets and jewellers, and also recognizes Sales Data Controllers (SDCs) and properly integrated POS systems. Separately, a structured national e-invoicing mandate isn't live yet: the Finance Bill 2026 wording is enabling ("may be made mandatory") rather than an outright mandate.
- **Fix:** Add an EFD/SDC integration adapter to the checkout flow (fiscalize before the final print, queue while offline according to NBR rules). Research NBR's requirements for listed VAT software. Treat this as MVP scope for retail tenants in mandated categories. Track e-invoicing as a monitored item behind a pluggable adapter.

#### TAX-02 · High · VAT rates are incomplete and likely hard-coded (§8 #9, §11.4)
- **Issue:** The design lists 15%, 7.5%, 5% and exempt, but there's also a 10% reduced rate and a separate turnover-tax regime for smaller businesses. Published sources currently disagree on the turnover-tax thresholds and rate, which shows how often these change with each Finance Act. The POS wireframe also applies 5% VAT to the whole cart, including rice.
- **Fix:** Make the tax engine data-driven: effective-dated tax codes, supplementary duty, exempt and zero-rated items, and a per-company tax regime (VAT-registered vs. turnover-tax enlisted). Calculate VAT per line. Confirm current rates and thresholds with a Bangladesh VAT consultant.

#### TAX-03 · High · Mushak forms and VAT processes are missing (§12.2)
- **Issue:** Covered: Mushak 6.1, 6.2, 6.3, 6.7 and 6.8. Not covered: the monthly VAT return (9.1), the inter-branch transfer challan (6.5, needed for stock transfers), VAT-deducted-at-source certificates (6.6, common with corporate wholesale buyers), central vs. unit registration, and input-tax credit tracking. The "buyer BIN mandatory above BDT 25,000" rule in §12.2 has no source.
- **Fix:** Have a Bangladesh VAT consultant review the full list of forms and processes and verify every threshold the design states. Store a reference to the governing rule next to each tax setting.

#### TAX-04 · High · Import taxes aren't modelled (§1.3, §8 #6)
- **Issue:** Import & trading is a target segment, but landed cost covers none of the import tax components: customs duty, regulatory duty, supplementary duty, import VAT, advance tax (AT) and advance income tax (AIT). The 2025 Finance Ordinance proposed raising AT for commercial importers from 5% to 7.5%, so these rates move too. There's no LC management or bill-of-entry reference.
- **Fix:** Add an Import module or sub-module: LC tracking, bill of entry, duty components with the correct GL and input-credit treatment, and landed-cost allocation.

#### TAX-05 · High · The personal data protection law isn't addressed (§12)
- **Issue:** §12 covers OWASP and VAT only. Bangladesh's Personal Data Protection Ordinance was enacted in November 2025 and amended in 2026, including changes to its cross-border transfer and localization provisions. The platform stores customer phone numbers and addresses as well as staff data. The default `data_residency_region = 'bd-dhaka-1'` implies in-country hosting without saying where that is.
- **Fix:** Add a privacy section covering data classification, lawful basis and consent (especially for SMS marketing), data subject requests, retention and deletion, breach notification, and a data protection officer if required. Get a legal opinion on localization before choosing a hosting provider and region (OPS-01).

#### TAX-06 · Medium · Record retention isn't defined (§7.3, §13.4)
- **Issue:** Archiving and purge rules aren't tied to the statutory retention periods for VAT and accounting records.
- **Fix:** Confirm the periods with the tax advisor and use them to drive audit-log partition retention, tenant offboarding and backup retention.

#### TAX-07 · High · Tax-inclusive (MRP) pricing isn't supported (§8 #2, §11.4)
- **Issue:** Packaged consumer goods in Bangladesh carry a printed maximum retail price (MRP) that retailers may not exceed, and shelf prices are normally tax-inclusive. The wireframe adds VAT on top of the subtotal, and the catalog has no MRP field. Some goods may be taxed on a fixed or tariff value rather than the selling price.
- **Fix:** Add a tax-inclusive flag per price list with back-calculation and rounding rules, an MRP field with a check that blocks selling above it, and support for value-based tax rules once a VAT consultant confirms which items they apply to.

#### TAX-08 · Medium · "Global extensibility" has no localization architecture (Header, §1.3)
- **Issue:** The header promises global multi-tenant extensibility, but Bangladesh rules are wired into a single module, and ZATCA and Peppol are mentioned with no way to add them.
- **Fix:** Define a country-pack interface (tax engine, document formats, fiscal-device adapters, chart-of-accounts templates, statutory reports, number formats), with Bangladesh as the first pack, plus per-tenant locale, time zone and base currency. Each new market also brings its own privacy law (GDPR in the EU, for example).

### 3.8 Security

#### SEC-01 · High · No segregation of duties (§3.2, §7.4)
- **Issue:** Tenant Admin has full user and role management but only read access to the GL, so they can grant themselves the Accountant role. Nothing stops one user from both creating and approving a purchase order, or creating a supplier and then paying it. The matrix also omits the Sales Manager, Procurement Manager and Support Agent personas from §3.1, and "Rule-Restricted" isn't defined.
- **Fix:** Add a segregation-of-duties conflict matrix enforced at role assignment and approval time, block self-approval, alert the tenant owner on privileged role changes, and complete the permission matrix.

#### SEC-02 · High · Super-admin access to tenant data is only a footnote (§3.2)
- **Issue:** "Time-limited tenant support elevation tokens" are mentioned but not designed.
- **Fix:** Specify the flow: a tenant-controlled consent setting, reason code, time limit, read-only by default, a visible banner while impersonating, full audit, and a notification to the tenant owner.

#### SEC-03 · Medium · The audit log is tamper-resistant, not tamper-evident (§7.3)
- **Issue:** Revoking UPDATE and DELETE stops the application role, but a DBA or the owner role could still change rows without anyone noticing.
- **Fix:** Hash-chain audit rows per tenant (each row stores a hash of its content plus the previous row's hash) and anchor a daily digest outside the database, for example in object storage with object lock enabled. Partition the table by month.

#### SEC-04 · Medium · Secrets and key management (§12.1)
- **Issue:** One `APP_KEY` encrypts every tenant's payment-gateway and courier secrets, with no rotation plan. The document says Laravel uses AES-256-GCM natively; Laravel's default cipher is AES-256-CBC with an HMAC, and GCM has to be configured.
- **Fix:** Use envelope encryption with per-tenant data keys (through a KMS if the hosting provider has one), or at minimum document key rotation using Laravel's previous-keys support. Correct the cipher statement.

#### SEC-05 · Medium · Webhook security gaps (§10.3)
- **Issue:** The HMAC covers only the payload, so a captured webhook can be replayed. Tenant-supplied webhook URLs can be pointed at internal addresses (SSRF). Inbound webhooks from Shopify, WooCommerce and MFS providers have no verification design.
- **Fix:** Sign `timestamp + "." + payload` and reject stale timestamps. Block private and link-local IP ranges after DNS resolution. Allow two active secrets during rotation, and verify the signature of every inbound webhook.

#### SEC-06 · Medium · Authentication gaps (§7.1)
- **Issue:** TOTP is the only MFA method. There's no login throttling or cashier PIN lockout, no API token expiry or rotation, and no OAuth2 for the third-party apps planned in Phase 2.
- **Fix:** Add passkeys (WebAuthn) for admins, SMS OTP as a fallback (common locally, but budget for the SMS cost), throttling and lockout, token expiry, and an OAuth2 server before the public API launches.

#### SEC-07 · Medium · File storage isolation is undefined (§4.1)
- **Issue:** The "Tenant-Encrypted Vault" isn't specified.
- **Fix:** Use per-tenant key prefixes that users can't control, short-lived signed URLs, server-side encryption, malware scanning on upload, and per-tenant storage quotas tied to metering.

#### SEC-08 · Medium · Security testing is limited to isolation tests (§12.3, §15)
- **Issue:** There's no dependency scanning, static or dynamic analysis, or penetration test. §12.3 claims cross-tenant leakage is "mathematically impossible".
- **Fix:** Add `composer audit` and `npm audit`, static and dynamic scans in CI, and an external penetration test before the pilot. Replace the absolute claim with the specific guarantees that are tested.

#### SEC-09 · High · Branch and company scoping inside a tenant has no enforcement point (§7.2)
- **Issue:** RLS isolates tenants, but restricting a cashier to one branch, or a user to one company, depends entirely on application code. Every surface needs it: Filament tables, global search, relation managers, exports, reports, API endpoints and dashboards. Missing it on one surface exposes another branch's data.
- **Fix:** Implement one location-scope service applied as a global scope and in policies, cover every surface with tests, and consider a second RLS predicate on `branch_id` for the most sensitive tables.

#### SEC-10 · Medium · Browser-side security is missing (§12.1)
- **Issue:** §12.1 maps four OWASP Top 10 categories (A01, A02, A03, A05). There's no Content Security Policy, frame protection, XSS rules for custom HTML in Filament columns or Blade views, service-worker scope rules, or supply-chain controls for front-end packages.
- **Fix:** Map all ten categories of the current OWASP Top 10, add CSP and `frame-ancestors` headers, ban unescaped output in custom components, restrict the service worker's scope and cached routes, and lock and audit front-end dependencies.

### 3.9 Data architecture

#### DATA-01 · Medium · Random UUIDv4 keys on high-insert tables (§9.2)
- **Issue:** `gen_random_uuid()` produces random v4 UUIDs, which fragment B-tree indexes on fast-growing tables. Laravel's `HasUuids` generates time-ordered v7 UUIDs, so the database default and the application disagree.
- **Fix:** Use UUIDv7 everywhere (`uuidv7()` is built into PostgreSQL 18).

#### DATA-02 · Medium · The entity model is inconsistent and incomplete (§8.1, §9)
- **Issue:** §8.1 shows Tenant → Organization → Company, §9.1 shows Tenants → Companies, and the DDL in §9.2 references `company_id` with no companies table. There's no DDL for companies, branches, products, customers, suppliers, invoices, stock movements, the outbox, audit logs, idempotency keys, number series, fiscal periods or tax codes.
- **Fix:** Decide whether "Organization" exists as an entity, and add DDL for the core tables before Phase 1.

#### DATA-03 · Medium · No tenant customization model
- **Issue:** ERP tenants nearly always need custom fields and document layouts. The design has neither.
- **Fix:** Add JSONB custom-field definitions per entity, validated against a per-tenant schema and rendered in Filament forms. Schedule it for Phase 2 but reserve the columns now.

#### DATA-04 · Medium · No capacity model or partitioning plan (§13)
- **Issue:** There are no data-growth estimates, and audit logs and stock movements will outgrow everything else.
- **Fix:** Estimate rows per tenant per month for the largest tables, partition them by time, set archiving rules, and add a read replica before reporting load grows.

#### DATA-05 · Medium · Bangla search and sorting (§7.5)
- **Issue:** PostgreSQL full-text search has no Bangla dictionary, and users will search by Bangla names, English names and romanized Bangla.
- **Fix:** Use `pg_trgm` on normalized name columns, store transliterated search keys, and use ICU collations for Bangla sorting.

#### DATA-06 · Medium · Schema conventions are missing (§9.2)
- **Issue:** Status and type columns (`status`, `account_type`, `reference_type`) are free-text `VARCHAR` with no check constraints. Most tables lack `updated_at`, `created_by` and `updated_by`. There's no soft-delete policy, which matters for unique codes. Nothing protects against two users overwriting each other's edits. The polymorphic `reference_type`/`reference_id` pair has no integrity check. Journal lines lack the dimensions VAT and subledger reports need (customer or supplier, tax code, branch, line description), and `chart_of_accounts.is_reconciled` has no clear meaning on an account.
- **Fix:** Write schema conventions: enums or check constraints, audit columns, soft-delete rules with partial unique indexes, an optimistic-locking `lock_version` column, and integrity checks for polymorphic references. Add the missing journal-line dimensions and replace `is_reconciled` with an `is_reconcilable` flag.

#### DATA-07 · High · Business dates can land on the wrong day (§7.5, §9.2)
- **Issue:** Timestamps are stored in UTC, but `posting_date` is a `DATE`. Dhaka is UTC+6, so a date derived from a UTC timestamp puts every sale made between midnight and 6 a.m. local time on the previous day, which misdates invoices and VAT returns. Shifts that run past midnight also need a business-day rule.
- **Fix:** Derive the business date in the branch's time zone when each transaction is created and store it explicitly. Define a business-day cutover for shifts, and test around midnight.

#### DATA-08 · Medium · Master data isn't bilingual (§7.5)
- **Issue:** The UI is bilingual, but product, category and customer names are single fields. Receipts may need Bangla while e-commerce and reports need English, and some businesses want Bangla digits on receipts and invoices.
- **Fix:** Add Bangla and English name fields (or a translations table) for customer-facing master data, a language setting per document template, and a Bangla-digits option.

### 3.10 API and integration

#### API-01 · Medium · API contract details are missing (§10)
- **Issue:** No versioning or deprecation policy, pagination and filtering conventions, error format, rate-limit headers or machine-readable specification.
- **Fix:** Publish an OpenAPI spec as the contract, use RFC 9457 problem details for errors and cursor pagination, and write a deprecation policy.

#### API-02 · Medium · Local integrations are missing (§8 #11, §1.3)
- **Issue:** Only WooCommerce and Shopify are covered. Local retailers also need SMS gateways, courier services, MFS settlement reconciliation (fees and settlement timing) and Bangla QR, Bangladesh Bank's interoperable QR payment standard. Oversell between POS and online channels isn't addressed.
- **Fix:** Create an integrations backlog with one adapter interface per category. Schedule SMS for MVP (OTP and due reminders) and the rest per segment. Define how stock is allocated between POS and online channels.

#### API-03 · Medium · Idempotency semantics are incomplete (§10.2)
- **Issue:** Reusing an idempotency key with a different request body isn't handled, and a key's scope (tenant, endpoint, terminal) isn't defined.
- **Fix:** Store a hash of the request with each key and reject reuse with a different body (HTTP 422). Scope keys per tenant and endpoint, and document retry behaviour for API clients.

### 3.11 Infrastructure, cost and reliability

#### OPS-01 · High · The budget can't cover the stated commitments (C-01, §13.4, §17)
- **Issue:** $100–200 per month for development, staging and production has to pay for PostgreSQL with WAL archiving and point-in-time recovery, Redis, queue workers, object storage, logging, monitoring and backups, while the design promises RPO under 5 minutes, RTO under 30 minutes, zero-downtime deploys and an enterprise dedicated-database tier. There's no cost breakdown, and hosting is undecided ("Managed Containers / Cloud Run / VPS"). Cloud Run is a poor fit for long-running Horizon workers.
- **Fix:** Choose a provider and region (after the legal check in TAX-05) and produce a monthly bill of materials per environment. State what reliability the budget buys and what spend level unlocks the stated RPO and RTO.

#### OPS-02 · High · Single points of failure (§4.1, §13.4)
- **Issue:** One PostgreSQL primary with no standby; one Redis instance holding sessions, queues, rate limits and idempotency keys; one application node. Restoring from WAL won't meet a 30-minute RTO once the database grows.
- **Fix:** Use managed PostgreSQL with a standby (or a streaming replica), enable Redis persistence or use managed Redis, and write a failover runbook. Measure restore time in the quarterly drill instead of assuming it.

#### OPS-03 · Medium · Caching strategy (§13.2)
- **Issue:** Flushing a tenant's entire catalog tag on any single price edit invites cache stampedes, and the server-side barcode cache is mostly redundant if the POS looks barcodes up in IndexedDB.
- **Fix:** Invalidate per key, and push catalog changes to terminals through an event-driven delta sync.

#### OPS-04 · Medium · Queue fairness and rate limits (§13.3)
- **Issue:** Priority queues don't stop one tenant's bulk import from starving other tenants in the same queue. The Starter limit of 120 requests per minute can be reached by a few cashiers, since each Livewire interaction is a request.
- **Fix:** Add per-tenant concurrency limits for jobs (e.g. Redis funnels). Set separate limits for UI and API traffic, calibrated from load tests.

#### OPS-05 · Medium · Service levels are only partly defined (§2.2, §13)
- **Issue:** There's no availability target. The latency targets don't say where they're measured (on the server, or on a device over a mobile network to wherever hosting ends up), whether a fiscal-device call sits inside the checkout budget, or how the offline path is measured.
- **Fix:** Set availability targets per surface (POS sync API, back office, public API), define measurement points and error budgets, and include fiscalization in the checkout latency budget.

#### OPS-06 · Medium · Disaster recovery covers only PostgreSQL (§13.4)
- **Issue:** Uploaded files, generated documents, jobs held in Redis, secrets and configuration aren't covered. Backups kept with the same provider don't survive a provider-level outage or an account compromise.
- **Fix:** Enable object-storage versioning with an off-provider copy, make queued work recoverable (or re-creatable from the outbox), back up secrets and configuration, and include all of them in the restore drill.

### 3.12 Observability

#### OBS-01 · High · Logs only; no metrics, tracing or alerting (§14)
- **Issue:** The section covers structured logs and usage metering, but there's no error tracking, metrics, tracing, alerting, on-call process or dashboards for the p95 targets.
- **Fix:** Add error tracking (e.g. Sentry), metrics and dashboards, OpenTelemetry tracing, uptime checks, slow-query monitoring (`pg_stat_statements`) and alerts on queue depth and failed jobs. Add POS client telemetry (offline duration, sync backlog per terminal) and business-integrity monitors (unbalanced entries, subledger-to-GL drift, negative stock).

### 3.13 DevOps and testing

#### QA-01 · Medium · The concurrency test isn't concurrent (§16.2)
- **Issue:** The two requests run one after another in the same process, so the test passes without proving that locking works. It also reads a `stock_quantity` field on `ProductVariant` that the schema doesn't have; stock lives in `stock_balances`.
- **Fix:** Send truly parallel requests over separate database connections (parallel processes or a load-testing tool) against a real PostgreSQL database, and assert on `stock_balances` and `stock_movements`.

#### QA-02 · Medium · Missing test types (§16)
- **Issue:** No accounting-invariant tests, VAT golden tests, browser end-to-end tests for the POS (including offline), sync fuzzing, or migration tests on production-sized data.
- **Fix:** Add property-based tests (the trial balance always balances; the stock ledger always matches balances), VAT tests built from worked examples verified by a consultant, Playwright end-to-end tests with simulated offline mode, and the RLS coverage check from TEN-03.

#### QA-03 · Medium · Release engineering gaps (§15)
- **Issue:** No infrastructure as code, secrets management, zero-downtime migration practice (expand/contract), feature flags, staging data anonymization or release calendar.
- **Fix:** Add each of these. Avoid releases during peak trading (evenings, Eid and other festival seasons) and announce maintenance windows to tenants in advance.

### 3.14 Roadmap and delivery

#### PLAN-01 · High · The roadmap doesn't add up (§18)
- **Issue:** The chart is titled 24 weeks, but its bars add up to 29 (3 + 6 + 8 + 6 + 4 + 2). Phase 0 is marked done and Phase 1 active, even though the plan starts on 1 November 2026. Every task runs in sequence despite a 3–5 person team. Missing entirely: Reporting (#10), the Integration gateway (#11), Ledgers (#7), offline sync, the Super-Admin panel, subscription billing, onboarding and data import.
- **Fix:** Rebuild the plan with parallel workstreams, the missing items and correct statuses.

#### PLAN-02 · High · Sequencing causes rework (§18)
- **Issue:** POS checkout (weeks 10–12) is built before the accounting engine (weeks 18–19), yet checkout has to post journals atomically. Security and concurrency testing are left to the end. A 2-week pilot doesn't cover even one monthly VAT return cycle.
- **Fix:** Build the posting engine and tax engine before the POS. Run isolation and concurrency tests from the first sprint. Extend the pilot to at least 6–8 weeks so it covers a full VAT month and a month-end close.

#### PLAN-03 · High · Scope is aggressive for the team size (§1.3, §18)
- **Issue:** Eleven MVP modules, including double-entry GL, VAT, offline POS, multi-warehouse FIFO inventory and e-commerce sync, for 3–5 engineers in about seven months.
- **Fix:** Define explicit cut lines. A realistic MVP is POS, catalog, single-company inventory, AR/AP, GL and VAT for one retail segment, with multi-company, e-commerce sync and advanced reporting moved to Phase 2.

#### PLAN-04 · Medium · The risk register is incomplete (§19)
- **Issue:** Five risks are listed. Missing: key-person dependency in a small team, regulatory change (VAT rates, fiscal devices, data law), Laravel and Filament major upgrades, payment-gateway and SMS outages, customer data migration and adoption, hiring JavaScript skills for the POS (POS-01), dependence on a single provider, scope creep, peak load during Eid, and power cuts at outlets.
- **Fix:** Add these with owners, likelihood, impact and mitigation, and review the register monthly.

### 3.15 UI/UX

#### UX-01 · Medium · UI coverage is thin (§11)
- **Issue:** There are only two wireframes. Nothing covers tenant admin configuration, the Super-Admin panel, onboarding, field sales on low-end Android phones or approvals, and no actual design tokens are defined.
- **Fix:** Add wireframes for each layer's key journeys and define tokens (color, typography, spacing, contrast modes).

#### UX-02 · Medium · Bangla rendering and PDFs (§7.5, §11)
- **Issue:** Bangla conjuncts need proper text shaping. DomPDF, the usual PDF choice in Laravel projects, doesn't shape Bangla correctly, so invoices and Mushak forms would render wrongly.
- **Fix:** Generate PDFs with headless Chrome (e.g. Browsershot or Gotenberg) or with mPDF and shaping-capable fonts, and test Bangla on every document template and receipt.

#### UX-03 · Medium · Low-bandwidth optimization is claimed but not designed (§1.1)
- **Issue:** §1.1 lists low-bandwidth optimization as a priority, but there's no page-weight budget, Livewire payload strategy, image optimization or testing on slow connections.
- **Fix:** Set page-weight and request budgets for each layer, use lazy-loaded and deferred Livewire components, compress and cache assets, and test on throttled mobile connection profiles in CI.

#### UX-04 · Medium · No device and browser support matrix (§11, ADR-004)
- **Issue:** The POS depends on Chromium-only APIs, field sales targets mobile browsers, and warehouse staff typically use Android handheld scanners, but nothing states which devices, browsers and screen sizes are supported. The WCAG 2.2 AA claim has no test plan behind it.
- **Fix:** Publish a support matrix per layer, test on the lowest supported devices, and add accessibility testing (automated checks plus keyboard and screen-reader passes).

### 3.16 Core platform services

#### CORE-01 · Medium · No notification service (§7)
- **Issue:** The brief's list of core services included notifications, but §7 has none. OTPs, due reminders, approval requests, low-stock alerts and digital receipts all depend on one.
- **Fix:** Design a notification service with channels (email, SMS, in-app, later WhatsApp), Bangla and English templates, per-user preferences, delivery tracking and per-tenant SMS cost metering.

#### CORE-02 · Medium · No report and export engine (§8 #10)
- **Issue:** Large Excel and PDF exports generated inside a web request will time out or run out of memory, and there's no scheduling or storage for generated reports.
- **Fix:** Queue exports, stream and chunk rows, render PDFs on dedicated workers, store results with an expiry and notify the user when they're ready, and add scheduled reports.

#### CORE-03 · Medium · No settings hierarchy (§5.1, §7)
- **Issue:** Settings live at several levels (tenant, company, branch, terminal): receipt footers, tax regime, rounding, printer setup. There's no inheritance model.
- **Fix:** Use typed settings that inherit from tenant down to terminal with overrides at each level, audit every change, and invalidate caches when settings change.

#### CORE-04 · Medium · No real-time channel (§11.4)
- **Issue:** The dashboard shows a "real-time" branch leaderboard, terminals need catalog and price pushes, and notifications need live delivery, but the stack has no WebSocket or push mechanism.
- **Fix:** Add a real-time layer (for example Laravel Reverb, self-hosted to fit the budget) with tenant-scoped private channels, and use polling at defined intervals where real-time isn't needed.

### 3.17 Document governance

#### DOC-01 · Medium · No requirements baseline or traceability (Header, §20)
- **Issue:** The design says it's based on questionnaire responses, but the answers aren't recorded, functional and non-functional requirements are scattered across sections, and there's no glossary for terms such as Mushak, BIN, MFS, GRN, LC and AIT. No owner or approver is named.
- **Fix:** Add a requirements appendix (the questionnaire answers plus numbered functional and non-functional requirements) and link decisions and findings to it. Add a glossary, a named owner, approvers and a review cadence.

---

## 4. Functional gaps

Capabilities the target market needs that the design doesn't mention.

| Capability | Why it matters | Suggested phase |
|---|---|---|
| SaaS subscription billing | Plans, trials, invoicing tenants, local payment collection, dunning, proration, plan-limit enforcement and VAT on the platform's own fees. The Super-Admin panel promises billing, but nothing is designed. | MVP (before paid pilots) |
| Tenant onboarding wizard | Chart-of-accounts templates, tax setup, default roles, first branch and terminal, plus abuse controls (email and phone verification, rate limits) on self-service sign-up | MVP |
| Cash management | Opening float, cash drops and pickups, denomination counts, over/short posted to the GL | MVP |
| Pricing rules | Customer- and branch-specific prices with effective dates and a clear precedence order with promotions | MVP |
| Barcode and label generation | In-store codes for unbarcoded items, shelf and product labels | MVP |
| Training mode and sandbox tenants | Train cashiers without posting real sales; demo tenants for sales and support | Phase 1 |
| Digital receipts | SMS or link receipts for customers | Phase 2 |
| Data import and migration | Products, opening stock, opening balances and customers from Excel; migration from common local accounting tools | MVP |
| Units of measure | Carton/box/piece conversions, purchase vs. sales units, price per unit; essential in wholesale and distribution | MVP |
| Promotions and discounts | Buy-X-get-Y, combos, coupons, time-based prices | MVP (retail) |
| Customer credit ("baki") at POS | Credit sales, credit limits and due collection are routine in local retail | MVP |
| Cheque management | Post-dated cheques are common in wholesale | MVP |
| Withholding (VDS, TDS/AIT) | Corporate customers and supplier payments involve tax withheld at source | MVP |
| Weighing-scale barcodes | Price- or weight-embedded barcodes for supermarkets | MVP (supermarkets) |
| Expenses and petty cash | Daily branch expenses | MVP |
| Document templates | A4 invoices, challans, Mushak forms, Bangla PDFs | MVP |
| Approval engine design | Multi-level rules, delegation, escalation, SLAs | MVP (specification) |
| In-app help and support | Help content, support tickets, tenant announcements | Phase 1 |
| Loyalty and membership | Customer retention in retail | Phase 2 |
| Serial numbers and warranty | Electronics retail | Phase 2 |
| Delivery and courier integration | Challans, delivery status, cash-on-delivery reconciliation | Phase 2 |
| Custom fields | Per-tenant extensibility (DATA-03) | Phase 2 |
| Explicit out-of-scope list | State whether HR/payroll, manufacturing/BOM and CRM pipelines are excluded | Now |

---

## 5. Internal inconsistencies and document fixes

| Location | Problem | Fix |
|---|---|---|
| Header, §17 | "Filament v3/v4" | Name one version (TECH-02) |
| §4.1 diagram | Arrows point to `BusinessDomainModules`, which matches no defined node or subgraph ID, so Mermaid draws a separate empty node | Give the subgraph an ID and link to it |
| §4.2, §5.4, ADR-005 | `transactional_outbox` vs. `outbox` | One name and one schema (MOD-07) |
| §4.2 step 6 | Octane/FrankenPHP isn't in the stack | Decide (TECH-04) |
| §5.4 vs §11.3, R-03 | Async vs. atomic GL posting | Synchronous posting (MOD-06) |
| §5.2 | "Disabled → Deprecated" transition; platform and tenant states mixed | Two state machines (MOD-03) |
| §6.4 | Says the setting "resets to null"; it reverts to the previous session value (an empty string after first use), which is why `NULLIF` is needed | Correct the wording |
| §8.1 vs §9.1 vs §9.2 | Three different hierarchies; no companies table | DATA-02 |
| A-05 vs §9.2 | Integers or decimals | Decimals (ACC-05) |
| R-03 vs §9.2 | Cites a `chk_debit_equals_credit` constraint that doesn't exist | ACC-01 |
| ADR-003 vs §9.2 | UPDATE revoked, but `is_posted` must change; cascading deletes | ACC-02 |
| §12.3 Test Case 2 | Expects an FK violation that won't occur | TEN-02 |
| §12.1 | AES-256-GCM described as Laravel's native cipher | SEC-04 |
| §12.3 | "Mathematically impossible" | SEC-08 |
| §13.3 | Stock reservations placed on a queue | INV-04 |
| §16.2 | Non-concurrent test that reads a non-existent field | QA-01 |
| §18 | 24 vs. 29 weeks; Phase 0 marked done | PLAN-01 |
| §3.1 vs §3.2 | Personas missing from the permission matrix | SEC-01 |
| §8 #9 | 10% VAT rate missing | TAX-02 |
| §11.4 | VAT applied to the whole cart; "POS POS-Pay" typo; real brand names in mock data | Per-line VAT; fix the typo; use fictional products |
| §2.3 OQ-03, R-02 | Same open issue in two places, with no owner or due date | Give every open question an owner and a decision date |
| §20 | ADRs have no "Alternatives considered" section, which the original brief asked for | Add it to each ADR |
| §1.2 vs §3.1 | Layer numbers are reversed: §1.2 calls the Tenant User App layer 1, while the §3.1 diagram calls the Super-Admin plane layer 1 | Refer to layers by name |
| §1.3 vs §8 #3 | MFS APIs are Phase 2, but the MVP POS offers bKash/Nagad tender | State that MVP records MFS payments by reference only |
| Throughout | LaTeX math (`$p95 \le 200\text{ ms}$`) and escaped `\$` only render in some Markdown viewers | Use plain text (p95 ≤ 200 ms, $100) |
| §15.1 | "Staging Environment: Shared DB" is ambiguous | State that staging never shares the production database |
| §17 | "10x faster back-office development" has no supporting evidence | Remove it or back it up |
| §12.1 | Only four OWASP categories are mapped | SEC-10 |
| All diagrams | Mermaid syntax isn't validated, and one broken node reference was already found in §4.1 | Render every diagram in CI (e.g. with mermaid-cli) |

---

## 6. ADRs to add

1. Framework baseline: Laravel 13, Filament v5, PHP 8.4, PostgreSQL 17/18 (TECH-01 to TECH-03)
2. POS front-end technology: client-side app, not Livewire (POS-01)
3. Tenant context setting and connection pooling (TEN-01)
4. Database roles and cross-tenant access (TEN-03)
5. Hosting provider, region and data residency (OPS-01, TAX-05)
6. Primary key strategy: UUIDv7 (DATA-01)
7. Money representation and rounding policy (ACC-05)
8. Synchronous GL posting and the accounting posting contract (MOD-01, MOD-06)
9. Invoice numbering and fiscal device integration (POS-03, TAX-01)
10. Offline operation policy and conflict resolution (POS-04)
11. Module versioning and feature flags (MOD-04)
12. Tenant export, restore and offboarding (TEN-06)
13. Search strategy for Bangla and English (DATA-05)
14. PDF and receipt rendering (POS-06, UX-02)
15. User identity, tenant membership and Filament tenancy (TEN-10)
16. Sync API versioning and client update policy (POS-09)
17. Business date and time-zone rules (DATA-07)

---

## 7. Remediation plan

**Before development starts (design v1.1, about 2 weeks)**
- Resolve every Critical finding: TEN-01, TEN-02, ACC-01, ACC-02, POS-01, POS-02, POS-03, TAX-01.
- Settle TECH-01 and TECH-02 (versions), OPS-01 (hosting and budget), TAX-05 (legal opinion on data rules), TEN-10 (identity model), DATA-07 (business dates), and PLAN-01 to PLAN-04 (re-plan with cut lines and a complete risk register).
- Record the requirements baseline (DOC-01) and write the ADRs in section 6.
- Apply the documentation fixes in section 5.

**During Phase 1 (core platform)**
- TEN-03 to TEN-06, TEN-09, TEN-11, MOD-01 to MOD-06, MOD-09, ACC-03 to ACC-07, INV-01, INV-02, SEC-01, SEC-02, SEC-09, DATA-01, DATA-02, DATA-06, DATA-08, CORE-03, OBS-01, QA-01.

**Before Phase 2 (operations and POS)**
- POS-04 to POS-11, INV-03 to INV-06, TAX-02 to TAX-04, TAX-07, TAX-08, OPS-02 to OPS-05, API-01 to API-03, CORE-01, CORE-02, CORE-04, UX-01 to UX-04, TECH-03, TECH-04.

**Before the pilot**
- SEC-03 to SEC-08, SEC-10, QA-02, QA-03, TAX-06, OPS-06, DATA-03 to DATA-05, TEN-07, TEN-08, MOD-07, MOD-08.
- External penetration test and a restore drill with a measured RTO.

---

## 8. Questions for the product owner

1. Which retail segment goes first (supermarket, fashion, electronics, pharmacy)? The answer decides EFD/SDC scope, weighing-scale support and serial tracking.
2. Must MVP support turnover-tax businesses as well as VAT-registered ones?
3. How long may a terminal stay offline, and which operations are allowed while it is?
4. Is multi-company per tenant needed at launch, or can it wait for Phase 2?
5. Is in-country hosting a hard requirement, and what monthly spend is acceptable once paying tenants arrive?
6. Will NBR accept per-branch or per-terminal invoice series for your tenants?
7. Should there be POS-only and accounting-only plans?
8. How will pilot tenants pay, and does that need to work at launch?
9. Do external accountants, auditors or owners of several businesses need one login across tenants?
10. Are shelf prices tax-inclusive for your first segment, and which items are sold at a printed MRP?

---

## 9. Sources

Framework versions
- [Laravel 13 release notes](https://laravel.com/docs/13.x/releases.md)
- [Laravel News: Laravel 13 released, with support timeline](https://laravel-news.com/index.php/laravel-13-released)
- [Laravel News: What we know about Laravel 13 (Laravel 12 support dates)](https://laravel-news.com/index.php/laravel-13)
- [Filament: Introducing Filament v5](https://filamentphp.com/content/danharrin-filament-v5-blueprint)

Bangladesh fiscal devices and e-invoicing
- [The Financial Express: EFDs mandatory for 25 business types](https://mail.thefinancialexpress.com.bd/national/electronic-fiscal-devices-no-more-free-1633491738)
- [The Daily Star: NBR to outsource EFD maintenance](https://www.thedailystar.net/business/economy/news/nbr-outsource-maintenance-services-2933496)
- [EFD machine Bangladesh guide (SDC and POS integration)](https://rakibhassan.eu/?p=1695)
- [VATupdate: Bangladesh e-invoicing and e-reporting country booklet](https://www.vatupdate.com/2026/08/28/bangladesh-e-invoicing-e-reporting-country-booklet/)

Bangladesh VAT and import taxes
- [Kintsugi: Bangladesh VAT guide 2026](https://trykintsugi.com/sales-tax-guides/apac/bangladesh)
- [VAT rate Bangladesh FY2025–26 guide (differing turnover-tax figures)](https://rakibhassan.eu/vat-rate-bangladesh-2025-guide/)
- [KPMG: Bangladesh Finance Ordinance 2025 proposals](https://kpmg.com/us/en/taxnewsflash/news/2025/06/bangladesh-income-tax-vat-proposals-finance-ordinance-2025.html)

Bangladesh data protection
- [CCIA: Views on Bangladesh's Personal Data Protection Ordinance](https://ccianet.org/wp-content/uploads/2026/04/CCIA-Views-on-Bangladeshs-Personal-Data-Protection-Ordinance.pdf)
- [Jural Acuity: Personal Data Protection Ordinance overview (2026 amendment)](https://juralacuity.com/personal-data-protection-ordinance/)

Regulatory statements in this review are a starting point for the design team, not legal or tax advice. Confirm them with a Bangladesh VAT consultant and a lawyer before relying on them.

---

## 10. Revision history

| Version | Date | Changes |
|---|---|---|
| 1.0 | 26 Oct 2026 | First review: 70 findings (8 Critical, 27 High, 35 Medium) and 22 documentation fixes |
| 1.1 | 26 Oct 2026 | Second pass: 30 new findings (9 High, 21 Medium), 7 documentation fixes, 5 new functional gaps plus an expanded onboarding row, 3 new ADRs and 2 new product-owner questions; corrected the accounting week numbers in PLAN-02 |
