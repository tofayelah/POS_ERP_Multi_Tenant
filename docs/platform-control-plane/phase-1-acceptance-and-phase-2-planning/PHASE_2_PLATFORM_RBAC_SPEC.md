# KC ERP CONTROL PLANE
# PHASE 2 PLATFORM RBAC & USER MANAGEMENT SPECIFICATION

**Application:** KC ERP Control Plane (`/platform`)  
**Sprint:** 2.1 (Platform Authentication, RBAC, and Session Security)  
**Date:** 2026-11-05  

---

## 1. SIX-TIER PLATFORM ROLE ARCHITECTURE

Access to the KC ERP Control Plane is governed by a strict, hierarchical, six-tier Role-Based Access Control (RBAC) model. All access defaults to **DENY**.

```
[ Tier 1: SUPER ADMIN ]
        |
        +---> [ Tier 2: PLATFORM OPERATIONS (DevOps) ]
        |
        +---> [ Tier 3: FINANCIAL CONTROLLER (Billing) ]
        |
        +---> [ Tier 4: SECURITY & COMPLIANCE OFFICER ]
        |
        +---> [ Tier 5: CUSTOMER SUCCESS (Support Tier 2) ]
        |
        +---> [ Tier 6: READ-ONLY AUDITOR ]
```

### Role Definitions

| Role Key | Name | Hierarchy Level | Description & Core Responsibilities |
|---|---|---|---|
| `super_admin` | Super Administrator | Level 1 (Top) | Unrestricted emergency control, system configuration, security policy management, and role delegation. |
| `platform_ops` | Platform Operations / DevOps | Level 2 | Infrastructure monitoring, queue management, database maintenance, telemetry inspection, and release deployment oversight. |
| `finance_admin` | Financial Controller | Level 3 | Plan pricing, subscription billing oversight, invoice auditing, revenue reconciliation, and payment dispute management. |
| `compliance_officer` | Security & Compliance Officer | Level 4 | Platform audit log review, compliance reporting, break-glass session oversight, and access authorization auditing. |
| `support_tier2` | Customer Success / Support Tier 2 | Level 5 | Tenant onboarding assistance, technical troubleshooting, support access requests, and basic tenant telemetry inspection. |
| `auditor_ro` | Read-Only Auditor | Level 6 | Read-only inspection of tenant registries, subscription records, audit trails, and system health metrics. Zero mutation rights. |

---

## 2. GRANULAR PERMISSION MATRIX

Permissions are structured as domain-scoped action slugs (`domain.resource.action`).

| Permission Slug | Description | Super Admin | Platform Ops | Finance Admin | Compliance Officer | Support Tier 2 | Read-Only Auditor |
|---|---|:---:|:---:|:---:|:---:|:---:|:---:|
| `platform.users.manage` | Create, edit, and deactivate platform users | **YES** | NO | NO | NO | NO | NO |
| `platform.users.assign_roles` | Assign RBAC roles (up to self-tier) | **YES** | NO | NO | NO | NO | NO |
| `platform.tenants.view` | View tenant registry and status | **YES** | **YES** | **YES** | **YES** | **YES** | **YES** |
| `platform.tenants.create` | Provision new tenant metadata | **YES** | **YES** | NO | NO | **YES** | NO |
| `platform.tenants.suspend` | Suspend or reinstate tenant access | **YES** | **YES** | **YES** | NO | NO | NO |
| `platform.plans.view` | View subscription plan definitions | **YES** | **YES** | **YES** | **YES** | **YES** | **YES** |
| `platform.plans.manage` | Create or update pricing & module limits | **YES** | NO | **YES** | NO | NO | NO |
| `platform.telemetry.view` | View system health & subsystem latency | **YES** | **YES** | NO | **YES** | NO | **YES** |
| `platform.telemetry.diagnose` | Run manual diagnostic probes | **YES** | **YES** | NO | NO | NO | NO |
| `platform.audit.view` | View platform audit log records | **YES** | **YES** | **YES** | **YES** | NO | **YES** |
| `platform.audit.export` | Export audit logs for statutory compliance| **YES** | NO | NO | **YES** | NO | NO |
| `platform.support.request` | Request tenant delegated access token | **YES** | **YES** | NO | NO | **YES** | NO |
| `platform.support.approve_breakglass`| Approve emergency break-glass access | **YES** | NO | NO | **YES** | NO | NO |
| `platform.system.config` | Modify global platform configuration | **YES** | NO | NO | NO | NO | NO |

---

## 3. PLATFORM USER MANAGEMENT SPECIFICATION

### Database Schema Extension (`platform_users`)
In Phase 2, the existing `platform_users` table is extended to support RBAC and lifecycle management:

```sql
ALTER TABLE platform_users
    ADD COLUMN IF NOT EXISTS role VARCHAR(50) NOT NULL DEFAULT 'support_tier2',
    ADD COLUMN IF NOT EXISTS permissions JSONB DEFAULT '[]'::jsonb,
    ADD COLUMN IF NOT EXISTS status VARCHAR(30) NOT NULL DEFAULT 'active', -- active, inactive, locked
    ADD COLUMN IF NOT EXISTS failed_login_attempts INT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS locked_until TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS last_login_ip VARCHAR(45) NULL,
    ADD COLUMN IF NOT EXISTS password_changed_at TIMESTAMPTZ NULL;

CREATE INDEX idx_platform_users_role ON platform_users(role);
CREATE INDEX idx_platform_users_status ON platform_users(status);
```

### Lifecycle & Security Rules
1. **Account Deactivation:** Inactive users cannot authenticate. Deactivating a user immediately invalidates all active sessions in Redis.
2. **Brute Force Lockout:** 5 consecutive failed login attempts automatically locks the account for 30 minutes (`locked_until = now() + interval '30 minutes'`).
3. **Password Policy:** Minimum 16 characters, requiring uppercase, lowercase, numbers, and special symbols. Rejects passwords in common breach dictionaries.
4. **Self-Elevation Prevention:** A platform administrator cannot assign a role higher than their own hierarchy tier. A `platform_ops` user cannot create or promote a user to `super_admin`.

---

## 4. SESSION SECURITY & TIMEOUT MANAGEMENT

### Configuration Rules
- **Idle Session Timeout:** 15 minutes of inactivity automatically terminates the platform session.
- **Absolute Session Lifetime:** Maximum 8 hours, regardless of activity. After 8 hours, the administrator must re-authenticate with password and MFA.
- **Concurrent Session Policy:** Enforces a single active session per platform user. Authenticating from a new device/browser immediately terminates previous sessions.
- **Session Revocation on Credential Change:** Updating password or resetting MFA keys instantly terminates all active sessions across all devices.

---

## 5. SENSITIVE-ACTION RE-AUTHENTICATION ("SUDO MODE")

Administrative mutations with high security impact require re-authentication before execution, even if the user has an active session:

### Sudo-Protected Actions
1. Suspending or reinstating an active tenant.
2. Modifying commercial plan prices or module entitlements.
3. Creating or modifying platform administrators.
4. Requesting break-glass emergency tenant access.
5. Exporting unredacted audit trails.

### Verification Flow
1. Operator triggers sensitive action in Filament UI.
2. Modal prompt requests: Current Password OR Fresh TOTP Code.
3. Upon successful verification, system sets session variable `platform:sudo_verified_until = now() + 10 minutes`.
4. Subsequent sensitive actions within 10 minutes execute without re-prompting; after 10 minutes, sudo mode expires.
