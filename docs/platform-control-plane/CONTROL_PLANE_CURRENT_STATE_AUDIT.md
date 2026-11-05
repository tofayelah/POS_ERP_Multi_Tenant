# KC ERP Control Plane: Current State Implementation Audit

**Document Reference:** `docs/platform-control-plane/CONTROL_PLANE_CURRENT_STATE_AUDIT.md`  
**Application Target:** KC ERP Control Plane (`/platform`)  
**Environment:** Proxmox CT 104 (`dev.kcerp.com`), Port 8080 (Reverse Proxy HTTPS)  
**Technology Stack:** Laravel 13, PHP 8.4, Filament 5, PostgreSQL 17.4, Redis 7, Alpine.js / Tailwind CSS  
**Audit Date:** 2026-11-05  
**Audit Authority:** Senior Architecture & UI/UX Audit Team  
**Scope Boundary:** Read-Only Audit & Specification (Zero code mutations, Zero schema alterations)

---

## 1. Executive Summary

The **KC ERP Control Plane** (`/platform`) serves as the administrative backbone and SaaS orchestration layer for the multi-tenant KC Retail ERP ecosystem. While the tenant-facing application (`/admin/{tenant}`) provides store-level Enterprise Resource Planning (double-entry general ledger, inventory AVCO valuation, POS operations, and NBR Mushak statutory compliance), the Control Plane is designed to oversee multi-tenant isolation, subscription lifecycles, platform-wide infrastructure telemetry, and fleet-wide POS terminal connectivity.

This independent audit conducts an exhaustive inspection of the actual codebase, route declarations, Filament panel providers, Eloquent models, Blade templates, and visual screenshots (`media_1793690492768_02ad553b.png`, `media_1793685327602_ed74bae1.png`, `media_1793687586098_b558ab4e.png`, `media_1793689285424_2fefa04c.png`).

### Key Audit Verdict
The existing Control Plane demonstrates **sound structural foundation** with strict authentication guard isolation (`platform` guard and `platform_users` database table) and high-level tenant lifecycle management. However, the current implementation is **functionally incomplete and visually underdeveloped** for a commercial enterprise SaaS product:
1. **Critical Demonstration Fallbacks:** Key KPI widgets and telemetry dashboards contain hardcoded values (e.g. fallback `৳42,500` MRR, static chart arrays, and hardcoded `68/68 tables` RLS telemetry).
2. **Missing Commercial SaaS Billing:** The platform allows editing subscription tiers and tenant assignments, but lacks SaaS billing ledgers, recurring subscription invoices, payment gateway reconciliation, and automated dunning.
3. **Flat, Unsegmented Navigation:** All operations are compressed into four flat navigation links (`Dashboard`, `Plans`, `Subscriptions`, `Tenants`), lacking enterprise grouping for operations, security, telemetry, and customer success.
4. **Desktop Screen Space Wastage:** Layout constraints leave large empty voids on modern desktop viewports (1080p, 1440p, 4K), lacking full-width responsive grid designs.
5. **Unintegrated Support Protocol:** While a database table and model for `TenantSupportAccess` exist with a strict 4-hour database constraint, there is zero UI in Filament for requesting, authorizing, or conducting time-bound support impersonation.

---

## 2. Codebase & Architectural Inspection

### 2.1 Panel Configuration & Provider Architecture
- **Source File:** `app/Providers/Filament/PlatformPanelProvider.php`
- **Panel ID:** `platform`
- **Path Route:** `/platform`
- **Authentication Guard:** `platform`
- **Primary Palette:** `Color::Amber` (Hex `#F59E0B`)
- **Branding:** `KC ERP Control Plane`
- **Registered Pages:** `Filament\Pages\Dashboard::class`
- **Registered Widgets:**
  - `Filament\Widgets\AccountWidget::class` (Filament default user greeting)
  - Discovered via `app/Filament/Platform/Widgets/`
- **Middleware Pipeline:**
  - Standard Laravel web security pipeline: `EncryptCookies`, `AddQueuedCookiesToResponse`, `StartSession`, `AuthenticateSession`, `ShareErrorsFromSession`, `PreventRequestForgery`, `SubstituteBindings`, `DisableBladeIconComponents`, `DispatchServingFilamentEvent`.
  - Authentication Middleware: `Filament\Http\Middleware\Authenticate::class` mapped to the `platform` guard.

**Architectural Finding:** The panel cleanly isolates sessions from tenant ERP sessions (`web` guard). Authenticating into `/platform` establishes a session on the `platform` guard, preventing privilege leakage between platform operators and tenant stores.

### 2.2 Platform User Authentication & Database Architecture
- **Model:** `app/Models/PlatformUser.php`
- **Table:** `platform_users` (`database/migrations/2026_11_01_000004_create_platform_users_table.php`)
- **Key Columns:**
  - `id` (UUID, Primary Key)
  - `name` (string)
  - `email` (string, unique)
  - `password` (hashed)
  - `role` (string, default: `support_engineer`)
  - `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` (nullable text/timestamps)
  - `remember_token`, `timestamps`
- **Panel Access Policy:** Implements `FilamentUser::canAccessPanel(Panel $panel): bool`, evaluating `$panel->getId() === 'platform'`.
- **Role Helper:** `isSuperAdmin(): bool` evaluates `$this->role === 'super_admin'`.

**Architectural Finding:** The database schema anticipated Two-Factor Authentication (`two_factor_secret`), but 2FA enforcement is not currently wired into `PlatformPanelProvider.php` via Filament's `->profile()` or `->twoFactorAuthentication()` directives. Furthermore, role-based authorization is binary (`super_admin` vs non-super-admin) without granular spatie-like permissions.

---

## 3. Visual & Component-Level Audit (Reference Screenshots)

### 3.1 Platform Login View
- **Visual Artifact:** `media_1793690492768_02ad553b.png`
- **Route:** `https://dev.kcerp.com/platform/login`
- **Analysis:**
  - Clean, minimalist dark authentication modal with dark charcoal/slate background.
  - Amber `#F59E0B` primary button accents matching brand identity.
  - Form fields: Email Address, Password, Remember Me checkbox.
  - **Gaps Identified:**
    - Lacks corporate logo or SVG platform crest (displays generic text "KC ERP Control Plane").
    - Missing SSO / SAML / OIDC enterprise identity integration.
    - Missing explicit link for self-service platform password recovery.
    - Missing 2FA OTP prompt step for elevated operator roles.

### 3.2 Platform Dashboard: Layout & Viewport Utilization
- **Visual Artifacts:** `media_1793685327602_ed74bae1.png`, `media_1793687586098_b558ab4e.png`, `media_1793689285424_2fefa04c.png`
- **Route:** `https://dev.kcerp.com/platform`
- **Analysis:**
  - **Screen Space Wastage:** The Filament dashboard container has fixed horizontal padding that centers content in a constrained column, resulting in wide dead black space on standard 1080p and 1440p displays.
  - **Layout Flow:**
    - Top: Account Greeting Card ("Welcome, Super Admin").
    - Row 1: 4 Stats Overview Cards.
    - Row 2: "Platform Quick Actions & Store Launchpad" (6 cards).
    - Row 3: "Subscription Plans Distribution" (Doughnut Chart) + "System & Security Telemetry" (Status Box).
    - Row 4: "Registered Retail Tenants & Store Portals" Table.

---

## 4. In-Depth Component & Code Inspection

### 4.1 Widget 1: PlatformStatsOverviewWidget
- **Source File:** `app/Filament/Platform/Widgets/PlatformStatsOverviewWidget.php`
- **Reported Metrics:**
  1. `Total Retail Tenants`: Dynamically queries `Tenant::count()` and `Tenant::where('status', 'active')->count()`.
  2. `Platform MRR`: Sums `price_bdt` of active tenant plans.
  3. `Retail Outlets & POS`: Queries `Branch::count()` and `PosTerminal::count()`.
  4. `Daily Invariant Audits`: Hardcoded string `'100% Passed'` with description `'GL Trial Balance, VAT & Stock movements verified'`.
- **Critical Code Gaps Found:**
  - **Line 48-50:** Hardcoded fallback:
    ```php
    if ($mrr === 0.0 && $totalTenants > 0) {
        $mrr = 42500.0;
    }
    ```
    If subscription billing records are incomplete, the system displays a fabricated revenue number (`৳42,500`) instead of reporting real billing data.
  - **Line 60, 66, 72:** Hardcoded sparkline charts:
    ```php
    ->chart([1, 2, 3, 4, 5, max(6, $totalTenants)])
    ->chart([25000, 30000, 35000, 40000, (int) $mrr])
    ->chart([10, 20, 30, 45, max(51, $terminalsCount)])
    ```
    These are purely decorative static sequences, not reflective of historical growth or real telemetry.
  - **Line 74-77:** The `Daily Invariant Audits` card is completely static and does not query `audit_logs`, `journal_entries`, or run `InvariantWatchdogService`.

### 4.2 Widget 2: PlatformQuickActionsWidget
- **Source Files:**
  - `app/Filament/Platform/Widgets/PlatformQuickActionsWidget.php`
  - `resources/views/filament/platform/widgets/quick-actions.blade.php`
- **Contents:** 6 action cards:
  1. `Acme Retail BD`: Hardcoded link to `/admin/acme`.
  2. `POS Cashier Terminal`: Hardcoded link to `/pos`.
  3. `Staging Retail Mart`: Hardcoded link to `/admin/staging`.
  4. `Retail Organizations`: Link to `/platform/tenants`.
  5. `Plans & Tier Limits`: Link to `/platform/plans`.
  6. `Daily Invariant Watchdog`: External link to `/ready`.
- **Critical Code Gaps Found:**
  - The pilot store cards (`Acme Retail BD`, `Staging Retail Mart`) are **hardcoded in Blade HTML**. If new tenants are added or tenant subdomains change, this widget does not reflect dynamic tenant instances.
  - Card button styles use raw inline CSS (`style="display: grid; ...;"`) instead of idiomatic Filament Tailwind blade components, causing visual inconsistencies on high-DPI displays.

### 4.3 Widget 3: PlatformPlanDistributionChart
- **Source File:** `app/Filament/Platform/Widgets/PlatformPlanDistributionChart.php`
- **Type:** Doughnut Chart (Chart.js)
- **Data Source:** Counts tenants by `subscription_plan` (`starter`, `standard`, `enterprise`).
- **Critical Code Gaps Found:**
  - **Lines 61-66:** Mock data injection:
    ```php
    if ($starterCount === 0 && $standardCount === 0 && $enterpriseCount === 0) {
        $starterCount = 3;
        $standardCount = 1;
        $enterpriseCount = 2;
    }
    ```
    In a clean database or newly provisioned environment, the chart invents 6 tenants across tiers rather than displaying an empty state.
  - Dynamic plans created via `PlanResource` with custom codes (e.g. `wholesale_pro`) are completely ignored because the widget hardcodes only three string keys.

### 4.4 Widget 4: PlatformSystemHealthWidget
- **Source Files:**
  - `app/Filament/Platform/Widgets/PlatformSystemHealthWidget.php`
  - `resources/views/filament/platform/widgets/system-health.blade.php`
- **Rendered Items:**
  1. `PostgreSQL 17 DB`: "68/68 tables forced RLS"
  2. `Redis 7 Cluster`: "Session & Horizon active"
  3. `Audit Trail Integrity`: "SHA-256 HMAC sealed"
  4. `Mushak 6.3 Tax Vault`: "NBR sequence compliant"
  5. `Go POS Bridge`: "127.0.0.1:8181 active"
  6. `Disaster Recovery`: "RPO < 5m • RTO < 30m"
- **Critical Code Gaps Found:**
  - **Completely Static Mock HTML:** The widget executes **zero real-time probes**.
  - **Drifted Invariant:** It claims "68/68 tables forced RLS". However, following Sprint 2 (Phase 3 Landed Cost Vouchers), the actual tenant schema contains **74 forced RLS tables**. The telemetry is out of sync with actual database reality.
  - The "View System Readiness" button simply opens `/ready` in a new tab rather than rendering inline operational telemetry.

### 4.5 Widget 5: PlatformTenantsTableWidget
- **Source File:** `app/Filament/Platform/Widgets/PlatformTenantsTableWidget.php`
- **Data Query:** `Tenant::query()->latest()`
- **Columns:** `name`, `subdomain`, `subscription_plan`, `status`, `created_at`.
- **Actions:** `open_admin` (`/admin/{subdomain}`), `edit_tenant` (`TenantResource::getUrl('edit', ...)`).
- **Assessment:** Functionally operative. Accurately queries the `tenants` table. Provides direct launch links into tenant ERP instances. Lacks inline search filters for plan tier, outlet counts, or active POS terminals.

---

## 5. Resource & Domain Inspection

### 5.1 PlanResource
- **Source File:** `app/Filament/Platform/Resources/Plans/PlanResource.php`
- **Model:** `App\Models\Plan` (`plans` table)
- **Capabilities:** Create, edit, list subscription tiers (`code`, `name`, `price_bdt`, `billing_interval`, `max_branches`, `max_terminals`, `max_users`, `included_modules`, `is_active`, `description`).
- **Status:** **Implemented and verified.**

### 5.2 TenantSubscriptionResource
- **Source File:** `app/Filament/Platform/Resources/Subscriptions/TenantSubscriptionResource.php`
- **Model:** `App\Models\TenantSubscription` (`tenant_subscriptions` table)
- **Capabilities:** Assign plans to tenants, specify start/end dates and status (`active`, `in_arrears`, `cancelled`, `expired`).
- **Gaps:** Lacks automated billing cycles, prorated upgrades, renewal invoice generation, payment method capture, or payment gateway triggers.

### 5.3 TenantResource
- **Source File:** `app/Filament/Platform/Resources/Tenants/TenantResource.php`
- **Model:** `App\Models\Tenant` (`tenants` table)
- **Creation Workflow (`CreateTenant.php`):** Invokes `OnboardingWizardServiceContract::onboard()`, automatically generating:
  - Default legal company
  - Initial branch outlet (`BR-01`)
  - Initial POS terminal (`T-01`)
  - Initial admin user with role assignment
  - Base chart of accounts and number series
- **Lifecycle Actions:**
  - `open_store`: Opens `/admin/{subdomain}`.
  - `suspend`: Calls `TenantLifecycleService::suspend()`.
  - `reinstate`: Calls `TenantLifecycleService::reinstate()`.
- **Critical Gap:** The `suspend` modal collects a `reason` string, but `TenantLifecycleService::suspend()` discards it without storing it in an audit log or history table.

### 5.4 Support Impersonation Protocol (`TenantSupportAccess`)
- **Model:** `App\Models\TenantSupportAccess`
- **Migration:** `2026_11_01_000008_create_tenant_support_accesses_table.php`
- **Database Rules:** Strict composite RLS, foreign key constraints to `platform_users` and `users`, and CHECK constraint `chk_support_access_max_four_hours` (`expires_at <= granted_at + INTERVAL '4 hours'`).
- **Current Status:** **Partially implemented (Dormant).** The database schema and Eloquent model exist, but there is zero UI in Filament for operators to request or activate support access, and no middleware enforces read-only support session constraints.

---

## 6. Comprehensive Feature Classification Matrix

| Feature Area | Component / Resource | Current Implementation State | Technical Verdict | Action Required |
| :--- | :--- | :--- | :--- | :--- |
| **Authentication** | Platform Login (`/platform/login`) | Implemented and verified | Clean isolation via `platform` guard | Add 2FA prompt & password recovery |
| **Authentication** | Platform RBAC (`PlatformUser`) | Partially implemented | Only `role` column with `isSuperAdmin()` check | Implement 6-tier platform RBAC policy |
| **Dashboard** | Stats Overview Widget | Implemented but incomplete | Queries DB, but contains hardcoded fallbacks | Remove hardcoded fallbacks; bind to real telemetry |
| **Dashboard** | Store Launchpad Widget | Implemented but incomplete | Hardcoded HTML cards for pilot stores | Convert to dynamic tenant launchpad query |
| **Dashboard** | Plan Distribution Chart | Implemented but incomplete | Hardcoded fallback mock values for empty DB | Support dynamic plans; render true empty state |
| **Dashboard** | System & Security Telemetry | Partially implemented | 100% static mock HTML; drifted RLS count (68 vs 74) | Replace with real-time probe service |
| **Dashboard** | Registered Tenants Table | Implemented and verified | Operative; binds to Eloquent `Tenant` query | Add outlet counts, terminal counts & search filters |
| **SaaS Management** | Tenant Onboarding Wizard | Implemented and verified | Automated provisioning of company, branch, POS | Retain and surface provisioning progress bar |
| **SaaS Management** | Tenant Lifecycle (Suspend/Reinstate) | Implemented but incomplete | Updates status; discards suspension reason | Record suspension reasons in audit log |
| **SaaS Management** | Outlet & Branch Fleet Directory | Not implemented | Outlets only viewable inside individual tenant ERP | Build cross-tenant Platform Outlet directory |
| **SaaS Management** | POS Terminal Fleet Directory | Not implemented | Terminals only viewable inside tenant ERP | Build cross-tenant Platform POS Terminal monitor |
| **Commercial** | Subscription Plan Management | Implemented and verified | Full CRUD on `plans` table with module matrix | Add price revision history and grandfathering |
| **Commercial** | Tenant Subscription Assignment | Implemented but incomplete | Assigns plan; lacks invoice & payment integration | Build automated recurring billing engine |
| **Commercial** | SaaS Subscription Invoicing | Not implemented | Zero platform invoice schema or records | Create `platform_invoices` schema and PDF generator |
| **Commercial** | Payment Gateway Integration | Not implemented | No bKash, Nagad, or bank wire integration | Build commercial payment collection ledger |
| **Operations** | Real-time Database & RLS Monitor | Not implemented | Relies on manual CLI command (`db:check-rls`) | Build interactive RLS & connection monitor |
| **Operations** | Invariant Watchdog Monitor | Not implemented | Only accessible via CLI (`pilot:daily-check`) | Surface real-time Invariant Watchdog dashboard |
| **Operations** | Queue & Horizon Telemetry | Not implemented | Only accessible via CLI or separate Horizon UI | Surface active worker & failed job counters |
| **Operations** | Go POS Bridge Health Check | Not implemented | Telemetry card merely shows static text | Implement HTTP ping probe to `127.0.0.1:8181` |
| **Security** | Support Impersonation Protocol | Partially implemented | Schema exists; zero UI, actions, or middleware | Implement ticket-based support impersonation UI |
| **Security** | Platform Audit Logging | Not implemented | Tenant audit logs exist; zero platform audit log | Implement `platform_audit_logs` table & UI |
| **Customer Success** | System Announcements / Broadcasts | Not implemented | No mechanism to message active tenant users | Implement platform maintenance broadcast banner |
| **UI/UX** | Responsive Desktop Grid | Implemented but incomplete | Fixed narrow container; massive empty side space | Overhaul CSS to full-width responsive grid |
| **UI/UX** | Grouped Navigation Sidebar | Not implemented | Flat list of 4 items | Organize into 6 enterprise navigation groups |

---

## 7. Immediate Architectural Risks & Technical Debt

1. **Demonstration Code Leakage into Production:** The fallback logic in `PlatformStatsOverviewWidget` (`$mrr = 42500.0`) and `PlatformPlanDistributionChart` (`$starterCount = 3...`) poses severe compliance and business risk. Management or auditors viewing `/platform` may rely on fabricated operational numbers.
2. **False Telemetry Assurance:** The `PlatformSystemHealthWidget` claims "68/68 tables forced RLS" and "Audit Trail Integrity SHA-256 HMAC sealed" via static HTML. If a PostgreSQL constraint drops, an RLS policy fails, or the Go bridge crashes, the dashboard will continue displaying green checks.
3. **Audit Blind Spot on Platform Operations:** Platform operators can suspend retail chains, alter subscription plans, and modify tenant records without generating an immutable audit trail.
4. **Unregulated Support Access:** Without an operative UI and middleware enforcement for `tenant_support_accesses`, developers or support staff must either query the database directly or use shared credentials, violating non-negotiable tenant isolation rules.

---

## 8. Conclusion & Recommendations

The KC ERP Control Plane has a solid architectural core (clean Laravel/Filament foundations, strict `platform` authentication guard, and a functional tenant onboarding wizard). However, transitioning it into a commercial-grade SaaS control center requires an immediate overhaul of:
1. **Telemetry & Integrity:** Replacing all mock/static widgets with live probe services.
2. **UI/UX & Layout:** Implementing full-width responsive grids and 6-group structured navigation.
3. **Commercial Engine:** Adding subscription invoicing, payment recording, and automated dunning.
4. **Security Governance:** Operationalizing ticket-backed support access and platform audit logging.

The detailed gap analysis, specifications, and execution roadmap are established in the companion documents under `docs/platform-control-plane/`.
