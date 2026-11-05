# KC ERP Control Plane: Comprehensive Acceptance Criteria & Test Matrix

**Document Reference:** `docs/platform-control-plane/CONTROL_PLANE_ACCEPTANCE_CRITERIA.md`  
**Application Target:** KC ERP Control Plane (`/platform`)  
**Environment:** Proxmox CT 104 (`dev.kcerp.com`), PostgreSQL 17.4, PHP 8.4  
**Design Authority:** Quality Assurance & Senior Architecture Gatekeepers  
**Scope Boundary:** Read-Only Verification Specification (Zero code mutations, Zero schema alterations)

---

## 1. Executive Quality Gates & Non-Negotiable Pass Criteria

Before any code modifying or extending the KC ERP Control Plane is merged, released, or deployed, it must satisfy **five mandatory non-negotiable verification gates**:

| Quality Gate | Tool / Command | Required Standard | Severity if Failed |
| :--- | :--- | :--- | :--- |
| **Code Style & Formatting** | `vendor/bin/pint --test` | 100% compliant with project conventions | **Rejection (Blocker)** |
| **Static Type Analysis** | `vendor/bin/phpstan analyse` | **Larastan Level 8**: Zero errors, strict typing | **Rejection (Blocker)** |
| **PostgreSQL RLS Integrity** | `php artisan db:check-rls` | 100% of tenant tables have Forced RLS enabled | **Rejection (Blocker)** |
| **Automated Test Suite** | `php artisan test` (Pest) | 100% passing tests on PostgreSQL 17 as `app_rw` | **Rejection (Blocker)** |
| **Zero Mock Telemetry** | Automated inspection | Zero fallback literals (`$mrr = 42500.0`, static charts) | **Rejection (Blocker)** |

---

## 2. Detailed Acceptance Test Matrix by Feature Area

### 2.1 Navigation & UI/UX Acceptance Criteria

| Criteria ID | Category | Requirement Description | Verification Method | Acceptance Condition |
| :--- | :--- | :--- | :--- | :--- |
| **AC-NAV-01** | Navigation | 6 Distinct Enterprise Navigation Groups render in the sidebar. | Pest Feature Test & Visual Inspection | Groups appear in exact order: SAAS MANAGEMENT, COMMERCIAL, PLATFORM OPERATIONS, SECURITY & GOVERNANCE, CUSTOMER SUCCESS, SYSTEM SETTINGS. |
| **AC-NAV-02** | Navigation | Navigation Badges display live dynamic counts. | Livewire / Filament Test | Badges dynamically reflect DB counts (e.g. `Active: N`, `74 Forced`, `Failed: 0`); badges update on record changes. |
| **AC-NAV-03** | Navigation | Collapsible sidebar state persistence. | Browser / Playwright Test | Collapsing a group persists in browser `localStorage` across page navigations and reloads. |
| **AC-NAV-04** | Navigation | Global Command Palette (`Ctrl+K` / `Cmd+K`). | Browser / Playwright Test | Pressing `Ctrl+K` opens modal palette searching across tenants, POS terminals, and invoices. |
| **AC-UI-01** | UI Layout | Fluid full-width desktop container (`max-w-none`). | Visual Inspection (1080p, 1440p) | Content expands across 100% of viewport; no dead lateral margins $> 64\text{px}$. |
| **AC-UI-02** | UI Layout | WCAG 2.1 AA/AAA Color Contrast Compliance. | Automated Lighthouse / axe-core | Contrast ratio between text and card backgrounds $\ge 4.5:1$ (normal) and $\ge 7:1$ (bold/headings). |
| **AC-UI-03** | UI Layout | Zero Raw Inline CSS in Blade Views. | Static Code Grep (`Select-String`) | Blade views in `resources/views/filament/platform/` contain zero `style="..."` attributes; 100% Tailwind CSS classes. |

---

### 2.2 Dashboard KPI & Telemetry Acceptance Criteria

| Criteria ID | Category | Requirement Description | Verification Method | Acceptance Condition |
| :--- | :--- | :--- | :--- | :--- |
| **AC-KPI-01** | Telemetry | Absolute elimination of hardcoded fallback numbers. | Static Code Audit & Pest Test | Code contains zero demonstration fallbacks (`$mrr = 42500.0`). In empty database, MRR renders `৳ 0`. |
| **AC-KPI-02** | Telemetry | Accurate MRR calculation using `brick/math`. | Pest Unit Test | Multiple subscriptions with monthly/annual intervals sum exactly without floating-point rounding errors. |
| **AC-KPI-03** | Telemetry | True historical trend sparklines. | Feature Test | Sparkline arrays are either populated with real daily counts or omitted cleanly; zero static dummy sequences (`[1, 2, 3, 4, 5]`). |
| **AC-KPI-04** | Telemetry | Dynamic Store Launchpad cards. | Feature Test | Cards query active tenants dynamically; adding a tenant automatically renders a new card or appears in paginated launchpad. |
| **AC-KPI-05** | Telemetry | Plan Distribution Donut Chart empty state. | Feature Test | Chart displays zero slices or empty state illustration when DB has no subscriptions; never fakes `3, 1, 2`. |

---

### 2.3 Security, RBAC & Support Impersonation Criteria

| Criteria ID | Category | Requirement Description | Verification Method | Acceptance Condition |
| :--- | :--- | :--- | :--- | :--- |
| **AC-SEC-01** | Isolation | Strict Guard & Session Separation. | Pest Integration Test | Authenticating into `platform` guard gives zero access to `/admin/{tenant}` routes; `EnsureTenantContext` throws `UnauthorizedException`. |
| **AC-SEC-02** | RBAC | 6-Tier Operator Role Enforcement. | Pest Feature Test | `support_engineer` cannot edit plans or view platform financial invoices. `billing_admin` cannot trigger system health actions. |
| **AC-SEC-03** | 2FA | Mandatory TOTP Two-Factor Authentication. | Pest Feature Test | Operator without 2FA confirmed is redirected to 2FA setup; cannot access dashboard until token confirmed. |
| **AC-SEC-04** | Support Access | Ticket-backed support impersonation token. | Pest Feature Test | Requesting access requires valid ticket reference; generates token with `expires_at = NOW() + 4 hours`. |
| **AC-SEC-05** | Support Access | PostgreSQL 4-hour hard ceiling constraint. | Database Constraint Test | Attempting to insert `expires_at > granted_at + 4 hours` triggers PostgreSQL `chk_support_access_max_four_hours` exception. |
| **AC-SEC-06** | Support Access | Read-Only enforcement during support session. | Pest Feature Test | HTTP `POST`, `PUT`, `DELETE` requests from active support session return `403 Forbidden` unless authorized write override exists. |
| **AC-SEC-07** | Support Access | Instant support session revocation. | Pest Feature Test | Clicking `Revoke` sets `revoked_at = NOW()`; subsequent requests immediately terminate session. |
| **AC-SEC-08** | Audit | Platform Audit Trail Immutability. | Database & Unit Test | All platform mutations generate append-only `platform_audit_logs` records; `UPDATE` and `DELETE` queries are blocked by DB triggers. |

---

### 2.4 System & Infrastructure Monitoring Criteria

| Criteria ID | Category | Requirement Description | Verification Method | Acceptance Condition |
| :--- | :--- | :--- | :--- | :--- |
| **AC-MON-01** | Monitoring | Live PostgreSQL 17 connection pool probe. | Telemetry Unit Test | Returns actual active/idle connections and round-trip latency in milliseconds. |
| **AC-MON-02** | Monitoring | Dynamic Forced RLS compliance probe. | Telemetry Unit Test | Verifies 100% of tenant tables in `information_schema` have `c.relforcerowsecurity = true`; detects any dropped policy immediately. |
| **AC-MON-03** | Monitoring | Live Redis 7 ping and memory telemetry. | Telemetry Unit Test | Returns `PONG`, memory MB usage, and peak memory. |
| **AC-MON-04** | Monitoring | Horizon queue latency & failed jobs counter. | Telemetry Unit Test | Queries `failed_jobs` table; reports zero failed jobs or exact error count. |
| **AC-MON-05** | Monitoring | Go POS Bridge HTTP health probe (`127.0.0.1:8181`).| Telemetry Integration Test | Sends real HTTP probe to Go bridge; returns `Online ({latency}ms)` or `Offline` if process stopped. |
| **AC-MON-06** | Monitoring | Invariant Watchdog execution & history. | Feature Test | Manual "Run Invariant Audit Now" button executes watchdog commands and records result in `invariant_audit_runs`. |
| **AC-MON-07** | Monitoring | Audit trail HMAC hash chain verification. | Unit Test | Cryptographically recalculates HMAC across last 100 entries; returns `HEALTHY` or flags exact break position. |

---

### 2.5 Commercial SaaS Billing & Subscription Criteria

| Criteria ID | Category | Requirement Description | Verification Method | Acceptance Condition |
| :--- | :--- | :--- | :--- | :--- |
| **AC-BIL-01** | Billing | Strict separation from tenant ERP accounting. | Database Schema Audit | `platform_invoices` table exists on shared schema; zero platform subscription entries exist in `sales_invoices` or tenant GL. |
| **AC-BIL-02** | Billing | Automated recurring invoice generation. | Scheduled Command Test | Running `platform:billing:cycle` generates next billing cycle invoice with unique sequential numbering. |
| **AC-BIL-03** | Billing | Automated dunning transition to `in_arrears`. | Feature Test | Unpaid invoices past grace period transition tenant subscription to `in_arrears`; badge turns warning color. |
| **AC-BIL-04** | Billing | Commercial SaaS invoice PDF generation. | PDF Feature Test | Generates clean BDT invoice PDF with company billing details, plan name, dates, and payment instructions. |

---

### 2.6 Fleet Management & Customer Success Criteria

| Criteria ID | Category | Requirement Description | Verification Method | Acceptance Condition |
| :--- | :--- | :--- | :--- | :--- |
| **AC-FLT-01** | Fleet | Cross-tenant Outlet Directory. | Feature Test | Lists physical branches across all tenants with tenant name, address, contact phone; respects read-only permissions. |
| **AC-FLT-02** | Fleet | POS Terminal Fleet Directory. | Feature Test | Displays registered Dexie PWA terminals, last sync timestamp, and open shift cashier name. |
| **AC-FLT-03** | Customer Success | Broadcast Maintenance Announcements. | Feature Test | Creating announcement with active date range injects banner into tenant ERP top bar and POS cashier shell. |

---

## 3. Automated Verification Execution Script

To execute the entire verification suite on the Proxmox dev server CT 104, the following sequence is executed via SSH:

```bash
# 1. Verify Code Formatting (Pint)
vendor/bin/pint --test

# 2. Verify Strict Static Analysis (Larastan Level 8)
vendor/bin/phpstan analyse --memory-limit=2G

# 3. Verify PostgreSQL 17 Forced RLS Coverage
php artisan db:check-rls

# 4. Execute Full Automated Test Suite (PostgreSQL 17 as app_rw)
php artisan test --filter=Platform
```

Every criteria item in this document must pass with **0 failures and 0 warnings** before production release approval can be granted.
