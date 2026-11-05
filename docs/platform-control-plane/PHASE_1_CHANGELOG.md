# KC ERP CONTROL PLANE - PHASE 1 CHANGELOG

**Application:** KC ERP Control Plane (`/platform`)  
**Release:** Control Plane v1.1.0 (Phase 1 Approved Scope)  
**Baseline Commit:** `d4560e5`  
**Date:** 2026-11-05  

---

## 1. ADDED

### Core Platform Architecture
- Added `App\Core\Platform\Services\PlatformTelemetryService`:
  - PostgreSQL 17 execution probe measuring database query latency.
  - PostgreSQL catalog inspector validating forced Row-Level Security (`c.relrowsecurity = true`) across all 74 tenant tables.
  - Redis 7 cluster ping and latency probe.
  - Horizon failed queue jobs inspector.
  - Go POS bridge HTTP daemon probe (`127.0.0.1:8181`).
  - Standardized `brick/math` MRR and ARR calculator supporting monthly and annual billing intervals.
- Added `App\Core\Platform\Services\PlatformAuditService`:
  - Immutable platform audit ledger writer.
  - SHA-256 HMAC cryptographic signature generator (`payload_hash`).
  - Automated recursive PII and credential sanitizer (`[REDACTED]`).
  - Tenant and plan lifecycle event auditing (`logTenantCreation`, `logTenantSuspension`, `logTenantReinstatement`, `logPlanCreation`, `logPlanUpdate`).
- Added `App\Models\PlatformAuditLog`:
  - UUID-primary key Eloquent model for platform operations.
  - Runtime immutability guards throwing exceptions on `updating` or `deleting`.

### Database Migrations
- Added migration `database/migrations/2026_11_16_000001_create_platform_audit_logs_table.php`:
  - Created `platform_audit_logs` table on connection `pgsql_owner`.
  - Added PL/pgSQL function `trg_fn_enforce_platform_audit_log_immutability()` and trigger `trg_platform_audit_logs_immutability` to forbid `UPDATE` and `DELETE`.
  - Migrated on both `erp` and `erp_test` databases.

### Filament 5 Platform Panel & Resources
- Added `App\Filament\Platform\Resources\AuditLogs\PlatformAuditLogResource`:
  - Registered in `SECURITY & GOVERNANCE` navigation group.
  - Read-only table with action filters, severity filters, and date range filters.
  - Detail page displaying before/after JSON diffs, actor details, client IP, and HMAC seal.
- Added `App\Filament\Platform\Resources\AuditLogs\Pages\ListPlatformAuditLogs`.
- Added `App\Filament\Platform\Resources\AuditLogs\Pages\ViewPlatformAuditLog`.

### Automated Testing
- Added `tests/Feature/Platform/PlatformNavigationTest.php` (4 test cases, 18 assertions).
- Added `tests/Feature/Platform/PlatformDashboardKpiTest.php` (4 test cases, 10 assertions).
- Added `tests/Feature/Platform/PlatformTelemetryServiceTest.php` (5 test cases, 24 assertions).
- Added `tests/Feature/Platform/PlatformAuditLogTest.php` (4 test cases, 14 assertions).

---

## 2. CHANGED

### Filament Navigation & Layout
- Updated `App\Providers\Filament\PlatformPanelProvider`:
  - Re-architected sidebar navigation into 6 standard groups:
    1. `SAAS MANAGEMENT`
    2. `COMMERCIAL`
    3. `PLATFORM OPERATIONS`
    4. `SECURITY & GOVERNANCE`
    5. `CUSTOMER SUCCESS`
    6. `SYSTEM SETTINGS`
  - Enabled desktop collapsible sidebar (`sidebarCollapsibleOnDesktop()`).
  - Set sidebar width to `17rem`.
  - Enabled full-width responsive dashboard canvas (`maxContentWidth(Width::Full)`).
  - Configured global search keybindings (`Cmd+K` / `Ctrl+K`).

### Resources & Pages
- Updated `App\Filament\Platform\Resources\Tenants\TenantResource`:
  - Assigned to `SAAS MANAGEMENT` (Sort 1).
  - Added navigation badge displaying active vs total tenant count.
  - Added status filter (`active`, `suspended`, `in_arrears`, `onboarding`) and subscription tier filter.
  - Wired tenant suspension and reinstatement actions to `PlatformAuditService`.
- Updated `App\Filament\Platform\Resources\Tenants\Pages\ListTenants`:
  - Added status tabs (`All`, `Active`, `In Arrears`, `Suspended`).
- Updated `App\Filament\Platform\Resources\Tenants\Pages\CreateTenant`:
  - Wired `afterCreate` hook to log tenant creation in `PlatformAuditService`.
- Updated `App\Filament\Platform\Resources\Plans\PlanResource`:
  - Assigned to `COMMERCIAL` (Sort 1).
- Updated `App\Filament\Platform\Resources\Plans\Pages\CreatePlan` and `EditPlan`:
  - Wired audit logging for commercial plan definitions.
- Updated `App\Filament\Platform\Resources\Subscriptions\TenantSubscriptionResource`:
  - Assigned to `COMMERCIAL` (Sort 2).
  - Added navigation badge for subscriptions in arrears.

### Widgets & Blade Views
- Updated `App\Filament\Platform\Widgets\PlatformStatsOverviewWidget`:
  - Eliminated hardcoded `$mrr = 42500.0` and synthetic arrays.
  - Integrated live telemetry from `PlatformTelemetryService`.
- Updated `App\Filament\Platform\Widgets\PlatformPlanDistributionChart`:
  - Eliminated fake distribution numbers `[3, 1, 2]`.
  - Dynamically queries `Plan` and subscriptions; renders clean empty state when no subscriptions exist.
- Updated `App\Filament\Platform\Widgets\PlatformSystemHealthWidget` and `system-health.blade.php`:
  - Converted static HTML into a dynamic grid displaying live subsystem latencies and RLS status.
- Updated `App\Filament\Platform\Widgets\PlatformQuickActionsWidget` and `quick-actions.blade.php`:
  - Converted static cards into a dynamic active tenant store launchpad with ERP dashboard links.
- Updated `App\Filament\Platform\Widgets\PlatformTenantsTableWidget`:
  - Enhanced responsive column sizing and status badge indicators.

---

## 3. REMOVED
- Removed all hardcoded fallback values for platform financial KPIs.
- Removed synthetic sparkline generation.
- Removed static placeholder store cards.

---

## 4. BREAKING CHANGES
**NONE.**  
All tenant ERP workflows, subledgers, document chains, POS terminals, and accounting mechanisms remain intact and fully compatible.

---

## 5. NEXT STEPS REQUIRING HUMAN AUTHORIZATION
The following phases remain **STRICTLY ON HOLD** pending explicit human approval:
- **Phase 2:** Automated Tenant Provisioning & Isolated DB Setup.
- **Phase 3:** Commercial Billing Engine, Invoicing, and Payment Gateway Integration.
- **Phase 4:** Support Portal, Tenant Impersonation & Audit Trail.
- **Phase 5:** Global System Configuration & Infrastructure Operations.
