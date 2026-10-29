# Step 3: Identity, Authentication, and Separation of Duties (SoD) Implementation Plan

## 1. Executive Summary & Review Scope
- **Step Objective:** Implement a unified identity, multi-tenant membership, and granular authorization architecture. Establish dual authentication guards separating platform staff from tenant personnel, configure Spatie Laravel-Permission in teams mode, implement comprehensive Location Scoping across all query surfaces, enforce five hard Segregation of Duties (SoD) conflict rules, implement a consented, time-limited support access protocol, harden authentication with Argon2id, throttling and TOTP 2FA, and provide a full demo seeder.
- **Design Sections:**
  - §1.2: System Topology & Guard Separation (Tenant User App, Tenant Admin Panel, Super-Admin Panel).
  - §3: User Personas, RBAC Permission Matrix (§3.2), and Segregation of Duties (§3.3).
  - §6.5: User Identity & Multi-Tenant Membership (reconciled with ADR-015).
  - §7.3: Security, Support Access Protocol, and Hierarchical Settings.
- **Review Items Closed:**
  - `TEN-10` (High): Eliminate `is_platform_admin` flag on shared `users`; create dedicated `platform_users` table with its own guard; unify roles in Spatie teams mode; bind Filament tenancy to `TenantContext`.
  - `SEC-01` (High): Enforce five hard Segregation of Duties (SoD-01 to SoD-05) rules at policy/service gates; alert owner on privileged role escalation.
  - `SEC-02` (High): Support access elevation protocol: Tenant Owner in-app consent switch, 4-hour hard ceiling, mandatory ticket reference, active visual warning banner, read-only by default, and immutable audit logging.
  - `SEC-06` (Medium): Authentication hardening: IP + email login throttling with exponential backoff, Argon2id password hashing, and mandatory TOTP 2FA for administrative and financial roles.
  - `SEC-09` (High): Multi-surface branch and company scoping (`user_location_scopes`): global Eloquent `LocationScope` and policy checks enforced across tables, global search, relation managers, exports, and API resources.

---

## 2. Architectural Blueprint & Core Deliverables

### A. Identity & Membership Data Model (ADR-015, `TEN-10`)
1. **Global `users` Table (Tenant Personnel):**
   - Exempt from RLS (global authentication entity).
   - Columns: `id UUID PRIMARY KEY`, `name VARCHAR(255)`, `email VARCHAR(255) UNIQUE`, `phone VARCHAR(50) NULL`, `password VARCHAR(255)`, `two_factor_secret TEXT NULL`, `two_factor_recovery_codes TEXT NULL`, `two_factor_confirmed_at TIMESTAMP NULL`, timestamps.
   - Note: The `is_platform_admin` column from §6.5 draft is **removed**, resolving `TEN-10`.
2. **Dedicated `platform_users` Table (Platform Super-Admins & Support Engineers):**
   - Exempt from RLS (platform control plane).
   - Columns: `id UUID PRIMARY KEY`, `name VARCHAR(255)`, `email VARCHAR(255) UNIQUE`, `password VARCHAR(255)`, `role VARCHAR(50)` (`super_admin`, `support_engineer`), `two_factor_secret TEXT NULL`, `two_factor_recovery_codes TEXT NULL`, `two_factor_confirmed_at TIMESTAMP NULL`, timestamps.
3. **`tenant_memberships` Pivot Table:**
   - Links global users to authorized tenants.
   - Columns: `id UUID PRIMARY KEY`, `tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE`, `user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE`, `is_active BOOLEAN NOT NULL DEFAULT true`, timestamps.
   - Constraints: `UNIQUE (tenant_id, user_id)`.
   - Global mapping accessible to authenticated users to populate the Filament tenant switcher before tenant context is initialized.
4. **`branches` Table (Organizational Entity):**
   - Tenant-scoped table created with `TenantMigration::create`.
   - Columns: `id UUID`, `tenant_id UUID NOT NULL`, `company_id UUID NOT NULL`, `name VARCHAR(255)`, `code VARCHAR(50)`, `is_active BOOLEAN NOT NULL DEFAULT true`, timestamps.
   - Constraints: `FOREIGN KEY (tenant_id, company_id) REFERENCES companies(tenant_id, id) ON DELETE RESTRICT`, `UNIQUE (tenant_id, id)`.
   - RLS enabled and forced with `tenant_isolation_policy`.
5. **`user_location_scopes` Table (`SEC-09`):**
   - Tenant-scoped table created with `TenantMigration::create`.
   - Columns: `id UUID`, `tenant_id UUID NOT NULL`, `membership_id UUID NOT NULL REFERENCES tenant_memberships(id) ON DELETE CASCADE`, `company_id UUID NOT NULL`, `branch_id UUID NULL`, timestamps.
   - Constraints: `FOREIGN KEY (tenant_id, company_id) REFERENCES companies(tenant_id, id) ON DELETE CASCADE`, `UNIQUE (tenant_id, membership_id, company_id, branch_id)`.
   - RLS enabled and forced. If `branch_id IS NULL`, the user has company-wide scope across all branches of that company.

---

### B. Dual Authentication Guards & Filament Panels (`TEN-10`)
1. **Guard Configuration (`config/auth.php`):**
   - `web` guard: Provider `users` (Model: `App\Models\User`), session-based, used by Tenant Admin panel and Tenant User App.
   - `platform` guard: Provider `platform_users` (Model: `App\Models\PlatformUser`), session-based, used exclusively by the Super-Admin panel.
2. **Filament Panels:**
   - **Tenant Admin Panel (`/admin`):**
     - Auth guard: `web`.
     - Multi-tenancy enabled: `tenant(Tenant::class, slugAttribute: 'subdomain')`.
     - Tenant registration reads `$user->memberships()->with('tenant')->get()->pluck('tenant')`.
     - Tenant switching callback: Executes `TenantContext::set($tenant)` immediately, setting PostgreSQL session setting `app.current_tenant_id` for RLS.
   - **Super-Admin Panel (`/platform`):**
     - Auth guard: `platform`.
     - Tenancy disabled (manages global tenant directory, subscription plans, platform telemetry, and consented support access sessions).

---

### C. Unified Spatie Permissions in Teams Mode (`TEN-10`, `SEC-01`)
1. **Configuration (`config/permission.php`):**
   - `teams => true` with `team_foreign_key => 'tenant_id'`.
   - `register_permission_check_method => true`.
2. **Role Setup per §3.2 Matrix:**
   - 11 Tenant Roles: `Tenant Owner`, `Tenant Admin`, `Branch Manager`, `Shift Supervisor`, `Accountant`, `Procurement Manager`, `Sales Manager`, `Warehouse Manager`, `Cashier`, `Sales Representative`, `Auditor`.
   - Permissions seeded per tenant using `setPermissionsTeamId($tenant->id)`.
   - Deprecate redundant `tenant_memberships.role` column to ensure single source of truth.

---

### D. Multi-Surface Location Scoping (`SEC-09`)
Enforce branch and company scoping inside a tenant across all application surfaces:
1. **Eloquent Global Scope (`App\Core\Scoping\LocationScope`):**
   - Applied to models implementing `HasLocationScopeInterface` (`Branch`, `Warehouse`, `StockMovement`, `SalesOrder`, etc.).
   - If user is authenticated and has location scopes:
     - Applies `WHERE (company_id = ... AND (branch_id = ... OR branch_id IS NULL))`.
     - If user has no scope assigned for the active company/branch, queries return empty (fail-closed).
     - Global tenant owners / accountants with company-wide scopes bypass branch-level restrictions.
2. **Policy Authorization (`App\Policies\Concerns\EnforcesLocationScope`):**
   - Policy methods (`view`, `update`, `delete`) verify `$user->canAccessLocation($record->company_id, $record->branch_id)`.
3. **Multi-Surface Enforcement:**
   - **Surface 1: Tables (Filament & Eloquent):** Auto-filtered by `LocationScope`.
   - **Surface 2: Global Search (Filament):** Global search queries hook into `LocationScope` to prevent discovery of other branches' data.
   - **Surface 3: Relation Managers:** Nested resource queries (e.g. Company -> Branches) enforce location constraints.
   - **Surface 4: Streaming Exports:** Export workers stream data through queries bound to the requesting user's `LocationScope`.
   - **Surface 5: API Resources / Endpoints:** REST controllers apply `LocationScope` and return 403 on attempted cross-branch access.

---

### E. Segregation of Duties (SoD) Engine (`SEC-01`)
Implement five hard conflict rules as policy checks and domain service gates:
1. **SoD-01 (Purchase & Pay Conflict):**
   - A user who created or approved a Purchase Order cannot author or post the corresponding Supplier Payment.
   - Enforced in `SupplierPaymentPolicy::create()`: `$purchaseOrder->created_by !== $user->id && $purchaseOrder->approved_by !== $user->id`.
2. **SoD-02 (Supplier Creation & Payment):**
   - A user who created or updated a Supplier record cannot authorize disbursements to that supplier.
   - Enforced in `DisbursementPolicy::authorize()`: `$supplier->created_by !== $user->id && $supplier->updated_by !== $user->id`.
3. **SoD-03 (Inventory Count & Adjustment):**
   - A warehouse clerk who performed or recorded physical stock counts cannot approve stock write-off adjustments.
   - Enforced in `StockAdjustmentPolicy::approve()`: `$stockCount->counted_by !== $user->id`.
4. **SoD-04 (Role Escalation Prevention):**
   - A `Tenant Admin` cannot assign the `Accountant` or `Tenant Owner` role to themselves or escalate their own privileges.
   - Privilege changes to financial and administrative roles require explicit counter-signature/approval from the registered `Tenant Owner`.
   - Alerts the Tenant Owner via notification upon any privileged role modification.
5. **SoD-05 (Super-Admin Elevation Protocol):**
   - Governed by the dedicated Support Access Engine.

---

### F. Consented Support-Access Protocol (`SEC-02`)
1. **`tenant_support_accesses` Table:**
   - Tenant-scoped table created with `TenantMigration::create`.
   - Columns: `id UUID`, `tenant_id UUID NOT NULL`, `platform_user_id UUID NOT NULL REFERENCES platform_users(id)`, `granted_by_user_id UUID NOT NULL REFERENCES users(id)`, `ticket_reference VARCHAR(100) NOT NULL`, `granted_at TIMESTAMP NOT NULL`, `expires_at TIMESTAMP NOT NULL`, `is_read_only BOOLEAN NOT NULL DEFAULT true`, `revoked_at TIMESTAMP NULL`, timestamps.
   - Constraint: `CHECK (expires_at <= granted_at + INTERVAL '4 hours')`.
   - RLS enabled and forced.
2. **Elevation Workflow:**
   - Tenant Owner toggles "Allow Support Access" in Tenant Settings, specifying `ticket_reference` and duration (maximum 4 hours).
   - Platform Support Engineer logs in via `/platform`, selects the consented tenant session, and enters the Tenant Admin panel.
   - Visual Warning Banner: Persistent red banner at top of Filament layout: *"Active Support Access Session (Ticket #{ticket_ref}) — Expires in {remaining_time} — Read-Only Mode"*.
   - Read-Only Default: Middleware blocks all `POST`, `PUT`, `PATCH`, `DELETE` requests with 403 Forbidden unless two-factor confirmation from the Tenant Owner is provided.
   - Hard Expiration: Middleware `VerifySupportAccessActive` immediately revokes session if `now() >= expires_at` or `revoked_at IS NOT NULL`.
   - Audit Trail: Immutable audit events recorded on grant, elevation login, write attempt, and revocation/expiration.

---

### G. Authentication Hardening (`SEC-06`)
1. **Password Hashing:**
   - Configure Argon2id as the default password hashing driver in `config/hashing.php` (`memory: 65536`, `time: 4`, `threads: 1`).
2. **Login Rate-Limiting & Throttling:**
   - Custom rate limiter `login`: 5 attempts per minute per `email + IP` with exponential lockout (1m, 5m, 15m, 60m).
3. **Mandatory TOTP Two-Factor Authentication:**
   - Required for privileged roles: `Tenant Owner`, `Tenant Admin`, `Accountant`, `Branch Manager`, and all `platform_users`.
   - Middleware `RequireTwoFactorAuthentication` forces redirection to TOTP challenge before panel access if unconfirmed.

---

### H. Demo Tenant Seeder (`DemoTenantSeeder`)
Seed a comprehensive reference dataset validating the entire §3.2 matrix:
- **Tenant:** `Acme Retail BD` (`subdomain: acme`, `status: active`).
- **Companies (2):**
  1. `Acme Retail Ltd` (`BIN: 000111222-0101`, Retail MRP Tax-Inclusive).
  2. `Acme Wholesale Ltd` (`BIN: 000111222-0202`, Wholesale B2B Tax-Exclusive).
- **Branches (3):**
  1. `Dhanmondi Branch` (under Acme Retail Ltd).
  2. `Gulshan Branch` (under Acme Retail Ltd).
  3. `Uttara Branch` (under Acme Wholesale Ltd).
- **Platform Staff (1 in `platform_users`):**
  - Super-Admin: `superadmin@kcerp.com` (Role: `super_admin`).
- **Tenant Personnel (11 in `users`, assigned via Spatie teams mode):**
  1. Tenant Owner: `owner@acme.com` (All companies, all branches).
  2. Tenant Admin: `admin@acme.com` (All companies, all branches).
  3. Branch Manager: `branchmgr@acme.com` (Scoped to Dhanmondi Branch).
  4. Shift Supervisor: `supervisor@acme.com` (Scoped to Dhanmondi Branch).
  5. Accountant: `accountant@acme.com` (Both companies, all branches, financial authority).
  6. Procurement Manager: `procurement@acme.com` (Both companies).
  7. Sales Manager: `salesmgr@acme.com` (Both companies).
  8. Warehouse Manager: `warehousemgr@acme.com` (Central warehouse).
  9. Cashier: `cashier@acme.com` (Scoped to Dhanmondi Branch).
  10. Sales Representative: `salesrep@acme.com` (Scoped to Uttara Branch).
  11. Auditor: `auditor@acme.com` (Read-only across all companies/branches).
- **Location Scopes:** Seeded in `user_location_scopes` reflecting exact branch boundaries.

---

## 3. Test-First Implementation Strategy (TDD)

All tests execute against PostgreSQL 17 connecting as `app_rw` (with migrations on `pgsql_owner`):

### Suite 1: Segregation of Duties Tests (`tests/Feature/Security/SegregationOfDutiesTest.php`)
1. `test_sod_01_user_who_created_or_approved_po_cannot_post_payment`:
   - User creates Purchase Order. User attempts to execute payment on same PO. Expects 403 Forbidden.
   - Different accountant user executes payment on same PO. Expects 200/201 Success.
2. `test_sod_02_user_who_created_or_edited_supplier_cannot_authorize_disbursement`:
   - User creates Supplier record. User attempts to authorize disbursement to that supplier. Expects 403 Forbidden.
3. `test_sod_03_clerk_who_recorded_stock_count_cannot_approve_write_off`:
   - Warehouse clerk records physical stock count. Same clerk attempts to approve the stock write-off adjustment. Expects 403 Forbidden.
   - Warehouse Manager approves adjustment. Expects Success.
4. `test_sod_04_tenant_admin_cannot_self_assign_accountant_or_owner_role`:
   - Tenant Admin attempts to assign `Accountant` or `Tenant Owner` role to their own user record without Owner counter-signature. Expects 403 Forbidden.
5. `test_sod_05_platform_support_cannot_access_tenant_without_active_consent`:
   - Platform support engineer attempts to access tenant resources without active consent record. Expects 403 Forbidden.

### Suite 2: Multi-Surface Location Scoping Tests (`tests/Feature/Security/LocationScopingTest.php`)
1. `test_surface_1_table_query_filters_records_outside_assigned_branches`:
   - Authenticate as Cashier (Dhanmondi Branch). Query branch-scoped records. Asserts zero records from Gulshan or Uttara Branches are returned.
2. `test_surface_2_global_search_excludes_records_outside_assigned_branches`:
   - Execute Filament global search as Branch Manager (Dhanmondi). Asserts results exclude matching items located in Gulshan or Uttara.
3. `test_surface_3_relation_manager_excludes_unauthorized_branch_children`:
   - Inspect Company relation manager for branches/warehouses under scoped user. Asserts unauthorized branch children are omitted.
4. `test_surface_4_export_stream_excludes_unauthorized_branches`:
   - Trigger streaming report export as Dhanmondi manager. Verify CSV/stream content contains strictly Dhanmondi data.
5. `test_surface_5_api_resource_endpoint_denies_cross_branch_access`:
   - Make API request to `/api/v1/branches/{gulshan_id}/orders` with Dhanmondi cashier bearer token. Expects 403 Forbidden.

### Suite 3: Support Access Lifecycle Tests (`tests/Feature/Security/SupportAccessTest.php`)
1. `test_support_access_active_within_four_hours`:
   - Tenant Owner grants 2-hour support session with ticket reference. Platform user connects. Session is authenticated and permitted.
2. `test_support_access_expires_and_is_rejected_after_four_hours`:
   - Fast-forward time past `expires_at` (or simulate > 4 hours). Platform user request is immediately rejected with 403 Session Expired.
3. `test_support_access_is_read_only_by_default_and_rejects_modifications`:
   - Platform user under support session attempts `POST /admin/companies` or `PUT /admin/settings`. Expects 403 Forbidden (Read-Only Elevation).

---

## 4. Numbered List of Commits

1. **Commit 1 (`docs(adr): record ADR-015 for identity, memberships, and platform separation`)**
   - Author `docs/adr/ADR-015-identity-and-tenancy.md` and update `docs/adr/README.md`.
2. **Commit 2 (`test(security): add failing tests for SoD rules, location scoping surfaces, and support access`)**
   - Create `tests/Feature/Security/SegregationOfDutiesTest.php`.
   - Create `tests/Feature/Security/LocationScopingTest.php`.
   - Create `tests/Feature/Security/SupportAccessTest.php`.
   - Run on dev server and demonstrate expected test failures (Red).
3. **Commit 3 (`feat(auth): implement platform_users, users, tenant_memberships, and branches schema`)**
   - Create migrations for `platform_users`, `users`, `tenant_memberships`, `branches`, `user_location_scopes`, and `tenant_support_accesses`.
   - Apply `TenantMigration` RLS helper to tenant tables.
   - Configure dual guards (`web`, `platform`) in `config/auth.php`.
4. **Commit 4 (`feat(filament): configure Tenant Admin and Super-Admin panels on separate guards`)**
   - Configure Tenant Admin panel (`/admin`) on `web` guard with Filament tenancy bound to `TenantContext`.
   - Configure Super-Admin panel (`/platform`) on `platform` guard.
   - Configure Spatie Laravel-Permission in teams mode (`tenant_id`).
5. **Commit 5 (`feat(scoping): implement LocationScope engine and policy enforcement across five surfaces`)**
   - Implement `App\Core\Scoping\LocationScope` global scope and `HasLocationScope` trait.
   - Implement `EnforcesLocationScope` policy concern.
   - Bind location scoping into Filament table queries, global search, relation managers, export actions, and API resources.
6. **Commit 6 (`feat(security): implement SoD conflict policy rules and support access elevation protocol`)**
   - Implement SoD-01 to SoD-04 policy checks.
   - Implement `SupportAccessService`, `VerifySupportAccessActive` middleware, visual banner, and 4-hour expiration enforcement.
7. **Commit 7 (`feat(auth): configure Argon2id, login rate throttling, and mandatory TOTP 2FA`)**
   - Set Argon2id in `config/hashing.php`.
   - Configure `login` rate limiter with exponential backoff.
   - Implement TOTP 2FA enforcement for administrative and finance roles.
8. **Commit 8 (`feat(seed): create DemoTenantSeeder with two companies, three branches, and §3.2 matrix`)**
   - Author `database/seeders/DemoTenantSeeder.php` provisioning Acme Retail BD with all 11 tenant roles, location scopes, and 1 platform super-admin.
9. **Commit 9 (`test(qa): verify all security, SoD, and scoping tests pass on PostgreSQL as app_rw`)**
   - Run Pint, PHPStan Level 8, `db:check-rls`, and full test suite on dev server.
   - Verify 100% green status across all test suites.

---

## 5. Verification & Acceptance Criteria
- `vendor/bin/pint --test` passes with zero formatting issues.
- `vendor/bin/phpstan analyse` passes at Level 8 with zero errors.
- `php artisan db:check-rls --connection=pgsql_owner` passes with zero unshielded tenant tables.
- All SoD tests pass, rejecting all five conflict vectors.
- All location scoping tests pass across all five surfaces (tables, search, relations, exports, APIs).
- Support access strictly expires after 4 hours and enforces read-only mode by default.
- CI workflow on GitHub Actions passes all checks.
