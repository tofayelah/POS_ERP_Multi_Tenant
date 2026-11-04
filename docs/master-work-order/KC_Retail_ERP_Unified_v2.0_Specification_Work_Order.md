# KC Retail ERP --- Unified v2.0 Specification

## Architecture Audit, Gap Analysis and Controlled Specification Workflow

**Document type:** Architecture audit and specification work order\
**Project:** KC Retail ERP\
**Target output:** A reviewed, internally consistent Unified ERP
Specification v2.0\
**Execution mode:** Read-only repository inspection and documentation
creation\
**Approval rule:** No application code, database schema, production data
or deployment changes are authorized by this document.

------------------------------------------------------------------------

## 1. Purpose

This work order explains exactly what the engineering agent must read,
inspect, compare, decide, document and validate to produce a unified
`v2.0` specification for KC Retail ERP.

The work is based on two project documents supplied by the product
owner:

1.  `erp-build-playbook-4.html` --- Retail ERP Build Playbook v1.5.0.
2.  `FULL_ERP_IMPLEMENTATION_REVIEW.md` --- implementation review
    v1.0.0.

The Playbook describes a staged 14-step build process, engineering
workflow, review gates, prompts, acceptance checklists and a 27-week
roadmap. It identifies a baseline of Laravel 13, PHP 8.4, Filament 5,
PostgreSQL 17, Alpine.js, Dexie.js, a Go hardware/fiscal bridge and
Antigravity-assisted development.

The Implementation Review describes the intended/currently claimed
implementation, including a modular monolith, tenant isolation with
PostgreSQL RLS, accounting, billing, catalog, inventory, sales,
procurement, tax, offline POS, hardware bridge, load-test metrics,
automated tests, ADRs and a pilot deployment.

**Important:** A statement in the Implementation Review is a claim to
verify, not proof by itself. Do not label a feature "verified
implemented" unless repository evidence and reproducible test or
operational evidence support that status.

The result must preserve useful requirements from both source documents,
explicitly resolve conflicts, identify missing information and separate
existing verified functionality from proposed future scope.

------------------------------------------------------------------------

## 2. Mission and success criteria

Produce one authoritative architecture specification and a set of
supporting documents that answer:

-   What is the approved product scope?
-   What does the Playbook require?
-   What does the Implementation Review claim?
-   What exists in the repository today?
-   What is implemented and verified?
-   What is implemented but not adequately verified?
-   What is partially implemented?
-   What is planned but not implemented?
-   What is missing from both source documents?
-   Which requirements conflict?
-   Which decisions require product-owner, accountant, VAT adviser,
    security or operations approval?
-   What should be built next, in what order, and with what acceptance
    criteria?

The work is complete only when:

1.  Both source files have been read in full.
2.  The repository has been inspected without modifying it.
3.  Every material requirement has a stable ID and traceable source.
4.  Conflicts and unresolved questions are recorded, not silently
    guessed.
5.  Current implementation status is evidence-based.
6.  Proposed additions are clearly labelled as recommendations.
7.  Architecture, security, data, accounting, tax, offline POS and
    operations concerns are covered.
8.  The roadmap includes dependencies, priorities, risks and acceptance
    criteria.
9.  All requested documents are created and cross-reference one another.
10. The agent stops and requests approval before implementation work.

------------------------------------------------------------------------

## 3. Non-negotiable safety and scope controls

### 3.1 Allowed work

The agent may:

-   Read the source documents and repository files.
-   Inspect Git status, branches, commit history and diffs.
-   Inspect migrations, models, services, policies, routes, resources,
    tests and configuration.
-   Run non-destructive read-only inspection commands.
-   Run existing tests only when the environment is safe and doing so
    cannot mutate shared or production data.
-   Review existing CI results and logs when available.
-   Create or update only the specification documents listed in Section
    16, preferably on a dedicated documentation branch if the owner
    approves that branch workflow.
-   Produce evidence references such as file paths, class names,
    migration names, test names, commit IDs and CI run identifiers.

### 3.2 Prohibited without separate explicit approval

The agent must not:

-   Modify application source code.
-   Create, alter, drop or run application database migrations.
-   Change database records or production data.
-   Run destructive commands, including database resets, volume
    deletion, truncation or destructive seeders.
-   Change tenant data or subscription status.
-   Change production or staging configuration.
-   Deploy, restart production services or change DNS.
-   Merge into `main` or another protected branch.
-   Change dependency versions or the approved technology stack.
-   Disable, weaken or delete tests, security policies, RLS rules or
    audit controls.
-   Add a new frontend framework or convert the modular monolith into
    microservices.
-   Claim regulatory approval or production readiness without supporting
    evidence.

If an inspection command might mutate state, do not run it. Explain the
risk and request approval.

### 3.3 Branch and working-tree protection

At the start:

1.  Record the current branch.
2.  Record `git status --short --branch`.
3.  Record the current commit hash.
4.  Confirm there are no uncommitted changes that could be overwritten.
5.  Do not switch branches, reset, stash, rebase or clean the working
    tree.
6.  If the tree is dirty, preserve it and report the exact status.
7.  If documentation changes need a separate branch, request approval
    rather than creating or switching branches unilaterally.

------------------------------------------------------------------------

## 4. Source documents and source-of-truth rules

### 4.1 Read these files completely

-   `erp-build-playbook-4.html`
-   `FULL_ERP_IMPLEMENTATION_REVIEW.md`

Do not rely on excerpts, summaries or headings only. Extract the
complete text, including tables, code examples, checklists, prompts,
roadmap, ADR references, implementation claims and caveats.

### 4.2 Read repository guidance

Inspect, if present:

-   `AGENTS.md`
-   `.agents/rules/`
-   `.agents/skills/`
-   `README.md`
-   `composer.json`
-   `package.json`
-   `phpunit.xml` or `pest.php`
-   CI workflow files
-   deployment and infrastructure documentation
-   `docs/` architecture, design, review and ADR documents

Do not assume a file exists. Record missing files as findings.

### 4.3 Precedence when sources disagree

Do not automatically assume the newer-looking document is correct. Apply
this process:

1.  Identify the exact conflicting statements.
2.  Record both sources and section/line references.
3.  Check the actual repository and test evidence.
4.  Determine whether the conflict is about intended design, current
    implementation, or a historical state.
5.  Recommend a resolution.
6.  Mark the decision as **Open --- Owner Approval Required** until
    approved.

The Implementation Review may describe implementation detail absent from
the Playbook. That detail can be retained as a candidate implementation
fact only after verification. The Playbook's approval gates and
engineering workflow must not be discarded simply because the review is
more implementation-oriented.

### 4.4 Evidence labels

Use these exact labels consistently:

-   **Verified implemented:** repository evidence plus reproducible
    passing test or operational evidence.
-   **Implemented, verification incomplete:** code exists, but adequate
    test/evidence is missing.
-   **Partially implemented:** only some required paths or behaviours
    exist.
-   **Planned:** documented requirement, no implementation evidence
    found.
-   **Proposed:** new recommendation not established by either source
    document.
-   **Blocked:** cannot proceed until a dependency or decision is
    resolved.
-   **Not applicable:** supported by a written reason.
-   **Unknown:** insufficient evidence; do not guess.

Every important implementation claim must include evidence: path,
symbol, migration, test, CI run, commit or operational record. If
evidence is unavailable, say so.

------------------------------------------------------------------------

## 5. Required inspection sequence

Follow this order. Do not skip ahead to feature design before
understanding the current system.

### Step 1 --- Establish repository baseline

Inspect and report:

-   Repository name and root.
-   Current branch and commit.
-   Working-tree status.
-   Main/protected branch names, if discoverable.
-   Runtime versions actually used by the repository and CI.
-   Framework and package versions from lock/config files.
-   Database engine and version configuration.
-   Frontend build system and entry points.
-   Deployment topology documented in the repository.
-   Existing environments: local, CI, development, staging, pilot,
    production.

**Deliverable evidence:** repository baseline table.

**Stop condition:** if the repository is not the expected KC Retail ERP
repository, or the current working tree cannot be safely preserved, stop
and report.

### Step 2 --- Extract the Playbook requirements

Read all 14 steps and capture:

-   Step number and name.
-   Goals.
-   Design sections referenced.
-   Review IDs.
-   Open gates and decisions.
-   Required code or documents.
-   Test-first prompts.
-   Human approval points.
-   "Done when" criteria.
-   Branch/tag guidance.
-   Dependencies and schedule assumptions.

Preserve the Playbook's terminology and sequence. Do not rewrite it as a
generic ERP roadmap.

At minimum, capture the Playbook's explicit areas:

1.  Platform baseline.
2.  Tenancy, roles and RLS.
3.  Identity and segregation of duties.
4.  Catalog, tax codes and price lists.
5.  General ledger.
6.  SaaS billing and onboarding.
7.  Inventory, stock movements and costing.
8.  Offline POS shell.
9.  VAT, invoices and Mushak.
10. Print and fiscal bridge.
11. AR, AP and business documents.
12. Offline synchronization.
13. Outbox, audit, import and QA.
14. Pilot and go-live.

Also capture its engineering process: plan first, approval, tests first,
implementation, server-side verification, independent review and release
gates.

### Step 3 --- Extract Implementation Review claims

Capture each claim about:

-   Architecture and module boundaries.
-   Technology versions.
-   Tenant model and RLS.
-   Database tables, migrations and constraints.
-   Core platform services.
-   Accounting and subledgers.
-   Billing and onboarding.
-   Catalog and pricing.
-   Inventory and costing.
-   Sales and procurement.
-   Tax and Mushak.
-   POS and offline storage.
-   Hardware/fiscal bridge.
-   APIs and sync semantics.
-   Load and concurrency testing.
-   Static analysis and automated tests.
-   ADR status.
-   Deployment, pilot tenants and operational checks.

For each claim, add a status field and evidence field. Do not copy a
claimed metric into the "verified" column without locating its source
output.

### Step 4 --- Inspect actual code and repository structure

Inspect the repository by domain, not just by filename.

#### Platform and tenancy

-   Tenant, company, branch, warehouse and terminal models.
-   Tenant context lifecycle.
-   Middleware and guards.
-   RLS SQL, policies and role grants.
-   Database connection setup and connection pooling.
-   Composite tenant/company foreign keys.
-   Background job tenant context propagation.
-   Cache, session, file and queue scoping.
-   Platform support access and audit trail.

#### Identity and security

-   Authentication and password policies.
-   MFA implementation, if any.
-   Roles, permissions and permission scopes.
-   Segregation of duties.
-   Session/device revocation.
-   API token lifecycle.
-   Rate limiting.
-   Support impersonation/elevation controls.
-   Secrets handling.
-   Audit logging.

#### Accounting and finance

-   Chart of accounts.
-   Fiscal years and accounting periods.
-   Journal headers and lines.
-   Posting service and transaction boundaries.
-   Journal immutability triggers.
-   Reversals and period close.
-   AR/AP subledgers and allocation.
-   Trial balance and financial reports.
-   Cash, bank and reconciliation.
-   Currency and exchange rate handling.
-   Inventory-to-GL reconciliation.
-   Tax control accounts.

#### Catalog and sales

-   Products, variants, attributes and barcodes.
-   Unit-of-measure conversion.
-   Price lists and customer pricing.
-   Discounts and approval limits.
-   Quotations, orders, delivery challans and invoices.
-   Returns, refunds, exchanges and credit notes.
-   Customer credit sales and limits.
-   Loyalty/promotion implementation, if any.

#### Inventory and procurement

-   Stock movement ledger and balance derivation.
-   Warehouse/location hierarchy.
-   Reservations and stock availability.
-   FIFO/AVCO implementation.
-   Cost layers and landed cost.
-   Stock counts and adjustments.
-   Transfers and transit.
-   Offline oversell exception process.
-   Supplier, purchase order, goods receipt and supplier bill.
-   Three-way matching and approvals.
-   Supplier returns and payment allocation.

#### Tax and compliance

-   Tax code and rate effective dates.
-   Tax calculation and rounding.
-   VAT inclusive/exclusive handling.
-   SD and VDS handling.
-   Mushak templates and data sources.
-   Credit/debit note relationships.
-   Tax period and return reporting.
-   Fiscal device/SDC integration evidence.
-   Configuration by taxpayer and business type.

#### POS and hardware

-   POS frontend entry point and build.
-   IndexedDB/Dexie schema and versioning.
-   Service worker and offline shell.
-   Terminal pairing and device revocation.
-   Cashier PIN and lockout.
-   Cart persistence and recovery.
-   Offline payment and credit policy.
-   Idempotency keys and sync endpoints.
-   Retry, quarantine and conflict resolution.
-   Receipt rendering and Bengali font support.
-   Printer, cash drawer and bridge authentication.
-   Bridge update, signing and certificate lifecycle.

#### SaaS and operations

-   Tenant provisioning and suspension.
-   Subscription plans and entitlements.
-   Quota enforcement and metering.
-   Billing and payment reconciliation.
-   Tenant export and offboarding.
-   Queue/outbox monitoring.
-   Logs, metrics, traces and alerting.
-   Backup and restore.
-   Disaster recovery.
-   Deployment and rollback.
-   Incident response.
-   Data retention and privacy controls.

### Step 5 --- Inspect tests and CI evidence

Identify and classify:

-   Unit tests.
-   Feature/API tests.
-   Database rule tests.
-   RLS isolation tests.
-   Concurrency tests.
-   Browser/E2E tests.
-   POS offline tests.
-   Hardware bridge tests.
-   Load tests.
-   Static analysis.
-   Dependency/security scans.
-   Migration tests.
-   Backup/restore tests.

For each test suite, record:

-   Test file and test names.
-   What requirement it verifies.
-   Whether it runs in CI.
-   Database role used.
-   Whether it uses real PostgreSQL where required.
-   Whether it tests allowed and forbidden paths.
-   Last observed run and result, if available.

A test count alone is not proof of adequate coverage. Map tests to
requirements and identify untested critical paths.

------------------------------------------------------------------------

## 6. Requirements traceability model

Create a master requirements register. Every requirement must have a
unique ID.

Suggested ID prefixes:

-   `PLAT` --- SaaS platform.
-   `TEN` --- tenancy and organization.
-   `IAM` --- identity and access.
-   `SEC` --- security.
-   `CAT` --- catalog and pricing.
-   `POS` --- point of sale.
-   `SAL` --- sales and invoicing.
-   `INV` --- inventory and warehouse.
-   `PUR` --- procurement.
-   `ACC` --- accounting.
-   `ARAP` --- receivables and payables.
-   `TAX` --- tax and compliance.
-   `IMP` --- import and international trade.
-   `CRM` --- customer relationship management.
-   `HR` --- HRM and payroll.
-   `ECOM` --- e-commerce.
-   `BI` --- analytics and reporting.
-   `WF` --- workflow and approvals.
-   `INT` --- integrations and APIs.
-   `OPS` --- operations and reliability.
-   `DOC` --- document management.
-   `QA` --- testing and quality.

For each requirement record:

  -----------------------------------------------------------------------
  Field                               Meaning
  ----------------------------------- -----------------------------------
  Requirement ID                      Stable unique identifier

  Requirement                         Clear, testable statement

  Source                              Playbook step/section, Review
                                      section, repository evidence or
                                      proposal

  Type                                Functional, non-functional,
                                      regulatory, operational

  Status                              One of the evidence labels in
                                      Section 4.4

  Priority                            P0, P1, P2 or P3

  Domain owner                        Responsible module/team

  Dependencies                        Other requirement IDs

  Evidence                            Paths, symbols, tests, CI runs or
                                      records

  Acceptance criteria                 Observable pass/fail conditions

  Open decision                       Approval required, if any
  -----------------------------------------------------------------------

Do not combine unrelated requirements into one vague row.

------------------------------------------------------------------------

## 7. Conflict and gap analysis

Build three separate analyses.

### 7.1 Source-to-source comparison

Compare the Playbook with the Implementation Review.

Identify:

-   Playbook requirements absent from the Review.
-   Review additions absent from the Playbook.
-   Differences in implementation detail.
-   Differences in status or timeline.
-   Conflicting technical decisions.
-   Conflicting module scope.
-   Claims that need proof.
-   Decisions that remain open.

### 7.2 Source-to-code comparison

For every requirement, determine whether code and tests support it.

Use the evidence labels. Do not infer completion from a class or table
name alone. Trace critical flows end to end.

### 7.3 Product gap analysis

Identify important enterprise requirements not sufficiently specified in
either source. Clearly label these as **Proposed**, not source-derived.

At minimum, evaluate the following candidate domains:

1.  Advanced CRM and customer 360.
2.  HRM, attendance, leave and payroll.
3.  Import and international trade.
4.  Multi-currency and foreign exchange.
5.  Advanced warehouse management.
6.  Advanced POS returns, exchanges and credit sales.
7.  Loyalty, promotion and vouchers.
8.  E-commerce and WooCommerce integration.
9.  Advanced procurement and supplier performance.
10. Configurable workflow and approval engine.
11. Fixed assets and budget management.
12. Business intelligence and forecasting.
13. Notification and communication centre.
14. SaaS subscription automation and customer support.
15. Document management.
16. API, webhook and integration management.
17. Data import/export and tenant portability.
18. Multi-company consolidation and intercompany transactions, if
    required.
19. Product batch, lot, serial and expiry tracking, where relevant.
20. Courier, payment gateway and MFS reconciliation.

For every proposed domain, specify business value, scope, dependencies,
risks, estimated relative effort and why it belongs in the roadmap.

------------------------------------------------------------------------

## 8. Target architecture decisions to document

Do not silently change the existing baseline. Document the current
choice, its rationale, evidence and any recommendation.

### 8.1 Application architecture

Preferred baseline to evaluate:

-   Modular monolith.
-   Explicit domain boundaries.
-   Domain services and contracts.
-   Events for cross-domain communication where appropriate.
-   Shared core limited to genuinely cross-cutting capabilities.
-   Separate POS edge application where required.
-   Go bridge as a separately versioned local hardware component.

Do not create one microservice per module by default. Recommend
extraction only when justified by independent scaling, deployment,
security or ownership needs.

### 8.2 Tenant and organization hierarchy

Document the intended hierarchy and scope of each record:

`Platform → Tenant → Company → Branch → Warehouse/Location → POS Terminal`

Clarify:

-   Which entities are tenant-wide.
-   Which are company-specific.
-   Which are branch-specific.
-   Which are warehouse-specific.
-   Which are terminal-specific.
-   Whether one tenant can contain multiple legal entities.
-   Whether financial books are separate per company.
-   Whether consolidated reports are required.
-   How intercompany transactions work, if in scope.

Every table must have a documented ownership/scope classification.

### 8.3 Database and transaction rules

Document:

-   Tenant isolation and RLS enforcement.
-   Database roles and ownership.
-   Composite foreign key conventions.
-   Tenant context for web requests, jobs, CLI commands and tests.
-   Transaction boundaries.
-   Financial posting immutability.
-   Stock ledger immutability.
-   Number series concurrency.
-   Idempotency.
-   Audit history.
-   Expand-and-contract migration policy.
-   Indexing and retention strategy.

### 8.4 Financial architecture

Document:

-   Base currency and transaction currencies.
-   Decimal precision and rounding rules.
-   Exchange-rate source and effective date.
-   Chart of accounts per company.
-   Fiscal year and period control.
-   Posting and reversal rules.
-   AR/AP open-item allocation.
-   Bank/cash reconciliation.
-   Cost centres and profit centres.
-   Inventory valuation.
-   Landed cost.
-   Tax posting.
-   Consolidation, if approved.

Never use binary floating-point arithmetic for monetary values.

### 8.5 Offline POS architecture

Document:

-   Device enrollment and revocation.
-   Local data classification and protection.
-   Offline authentication limitations.
-   Local storage lifecycle.
-   Cart and shift recovery.
-   Offline pricing and tax policy.
-   Payment tender states.
-   Offline returns and credit policy.
-   Idempotent transaction submission.
-   Duplicate prevention.
-   Clock skew.
-   Catalog versioning.
-   Conflict handling.
-   Oversell exception workflow.
-   Sync observability and supervisor reconciliation.

### 8.6 Integration architecture

Document:

-   API versioning.
-   Authentication and authorization.
-   Rate limits and quotas.
-   OpenAPI specification.
-   Webhook signing.
-   Retry and dead-letter handling.
-   Transactional outbox.
-   Idempotency.
-   Integration health dashboard.
-   Credential rotation.
-   External provider failure behaviour.

### 8.7 Security architecture

Evaluate:

-   MFA.
-   RBAC and scoped permissions.
-   Segregation of duties.
-   Session and device management.
-   Rate limiting and abuse prevention.
-   Secrets management.
-   Encryption in transit and at rest.
-   Sensitive field access.
-   Audit trail.
-   Support elevation.
-   File upload security.
-   Dependency and container scanning.
-   Security incident process.
-   Independent penetration testing.

### 8.8 Reliability and operations

Document:

-   Environment separation.
-   CI/CD gates.
-   Release and rollback.
-   Monitoring, logs, metrics and traces.
-   Queue and outbox monitoring.
-   Database capacity.
-   Backup frequency.
-   Restore testing.
-   RPO and RTO targets.
-   Disaster recovery.
-   Incident severity and escalation.
-   Data retention and deletion.
-   Tenant export and offboarding.

------------------------------------------------------------------------

## 9. Bangladesh-specific business and compliance assessment

Treat this section as a requirements assessment, not legal or tax
certification.

Evaluate and document:

-   BDT as default accounting currency.
-   Foreign currencies such as USD and RMB.
-   Exchange-rate capture and realised/unrealised FX differences.
-   Import PI, supplier invoice and purchase order.
-   LC and TT payment tracking.
-   Shipment, vessel, ETD and ETA.
-   Container sizes and CBM.
-   Freight, insurance, customs duty and other charges.
-   Landed cost allocation methods.
-   Customs and import document archive.
-   Wholesale, retail, distributor and customer-group price lists.
-   Branch-level operations.
-   Bengali and English document output.
-   Cash, card, bKash, Nagad and other supported tenders.
-   VAT, SD, VDS and Mushak workflows relevant to the taxpayer's actual
    business.
-   Tax invoice, credit/debit notes and tax-period reporting.
-   POS/fiscal-device requirements, where legally applicable.

For every statutory feature:

1.  Identify the applicable business/taxpayer category.
2.  Identify the official current legal or NBR source.
3.  Record the source date and effective date.
4.  Identify a qualified VAT/tax professional who must validate it.
5.  Separate configurable tax rules from hard-coded assumptions.
6.  Record whether official electronic integration is available,
    required, approved or merely proposed.

Do not claim that the ERP "conforms" or is "NBR approved" without
written, current, authoritative evidence.

------------------------------------------------------------------------

## 10. Module specification template

Use this template for every existing and proposed module.

### Module: \[Name\]

**Module ID:**\
**Business owner:**\
**Technical owner:**\
**Priority:** P0 / P1 / P2 / P3\
**Status:** Evidence label\
**Source:** Playbook / Review / Repository / Proposed

#### A. Business purpose

Describe the business problem and expected outcome.

#### B. Scope

List included features and explicitly excluded features.

#### C. Actors and permissions

List roles and the actions each may perform. Include tenant, company,
branch, warehouse and terminal scope.

#### D. Business workflows

Describe normal, exception, cancellation, approval, reversal and
recovery flows.

#### E. Data model

List entities, key fields, relationships, ownership scope, unique
constraints, indexes, foreign keys, retention and audit needs.

#### F. Services and domain boundaries

List services, contracts, events, jobs and cross-module dependencies.

#### G. User interface

List Filament resources/pages, POS screens, dashboards, filters, bulk
actions and printable documents.

#### H. APIs and integrations

List endpoints, payload requirements, authentication, idempotency,
webhooks, retries and error handling.

#### I. Accounting and tax impact

Describe journal postings, subledger impact, tax treatment and
reconciliation requirements. If no impact, state why.

#### J. Reports and KPIs

List reports, filters, totals, export formats and access restrictions.

#### K. Security and audit

List sensitive operations, approval requirements, audit events and
tenant-isolation tests.

#### L. Tests

List unit, feature, database, concurrency, browser, integration and load
tests as appropriate.

#### M. Acceptance criteria

Write observable, testable pass/fail conditions.

#### N. Dependencies and risks

List dependency IDs, migration risks, data migration needs and
unresolved decisions.

#### O. Delivery estimate

Estimate relative size (S/M/L/XL), assumptions and specialist
involvement. Do not invent a precise delivery date before scope and team
capacity are known.

------------------------------------------------------------------------

## 11. Cross-module workflows to validate

The specification must trace these end-to-end workflows, not merely list
screens.

### 11.1 Import-to-stock-to-sale-to-cash

Supplier PI → Import PO → LC/TT → Shipment → Customs and freight → Goods
receipt → Landed cost → Inventory valuation → Sales invoice →
POS/payment → GL → Bank reconciliation.

### 11.2 Purchase-to-pay

Purchase request → Supplier quotation → PO → Approval → Goods receipt →
Supplier bill → Three-way match → Payment approval → Payment → AP
settlement → GL.

### 11.3 Order-to-cash

Quotation → Sales order → Reservation → Delivery → Invoice →
Receipt/payment → AR allocation → GL → Customer statement.

### 11.4 Return and refund

Original sale → Return request → Approval → Physical receipt/inspection
→ Inventory disposition → Credit note/refund → Payment reversal or
refund → GL and tax adjustment.

### 11.5 Branch stock transfer

Transfer request → Approval → Dispatch → In-transit stock → Receiving →
Discrepancy handling → Destination stock posting → Accounting impact, if
applicable.

### 11.6 Offline POS

Catalog sync → Device pairing → Shift open → Offline sale → Local
durable storage → Network restoration → Idempotent sync →
Invoice/stock/GL posting → Exception queue → Reconciliation → Shift
close.

### 11.7 Month-end close

Subledger close → Inventory valuation → Bank reconciliation → AR/AP
reconciliation → Tax review → Accruals and depreciation → Trial balance
→ Financial statements → Period lock.

For every workflow, identify responsible roles, transaction boundaries,
failure recovery, audit events, reports and test scenarios.

------------------------------------------------------------------------

## 12. Priority and release classification

Use these priorities:

-   **P0 --- Critical:** Security, tenant isolation, financial
    correctness, data integrity, legal blocking requirement or
    production safety.
-   **P1 --- High:** Required for the first reliable target-business
    release.
-   **P2 --- Medium:** Important business capability that can follow the
    core release.
-   **P3 --- Optional/Future:** Advanced convenience, optimization or
    speculative capability.

Do not use priority as a substitute for evidence. A feature can be P0
and still be "planned" or "blocked."

Create release groups:

-   **Foundation:** tenant isolation, identity, audit, organization and
    core settings.
-   **Core ERP:** catalog, inventory, procurement, sales, accounting and
    applicable tax.
-   **Retail Edge:** POS, offline sync and hardware.
-   **Enterprise Extensions:** import, CRM, HRM, workflow, e-commerce
    and advanced finance.
-   **SaaS Scale:** subscription automation, customer support, API
    ecosystem, advanced analytics and operational automation.

The exact release order must follow dependency analysis and
product-owner approval.

------------------------------------------------------------------------

## 13. Required test and acceptance strategy

### 13.1 Tenant isolation

Test that one tenant cannot read, write, update, delete, export or infer
another tenant's data through:

-   Web UI.
-   API.
-   Background jobs.
-   Queues.
-   Reports.
-   Search.
-   File storage.
-   Exports.
-   CLI/maintenance commands.
-   POS sync.
-   Support tools.

Test both forbidden and permitted access paths using the intended
unprivileged database role.

### 13.2 Accounting correctness

Test:

-   Balanced journals post.
-   Unbalanced journals fail.
-   Closed-period postings fail.
-   Posted entries cannot be edited/deleted.
-   Reversal creates a traceable compensating entry.
-   AR/AP allocations reconcile to open items.
-   Subledger control totals reconcile to GL.
-   Inventory valuation reconciles to inventory GL.
-   Currency conversion and rounding follow approved rules.

### 13.3 Inventory correctness

Test:

-   Every movement is immutable.
-   Balances reconcile to movement ledger.
-   Concurrent sales cannot oversell online when strict availability is
    required.
-   Approved offline oversells enter the exception workflow.
-   FIFO/AVCO calculations are reproducible.
-   Transfers reconcile across dispatch, transit and receipt.
-   Landed cost adjustments update inventory/COGS correctly.

### 13.4 POS correctness

Test:

-   Scanner and manual product lookup.
-   Cart recovery after reload/crash.
-   Shift open/close and cash count.
-   Split tenders.
-   Discounts and approval limits.
-   Offline sale persistence.
-   Duplicate sync submission.
-   Partial batch failure.
-   Price/tax changes while offline.
-   Returns and refunds.
-   Device revocation.
-   Bengali receipt output.
-   Printer/bridge failure recovery.

### 13.5 Security and operations

Test:

-   MFA and session revocation where implemented.
-   Permission escalation attempts.
-   Support access expiry and read-only enforcement.
-   Rate limits.
-   File upload restrictions.
-   Secret exposure checks.
-   Dependency scanning.
-   Backup restore.
-   Deployment rollback.
-   Queue/outbox recovery.
-   Monitoring alerts.

### 13.6 Test evidence requirements

Record the exact command, environment, commit, timestamp and result. Do
not paste a claimed summary without the underlying CI/test run reference
when one is available.

------------------------------------------------------------------------

## 14. Open decisions register

Create a decision register with:

  -----------------------------------------------------------------------
  Field                               Required content
  ----------------------------------- -----------------------------------
  Decision ID                         `DEC-001`, `DEC-002`, etc.

  Question                            Precise decision needed

  Context                             Why it matters

  Options                             Realistic alternatives

  Recommendation                      Preferred option and rationale

  Impact                              Business, architecture, data, cost,
                                      compliance

  Owner                               Product owner / accountant / tax
                                      adviser / security / operations

  Status                              Open / Approved / Rejected /
                                      Deferred

  Approval evidence                   Date and recorded approval
  -----------------------------------------------------------------------

At minimum, investigate these questions:

1.  Is each tenant one legal entity or can it contain multiple
    companies?
2.  Are separate books and fiscal years required per company?
3.  Is tenant-level consolidated accounting required?
4.  Which import workflows are required at initial release?
5.  Which foreign currencies and rate sources are approved?
6.  What is the authoritative offline oversell policy?
7.  Which offline payment types are allowed, and how are they verified?
8.  Which returns/refunds are allowed offline?
9.  What happens to active POS shifts when a tenant is suspended?
10. Which VAT/Mushak workflows apply to the target taxpayer categories?
11. Is SDC/fiscal-device integration legally required for the target
    users?
12. Which subscription payment methods and billing rules apply?
13. What are the required RPO and RTO?
14. What data retention and tenant offboarding rules apply?
15. Which features are required for the first commercial release?

Do not decide these questions by assumption.

------------------------------------------------------------------------

## 15. Timeline and effort planning

The Playbook contains a 27-week roadmap dated 2 November 2026 to 9 May
2027. Treat those dates as source-document planning data, not as a
confirmed current schedule.

The Implementation Review reports a different implementation state and
pilot milestone. Reconcile this discrepancy using commit history, CI
evidence, deployment records and owner confirmation.

For the v2.0 roadmap:

-   Estimate effort by module and dependency.
-   Identify specialist roles required.
-   Include discovery and decision time.
-   Include data migration and test automation.
-   Include security review and UAT.
-   Include pilot feedback and remediation.
-   Include backup/restore and rollback validation.
-   Add contingency for statutory and third-party dependencies.

Do not promise a delivery date until the scope, team size, availability
and release criteria are approved.

------------------------------------------------------------------------

## 16. Required deliverables

Create the following Markdown files in the project's documentation area,
unless the owner specifies another location.

### 16.1 `ERP_MASTER_ARCHITECTURE_v2.0.md`

The authoritative document. Include:

-   Executive summary.
-   Product vision and target customers.
-   Scope and exclusions.
-   Architecture principles.
-   Technology baseline and version evidence.
-   System context and component architecture.
-   Tenant/company/branch hierarchy.
-   Domain boundaries and ownership.
-   Data architecture.
-   Security architecture.
-   Financial architecture.
-   POS/offline architecture.
-   Integration architecture.
-   Reporting architecture.
-   Deployment and operations architecture.
-   Cross-module workflows.
-   Non-functional requirements.
-   Open decisions.
-   Architecture diagrams (Mermaid where useful).
-   References to supporting documents.

### 16.2 `ERP_FEATURE_GAP_ANALYSIS.md`

Include:

-   Playbook vs Review comparison.
-   Source-to-code verification.
-   Missing requirements.
-   Partial implementations.
-   Conflicting requirements.
-   New recommendations.
-   Evidence for each status.
-   P0--P3 priority.
-   Risk and business impact.

### 16.3 `ERP_MODULE_SPECIFICATION.md`

Include a full specification for every current and proposed domain using
Section 10's template. Separate current scope from future optional
scope.

### 16.4 `ERP_DATABASE_ARCHITECTURE_REVIEW.md`

Include:

-   Entity inventory.
-   Tenant/company/branch/warehouse ownership.
-   Relationship and key conventions.
-   RLS policies and coverage.
-   Composite foreign keys.
-   Financial immutability.
-   Ledger design.
-   Index and query risks.
-   Data retention.
-   Migration safety.
-   Data migration and tenant export.
-   Schema gaps and recommendations.

Do not create migrations as part of this task.

### 16.5 `ERP_SECURITY_AND_COMPLIANCE_AUDIT.md`

Include:

-   Threat model.
-   Tenant isolation review.
-   Authentication and authorization.
-   Segregation of duties.
-   Audit logging.
-   Device/POS security.
-   API and webhook security.
-   Secrets and encryption.
-   File security.
-   Backup and recovery security.
-   Bangladesh VAT/compliance assessment.
-   Evidence gaps.
-   Required professional validation.

### 16.6 `ERP_IMPLEMENTATION_ROADMAP_v2.md`

Include:

-   Release groups.
-   Dependency graph.
-   Prioritized backlog.
-   Existing work to verify or complete.
-   New module sequencing.
-   Specialist dependencies.
-   Relative effort.
-   Test and review gates.
-   Pilot and production gates.
-   Explicit exclusions from each release.

### 16.7 `ERP_ACCEPTANCE_CRITERIA.md`

Include:

-   Global acceptance criteria.
-   Module-level criteria.
-   Tenant isolation criteria.
-   Accounting and inventory reconciliation criteria.
-   Offline POS criteria.
-   Tax and document criteria.
-   Performance criteria.
-   Security criteria.
-   Backup/restore and rollback criteria.
-   UAT sign-off requirements.

Every criterion must be objectively testable.

### 16.8 `ERP_ARCHITECTURE_DECISION_REGISTER.md`

Include:

-   Existing ADR inventory.
-   Whether each ADR is verified in code.
-   Superseded or conflicting ADRs.
-   New decision proposals.
-   Decision owner and status.
-   Approval evidence.
-   Consequences and migration considerations.

### 16.9 Optional supporting files

Create these only if useful and manageable:

-   `ERP_REQUIREMENTS_TRACEABILITY.csv` or `.md`
-   `ERP_OPEN_QUESTIONS.md`
-   `ERP_TEST_COVERAGE_MATRIX.md`
-   `ERP_PRODUCTION_READINESS_CHECKLIST.md`
-   `ERP_GLOSSARY.md`

The eight primary deliverables above are mandatory.

------------------------------------------------------------------------

## 17. Required diagrams

Include Mermaid diagrams where they clarify the design:

1.  System context.
2.  Tenant/company/branch hierarchy.
3.  Modular domain map.
4.  Core transaction and accounting flow.
5.  Import-to-landed-cost flow.
6.  Offline POS synchronization flow.
7.  Approval workflow.
8.  Deployment topology.
9.  Backup and disaster recovery flow.

Diagrams must reflect verified or explicitly proposed architecture.
Label proposed components as proposed.

------------------------------------------------------------------------

## 18. Quality review before submission

Before reporting completion, perform a documentation consistency review.

Check:

-   All source documents are represented.
-   No source requirement disappeared silently.
-   All Review claims have evidence status.
-   No proposed feature is described as already implemented.
-   Every P0/P1 item has an owner and acceptance criteria.
-   All open decisions are visible.
-   All module dependencies are consistent.
-   Database ownership and tenant scope are consistent.
-   Accounting workflows balance and reconcile conceptually.
-   Offline POS and inventory policies do not contradict each other.
-   Tax claims are appropriately qualified.
-   Roadmap estimates reflect dependencies and unknowns.
-   Technology versions match repository evidence or are marked unknown.
-   All documents use the same terminology and version.
-   No implementation or deployment change was made.

Use a second, independent review pass. The reviewer should challenge
assumptions, traceability, security boundaries, financial integrity and
missing failure scenarios.

------------------------------------------------------------------------

## 19. Final report format

When complete, provide a concise report containing:

### A. Repository baseline

-   Branch and commit.
-   Working-tree status.
-   Runtime and framework versions.
-   Environments found.

### B. Source comparison

-   Key Playbook requirements.
-   Key Review additions.
-   Important omissions.
-   Conflicts and version discrepancies.

### C. Verified implementation status

Summarize verified, incomplete, partial, planned, blocked and unknown
items.

### D. Critical findings

List P0 and P1 findings first, with evidence and recommended action.

### E. Proposed v2.0 scope

Separate source-derived scope from new recommendations.

### F. Open decisions

List decisions requiring owner, accountant, VAT adviser, security or
operations approval.

### G. Deliverables

List every file created and its location.

### H. Safety declaration

Explicitly confirm whether application code, migrations, production
data, deployment configuration, branches and merges were left unchanged.

### I. Approval request

Ask the product owner to review and approve the v2.0 specification
before any implementation begins.

------------------------------------------------------------------------

## 20. Final execution instruction

Begin with repository baseline and complete source-document reading.
Continue through the inspection sequence in this document.

Create the eight required specification documents only after evidence
collection and gap analysis.

**STOP after the documentation and independent review are complete.**

Do not implement new features, modify application code, create
migrations, change infrastructure, deploy, merge or alter production
data.

Present the findings and wait for explicit written approval for the next
phase.
