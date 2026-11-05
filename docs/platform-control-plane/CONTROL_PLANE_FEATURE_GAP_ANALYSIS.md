# KC ERP Control Plane: Feature Gap Analysis

**Document Reference:** `docs/platform-control-plane/CONTROL_PLANE_FEATURE_GAP_ANALYSIS.md`  
**Application Target:** KC ERP Control Plane (`/platform`)  
**Environment:** Proxmox CT 104 (`dev.kcerp.com`)  
**Baseline Review:** `CONTROL_PLANE_CURRENT_STATE_AUDIT.md`  
**Scope Boundary:** Read-Only Audit & Gap Analysis (Zero code mutations, Zero schema alterations)

---

## 1. Executive Context & Industry SaaS Benchmarking

To operate as a tier-1 multi-tenant enterprise Retail SaaS platform across Bangladesh and regional emerging markets, the **KC ERP Control Plane** must satisfy rigorous operational standards. The Control Plane is not merely an internal administrative dashboard; it is the command center responsible for:
- Guaranteeing cryptographic tenant isolation across hundreds of independent retail organizations.
- Managing high-volume recurring subscription revenue with compliant commercial invoicing.
- Providing real-time infrastructure and data integrity telemetry (PostgreSQL 17 RLS, Redis, Go Edge POS Bridges, NBR VAT compliance).
- Providing governed, auditable support access ("impersonation") that respects data privacy and financial sovereignty.

This document performs an exhaustive gap analysis comparing the current `/platform` implementation against commercial SaaS industry standards (e.g., Stripe Billing, Shopify Plus Admin, Datadog/CloudWatch operational dashboards, and enterprise multi-tenant ERP platforms).

---

## 2. Pillar-by-Pillar Gap Analysis

### 2.1 Pillar 1: Tenant Lifecycle & Multi-Tenant Fleet Management

| Capability | Enterprise SaaS Benchmark | Current Implementation in KC ERP | Gap Description & Severity |
| :--- | :--- | :--- | :--- |
| **Tenant Provisioning** | Automated self-serve and admin wizard provisioning tenant DB context, base settings, chart of accounts, and admin user. | `OnboardingWizardService` provisions tenant, default company, branch `BR-01`, POS terminal `T-01`, admin user. | **Minor Gap (Low):** Current wizard is functional but lacks multi-step progress bar and custom domain DNS verification. |
| **Tenant Suspension** | Instant lock on write operations; preserves read/export access; stores suspension reason, operator ID, and timestamp. | `TenantLifecycleService::suspend()` sets `status = 'suspended'`. Discards reason; no operator logging. | **Critical Gap (High):** Discards suspension reason; no platform audit trail; no notification to tenant owner. |
| **Tenant Reinstatement** | Restores write capabilities; validates past-due invoices; notifies tenant admin. | `TenantLifecycleService::reinstate()` sets `status = 'active'`. | **Moderate Gap (Medium):** Does not check overdue subscription invoices or enforce payment collection before unblocking. |
| **Cross-Tenant Outlet Fleet** | Consolidated dashboard showing all branches/outlets across all retail chains with geo-distribution. | Outlets exist strictly inside tenant schema (`branches`). Platform operators cannot view cross-tenant outlet counts without DB scripts. | **Major Gap (High):** Platform operators lack visibility into total physical stores running KC ERP. |
| **POS Terminal Fleet Directory** | Centralized device registry displaying active POS terminals, app versions, offline sync lag, and bridge health. | `PosTerminal` model exists in tenant context. Platform operators cannot inspect edge hardware fleet from `/platform`. | **Major Gap (High):** No fleet monitoring for POS terminals; impossible to detect hardware/sync issues remotely. |
| **Tenant Domain Management** | Automated SSL provisioning and DNS CNAME validation for custom white-label domains (`pos.clientbrand.com`). | Database supports `custom_domain` column on `tenants`, but no DNS challenge verification or automated reverse proxy routing. | **Moderate Gap (Medium):** Custom domains must be manually configured in Nginx/Caddy reverse proxy. |

---

### 2.2 Pillar 2: Commercial SaaS Billing, Subscriptions & Invoicing

> [!IMPORTANT]
> **Strict Separation of Concerns:** Control Plane SaaS Billing (charging tenants for software licensing) must remain strictly decoupled from tenant operational accounting (merchandise inventory, supplier bills, customer VAT sales). SaaS billing invoices and ledgers must reside in the shared platform domain, not within any individual tenant's general ledger.

| Capability | Enterprise SaaS Benchmark | Current Implementation in KC ERP | Gap Description & Severity |
| :--- | :--- | :--- | :--- |
| **Subscription Plan Definition** | Flexible tier configuration (branches, terminals, users, feature flags, billing cycles, pricing in local currency). | `Plan` model & `PlanResource` support `price_bdt`, intervals, quotas, and module toggles. | **Implemented & Verified (None):** Robust plan schema and Filament resource already operative. |
| **Subscription Lifecycle** | Trials, recurring billing cycles, automated renewals, grace periods, upgrades, and downgrades. | `TenantSubscription` tracks `starts_at`, `current_period_ends_at`, and `status`. | **Major Gap (High):** No automated renewal job; no prorated upgrade/downgrade calculations. |
| **SaaS Invoicing** | Automated PDF invoice generation on billing cycle renewal with tax breakdown and unique invoice numbering. | **Non-existent.** No `platform_invoices` table or invoice generator. | **Critical Gap (Critical):** Tenants are never billed or issued invoices for their SaaS subscriptions. |
| **Payment Collection & Gateways** | Multi-channel payment recording (bKash Merchant, Nagad, SSLCommerz, Bank Wire/EFT) with transaction reference matching. | **Non-existent.** No payment collection records or webhook receivers. | **Critical Gap (Critical):** Platform cannot record or reconcile subscription revenues from tenants. |
| **Automated Dunning & Arrears** | Multi-stage dunning workflow: renewal reminder (-3 days), due notice (day 0), grace period (+7 days), auto-suspension (+14 days). | Status values `'in_arrears'` exist in enum, but no automated schedule or dunning service transitions them. | **Major Gap (High):** Manual intervention required to suspend delinquent accounts; high risk of uncollected ARR. |
| **Usage-Based Overage Billing** | Metering extra terminals, branches, or high-volume transactions beyond plan limits. | Quotas exist on `Plan` (`max_branches`, `max_terminals`), but no metering or overage billing logic. | **Minor Gap (Low):** Overages are blocked at provisioning rather than billed dynamically. |

---

### 2.3 Pillar 3: Security, Governance & Support Access Protocol

| Capability | Enterprise SaaS Benchmark | Current Implementation in KC ERP | Gap Description & Severity |
| :--- | :--- | :--- | :--- |
| **Guard & Authentication Isolation** | Dedicated auth guard, dedicated user table, separate session cookies, and zero cross-context privilege leakage. | Dedicated `platform` guard, `platform_users` table, and `PlatformUser` authenticatable model. | **Implemented & Verified (None):** Excellent isolation between platform operators and tenant users. |
| **Platform RBAC** | Fine-grained roles (`super_admin`, `platform_admin`, `billing_admin`, `support_lead`, `support_engineer`, `auditor_ro`). | Single `role` string column in `platform_users`. Binary check `isSuperAdmin()`. | **Major Gap (High):** All non-super-admins have generic access; no separation between billing, support, and audit roles. |
| **Two-Factor Authentication (2FA)** | Mandatory TOTP / WebAuthn for all platform operators with emergency recovery codes. | Database columns exist in `platform_users` (`two_factor_secret`), but 2FA is not enabled or enforced in Filament. | **Major Gap (High):** High risk of unauthorized platform access via compromised operator passwords. |
| **Support Impersonation ("Login as Tenant")** | Governed, time-bound access requiring ticket ID, tenant admin authorization, read-only default, 4-hour hard ceiling, full audit trail. | Schema & model `TenantSupportAccess` exist with DB check constraint (`<= 4 hours`). **Zero UI or middleware**. | **Critical Gap (Critical):** Support personnel have no sanctioned, audited UI to access tenant stores for troubleshooting. |
| **Platform Audit Logging** | Tamper-evident logging of all platform actions (plan changes, tenant suspension, support access grants, user edits). | Tenant-level `audit_logs` exist for store data. **Zero platform-level audit logging table**. | **Critical Gap (Critical):** Platform operator mutations cannot be audited, attributed, or cryptographically verified. |
| **Session Security & Timeout** | Idle session expiration (15 mins), concurrent session limits, and sensitive action re-authentication. | Standard Laravel session lifetime (120 mins). No re-authentication on tenant suspension or plan deletion. | **Moderate Gap (Medium):** Sessions remain open excessively long; high risk on shared operator workstations. |

---

### 2.4 Pillar 4: Platform Operations & System Monitoring

| Capability | Enterprise SaaS Benchmark | Current Implementation in KC ERP | Gap Description & Severity |
| :--- | :--- | :--- | :--- |
| **Database & Forced RLS Monitor** | Real-time probe verifying PostgreSQL health, pool saturation, and ensuring 100% of tenant tables enforce RLS. | Static HTML card in `PlatformSystemHealthWidget` displaying hardcoded "68/68 tables" (actual count is 74). | **Critical Gap (Critical):** Misleading telemetry; fails to detect actual RLS degradation or table drift. |
| **Invariant Watchdog Telemetry** | Automated daily verification of GL trial balance, stock movements, VAT ledgers, and audit hash-chain integrity. | CLI command exists (`pilot:daily-check`). Dashboard card displays hardcoded `'100% Passed'`. | **Major Gap (High):** Operators cannot view watchdog run history, invariant discrepancies, or trigger on-demand audits from UI. |
| **Queue & Worker Monitoring** | Telemetry for Redis, Horizon queues, queue latency, job throughput, and failed job counts. | Static HTML card states "Session & Horizon active". Zero live metrics or failed job alerting. | **Major Gap (High):** Failed background jobs (e.g. document chains, offline POS syncs) remain undetected in UI. |
| **Go POS Bridge Health Monitor** | Real-time status of local hardware bridge (`127.0.0.1:8181`) for receipt printers, barcode scanners, and customer displays. | Static HTML card displays hardcoded "127.0.0.1:8181 active". | **Major Gap (High):** No active ping probe; hardware bridge failures are not signaled on the dashboard. |
| **Backup & PITR Validation** | Automated reporting on pg_dump / WAL archival freshness, snapshot timestamps, and recovery target objectives. | Static HTML card displays "RPO < 5m • RTO < 30m". No actual check of backup files or WAL replication lag. | **Major Gap (High):** Platform administrators cannot verify backup validity without SSH access to the host. |
| **Audit Hash-Chain Verification** | Cryptographic verification that SHA-256 HMAC chains on `audit_logs` have not been altered or broken. | CLI script exists. Dashboard displays static text "SHA-256 HMAC sealed". | **Moderate Gap (Medium):** Hash-chain verification must be surfaced as an automated operational widget. |

---

### 2.5 Pillar 5: Customer Success, Analytics & Fleet Telemetry

| Capability | Enterprise SaaS Benchmark | Current Implementation in KC ERP | Gap Description & Severity |
| :--- | :--- | :--- | :--- |
| **SaaS Business KPI Engine** | Real-time calculation of MRR, ARR, Churn, ARPU, LTV, Trial conversion, and collected revenue. | Hardcoded fallback `৳42,500` MRR in `PlatformStatsOverviewWidget`; static sparklines. | **Critical Gap (Critical):** Fabricated financial numbers; completely unsuitable for commercial reporting. |
| **Tenant User Directory** | Cross-tenant directory allowing operators to view store managers, cashiers, and login activity. | Users exist inside tenant context. No platform directory or security event log. | **Moderate Gap (Medium):** Support cannot quickly look up tenant contact persons or verify user identity. |
| **Platform Announcements & Maintenance Banners** | Global broadcast system displaying maintenance windows, feature updates, or urgent alerts inside tenant ERP & POS. | **Non-existent.** | **Moderate Gap (Medium):** No mechanism to notify cashiers and store admins of scheduled downtime. |
| **Tenant Usage Telemetry** | Tracking transaction volume, invoice generation rate, POS shift counts, and active daily users per tenant. | **Non-existent.** Telemetry must be queried directly via SQL. | **Moderate Gap (Medium):** Cannot identify high-growth tenants or at-risk inactive tenants. |

---

### 2.6 Pillar 6: UI/UX & Desktop Ergonomics

| Capability | Enterprise SaaS Benchmark | Current Implementation in KC ERP | Gap Description & Severity |
| :--- | :--- | :--- | :--- |
| **Screen Space Utilization** | Full-width responsive layout maximizing desktop real estate (1080p, 1440p, 4K) with dynamic multi-column CSS grid. | Narrow container with large lateral empty voids; fixed widths; severe wasted viewport space. | **Major Gap (High):** Poor desktop ergonomics for operations and NOC personnel monitoring many metrics. |
| **Sidebar Navigation Hierarchy** | Multi-level grouped navigation categorizing operational domains with badges, counters, and collapsible sections. | Flat list of 4 items (`Dashboard`, `Plans`, `Subscriptions`, `Tenants`). | **Major Gap (High):** Navigation does not scale as new management tools and monitors are added. |
| **Table Functionality** | Column toggles, density controls, multi-column sorting, advanced filters, export, and search debounce. | Basic Filament table in `PlatformTenantsTableWidget`. Lacks plan filter, status multi-select, and outlet counters. | **Moderate Gap (Medium):** Difficult to navigate large tenant registries efficiently. |
| **Widget Reusability & Standards** | Dynamic widgets with auto-refresh (Livewire polling), loading skeletons, accessible contrast, and zero inline CSS. | Widgets use raw inline styles (`style="display: grid; ..."`), hardcoded cards, and lack loading skeletons. | **Moderate Gap (Medium):** Violates Filament design system conventions and maintainability standards. |

---

## 3. Comprehensive Feature Gap Matrix & Prioritization

| Gap ID | Area | Feature Description | Severity | Complexity | Priority |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **GAP-01** | Telemetry | Remove hardcoded MRR (`৳42,500`), fake charts, and static health HTML | **Critical** | Low | **Immediate (P0)** |
| **GAP-02** | Telemetry | Build live System Health Probe Engine (PostgreSQL 17, 74 RLS tables, Redis, Bridge) | **Critical** | Medium | **Phase 1 (P1)** |
| **GAP-03** | UI/UX | Re-architect Navigation into 6 Enterprise Groups with badges | **High** | Low | **Phase 1 (P1)** |
| **GAP-04** | UI/UX | Overhaul Dashboard to Full-Width Responsive CSS Grid (1080p/1440p optimized) | **High** | Medium | **Phase 1 (P1)** |
| **GAP-05** | Security | Operationalize Support Impersonation Protocol (`TenantSupportAccess` UI & middleware) | **Critical** | High | **Phase 2 (P1)** |
| **GAP-06** | Security | Implement Platform Audit Logging (`platform_audit_logs` schema, listener, UI) | **Critical** | Medium | **Phase 2 (P1)** |
| **GAP-07** | Security | Enforce Platform Operator 2FA (TOTP) and 6-Tier RBAC Policies | **High** | Medium | **Phase 2 (P1)** |
| **GAP-08** | Commercial | Create Platform SaaS Invoicing Schema (`platform_invoices`) and PDF Generator | **Critical** | High | **Phase 3 (P2)** |
| **GAP-09** | Commercial | Implement Subscription Payment Collection & Dunning Workflow (In-Arrears rules) | **High** | High | **Phase 3 (P2)** |
| **GAP-10** | Operations | Surface Invariant Watchdog Interactive Dashboard & Test Runner | **High** | Medium | **Phase 3 (P2)** |
| **GAP-11** | SaaS Fleet | Build Cross-Tenant Retail Outlet & POS Terminal Fleet Directory | **High** | Medium | **Phase 4 (P2)** |
| **GAP-12** | Operations | Implement Horizon Queue Backlog Monitor & Failed Jobs Retry Action | **Medium** | Low | **Phase 4 (P2)** |
| **GAP-13** | Customer Success | Implement Platform System Announcements & Maintenance Banner System | **Medium** | Low | **Phase 5 (P3)** |
| **GAP-14** | Customer Success | Implement Tenant Usage Telemetry & Health Scorecard | **Medium** | Medium | **Phase 5 (P3)** |

---

## 4. Reusable Existing Assets & Architecture

The KC ERP codebase contains several robust enterprise components that can be reused directly to close these gaps without introducing new frameworks or redundant libraries:

1. **`OnboardingWizardServiceContract` & Implementation:**  
   Already provisions legal companies, branches, POS terminals, and initial administrator credentials atomically. Reusable for enhanced tenant onboarding.
2. **`TenantLifecycleServiceContract` & Implementation:**  
   Already implements state checks (`isActive`, `isInArrears`, `isSuspended`, `assertCanOpenShift`). Reusable for automated dunning and reinstatement.
3. **`TenantSupportAccess` Model & Database Constraint:**  
   Database table `tenant_support_accesses` and CHECK constraint `chk_support_access_max_four_hours` are already committed and migrated. Reusable to power the support impersonation UI.
4. **`brick/math` Library:**  
   Already configured and mandated across all financial code. Reusable for commercial SaaS invoice calculations and prorated subscription fees.
5. **Filament 5 Blade Component Ecosystem:**  
   Filament native components (`<x-filament::section>`, `<x-filament::badge>`, `<x-filament::button>`, `<x-filament-tables::table>`) provide accessible dark-mode styling out of the box, replacing brittle inline CSS.
6. **`HealthCheckController::ready()` Logic:**  
   PostgreSQL connection and Redis cache verification logic can be refactored into a reusable `PlatformTelemetryService`.
7. **`PilotDailyCheckCommand` Invariant Logic:**  
   The invariant checks (Trial Balance net-zero, VAT consistency, stock balance non-negative) can be scheduled and reported directly into the Control Plane.

---

## 5. Architectural Guidance & Constraints

1. **No Frontend Framework Bleed:**  
   Do not introduce Vue, React, or alternative SPA frameworks. The Control Plane must remain strictly built on **Filament 5, Livewire 4, and Tailwind CSS** within the existing Laravel 13 monolith.
2. **Strict Financial Separation:**  
   Platform subscription invoices must never be recorded in `sales_invoices` or posted to a tenant's general ledger. They must belong to the dedicated `platform_invoices` ledger on the shared schema.
3. **Fail-Closed Security Posture:**  
   If a platform operator initiates support access into a tenant store, tenant context `set_config('app.current_tenant_id', ...)` must still be strictly enforced, and session privileges must default to **Read-Only** unless explicit, time-stamped manager override is recorded.
