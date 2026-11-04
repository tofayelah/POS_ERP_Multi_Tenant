# Step 2: Tenancy, Database Roles, and Row-Level Security (RLS) Implementation Plan

## 1. Executive Summary & Review Scope
- **Step Objective:** Implement multi-tenant data isolation at the PostgreSQL engine level using Row-Level Security (RLS), least-privilege database roles, composite unique/foreign keys, and tenant-scoped application layers (middleware, queue, cache, permissions).
- **Design Sections:** §4.2 (Request Lifecycle & Connection Sizing), §6.1–6.4 (Roles, Composite Keys, Fail-Closed Policies, Non-DB Isolation), §9.1 (Tenants & Companies DDL).
- **Review Items Closed:**
  - `TEN-02` (Critical): Composite foreign keys prevent cross-tenant references at the database engine level.
  - `TEN-03` (High): Least-privilege database roles (`erp_owner`, `app_rw`, `reporting_ro`, `platform_ro`), global exempt table registry, and CI verification.
  - `TEN-04` (High): Tenant context propagation outside HTTP requests (queue job serialization, scheduled command iteration).
  - `TEN-05` (High): Secure tenant resolution via host/subdomain; `X-Tenant-ID` restricted to an assertion check against the authenticated session.
  - `TEN-09` (High): Non-database tenant isolation (tenant-prefixed cache keys, Spatie Permission in teams mode with tenant-aware cache keys).
  - `NEW-04` (High): RLS policies cover both `app_rw` and `reporting_ro`; verification of role coverage in `db:check-rls`.

---

## 2. Architectural Blueprint & Core Deliverables

### A. Database Roles & Connection Architecture (`database/sql/roles.sql`)
- **Roles:**
  1. `erp_owner`: Superuser / Migrator. Owns the database schema and runs DDL migrations. Bypasses RLS. Never used by runtime application code.
  2. `app_rw`: Standard application connection user. Granted `CONNECT`, `USAGE ON SCHEMA public`, `SELECT, INSERT, UPDATE, DELETE` on tenant and public tables, and `USAGE, SELECT` on sequences. **Never granted `BYPASSRLS`**.
  3. `reporting_ro`: Read-only reporting replica user. Granted `CONNECT`, `USAGE`, `SELECT` on tenant tables. Evaluates tenant RLS policies (closing `NEW-04`).
  4. `platform_ro`: Global platform & metering user. Granted `SELECT` only on global platform tables (`tenants`, etc.) and aggregated rollup views. Zero access to tenant transaction ledgers.
- **Default Privileges:**
  Configured so any table created by `erp_owner` automatically grants appropriate DML privileges to `app_rw` and `reporting_ro`.
- **Dual Laravel Connections (`config/database.php`):**
  - `pgsql_owner`: Connects using `DB_OWNER_USERNAME` / `DB_OWNER_PASSWORD`. Used strictly for `php artisan migrate --database=pgsql_owner`.
  - `pgsql`: Connects using `DB_USERNAME` / `DB_PASSWORD` (defaults to `app_rw`). Used by web requests, Horizon queue workers, and automated test suites.

### B. Reusable Migration Helper (`App\Core\Database\Migrations\TenantTableMigration`)
- Reusable schema helper for all tenant-scoped tables:
  - Generates application-managed UUIDv7 primary key: `id UUID PRIMARY KEY`.
  - Generates mandatory tenant foreign key: `tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE RESTRICT`.
  - Adds composite unique index: `UNIQUE (tenant_id, id)` (enabling composite FKs from child tables, closing `TEN-02`).
  - Supports company consistency: optional `company_id UUID` and `UNIQUE (tenant_id, company_id, id)`.
  - Enforces fail-closed RLS SQL:
    ```sql
    ALTER TABLE {table} ENABLE ROW LEVEL SECURITY;
    ALTER TABLE {table} FORCE ROW LEVEL SECURITY;
    CREATE POLICY tenant_isolation_policy ON {table}
        AS RESTRICTIVE
        FOR ALL
        TO app_rw, reporting_ro
        USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid)
        WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid);
    ```

### C. Foundation Tables & Models (§9.1)
- **`tenants` Table:**
  - Global platform table (exempt from RLS, closing `TEN-03`).
  - Columns: `id UUID PRIMARY KEY`, `name`, `subdomain` (unique), `custom_domain` (unique), `subscription_plan`, `status`, `data_residency_region`, timestamps.
  - Model: `App\Models\Tenant` with `HasUuids`.
- **`companies` Table:**
  - Tenant-scoped legal entity table.
  - Columns: `id UUID PRIMARY KEY`, `tenant_id UUID NOT NULL`, `legal_name`, `bin_number`, `base_currency`, `fiscal_year_start_month`, `tax_regime`, timestamps.
  - Constraints: `FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT`, `UNIQUE (tenant_id, id)`.
  - RLS: `ENABLE ROW LEVEL SECURITY`, `FORCE ROW LEVEL SECURITY`, `tenant_isolation_policy` applied.
  - Model: `App\Models\Company` with `HasUuids`.

### D. Tenant Context & Resolution Middleware (§4.2, `TEN-05`)
- **`App\Core\Tenancy\TenantContext`:**
  - Manages active tenant state per request/process lifecycle.
  - Interacts with PostgreSQL connection session:
    ```php
    DB::statement("SELECT set_config('app.current_tenant_id', :tenant_id, false)", [
        'tenant_id' => $tenant->id,
    ]);
    ```
  - `clear()` executes `SELECT set_config('app.current_tenant_id', '', false)`.
- **`App\Core\Http\Middleware\TenantResolutionMiddleware`:**
  1. Resolves tenant via Host header: checks `custom_domain` first, then `{subdomain}.kcerp.com`.
  2. If `X-Tenant-ID` header is provided, it is **never** used to select tenant; it is verified as an assertion check against the resolved tenant / authenticated session (`TEN-05`). Throws 403 on mismatch.
  3. Binds tenant into `TenantContext`.
  4. Resets tenant context in `terminate()` callback.

### E. Background Workers & Scheduled Tasks (§6.1, `TEN-04`)
- **Queue Middleware (`TenantJobMiddleware`):**
  - Captures `TenantContext::id()` when dispatching jobs.
  - When worker executes `handle()`, restores `TenantContext` using `set_config('app.current_tenant_id', $tenantId, false)`.
  - Resets context in a `finally` block upon job completion or failure.
- **Scheduled Tasks:**
  - Standardizes cross-tenant jobs to iterate active tenants explicitly:
    ```php
    Tenant::where('status', 'active')->chunk(50, function ($tenants) {
        foreach ($tenants as $tenant) {
            TenantContext::set($tenant);
            try {
                // Execute job logic under RLS
            } finally {
                TenantContext::clear();
            }
        }
    });
    ```

### F. Non-Database Isolation: Cache & Spatie Permissions (§6.4, `TEN-09`)
- **Tenant-Scoped Cache Decorator:**
  - Automatically prefixes cache keys with `tenant:{tenant_id}:{key}`.
- **Spatie Laravel-Permission:**
  - Configures `teams => true` with `team_foreign_key => 'tenant_id'`.
  - Customizes `cache.key` to include `tenant_id` so cached permissions never collide or bleed across tenants.

### G. Static & Runtime RLS Verification Command (`php artisan db:check-rls`)
- Inspects PostgreSQL catalog tables (`pg_class`, `pg_policy`, `pg_roles`, `information_schema.columns`):
  1. Flags any table with a `tenant_id` column lacking `relrowsecurity = true`.
  2. Flags any table with `relforcerowsecurity = false`.
  3. Validates that policy covers both `app_rw` and `reporting_ro` (`NEW-04`).
  4. Asserts that `app_rw` does not have `rolbypassrls = true`.
  5. Returns exit code 1 if any check fails, integrating directly into CI (`ci.yml`).

---

## 3. Test-First Implementation Suite (`tests/Database`)
All database tests connect exclusively as `app_rw` against PostgreSQL 17 (`erp_test`). Never run on SQLite.

1. **`tests/Database/TenantIsolationTest.php`:**
   - `test_tenant_a_cannot_read_tenant_b_records`: Inserting records for Tenant A and Tenant B; under Tenant A context, Tenant B rows are invisible.
   - `test_tenant_a_cannot_update_or_delete_tenant_b_records`: Tenant A issuing targeted updates against Tenant B's UUID affects 0 rows.
   - `test_tenant_a_cannot_insert_records_for_tenant_b`: Tenant A context attempting to insert with `tenant_id = Tenant B` is rejected by RLS `WITH CHECK` constraint.
   - `test_unauthenticated_or_empty_context_returns_zero_rows`: Without `app.current_tenant_id` set, all queries against tenant tables return zero rows (fail-closed).
   - `test_connection_reset_clears_tenant_context`: Executing queries, terminating/resetting context, and querying again on the same connection returns zero rows.
2. **`tests/Database/CompositeForeignKeyTest.php` (`TEN-02`):**
   - `test_cross_tenant_foreign_key_reference_is_rejected`: Creating Company under Tenant B; attempting to insert a child record under Tenant A referencing Tenant B's Company ID fails with PostgreSQL FK violation.
3. **`tests/Database/QueueAndJobIsolationTest.php` (`TEN-04`):**
   - `test_queued_job_restores_tenant_context_and_cleans_up`: Dispatched job serializes `tenant_id`, executes with correct RLS context, and clears context on termination.
4. **`tests/Database/CacheAndPermissionIsolationTest.php` (`TEN-09`):**
   - `test_cache_keys_are_tenant_partitioned`: Values cached under Tenant A with identical key are isolated from Tenant B.
   - `test_permission_cache_does_not_bleed_across_tenants`: Spatie permissions assigned in Tenant A do not grant authorization in Tenant B.

---

## 4. Numbered Commit Sequence

```
commit 1: chore(infra): configure postgresql roles, default privileges, and dual connections
- Add database/sql/roles.sql with erp_owner, app_rw, reporting_ro, platform_ro.
- Configure config/database.php with pgsql_owner and pgsql (app_rw).
- Update .env.example, phpunit.xml, and docker/postgres/init.sql.
- Review IDs closed: TEN-03.

commit 2: feat(tenancy): add reusable tenant table migration helper and DDL for tenants and companies
- Create App\Core\Database\Migrations\TenantTableMigration.
- Add migration for global tenants table (UUIDv7, exempt from RLS).
- Add migration for companies table (UUIDv7, tenant_id, composite unique, forced RLS).
- Add App\Models\Tenant and App\Models\Company.
- Review IDs closed: TEN-02, NEW-04.

commit 3: feat(tenancy): implement TenantContext and host-based TenantResolutionMiddleware
- Implement App\Core\Tenancy\TenantContext with set_config session scoping.
- Implement App\Core\Http\Middleware\TenantResolutionMiddleware (host/subdomain first, X-Tenant-ID assertion).
- Register middleware and terminate callback cleanup.
- Review IDs closed: TEN-05.

commit 4: feat(tenancy): implement queue job tenant middleware and scheduled task loop
- Create App\Core\Queue\Middleware\TenantJobMiddleware.
- Create App\Core\Queue\TenantAwareJob trait.
- Review IDs closed: TEN-04.

commit 5: feat(tenancy): configure tenant-prefixed cache store and Spatie Permission teams mode
- Implement tenant-aware cache key prefixing.
- Configure Spatie Permission with teams mode and tenant-isolated cache key.
- Review IDs closed: TEN-09.

commit 6: feat(console): add db:check-rls command for catalog verification
- Create App\Console\Commands\CheckRlsCommand (db:check-rls).
- Verify relrowsecurity, relforcerowsecurity, and role coverage (app_rw, reporting_ro).
- Review IDs closed: TEN-03, NEW-04.

commit 7: test(database): add comprehensive RLS, composite FK, queue, and cache isolation test suite
- Add tests/Database/TenantIsolationTest.php.
- Add tests/Database/CompositeForeignKeyTest.php.
- Add tests/Database/QueueAndJobIsolationTest.php.
- Add tests/Database/CacheAndPermissionIsolationTest.php.
- Run test suite on dev server (Container 104) as app_rw.
- Review IDs closed: TEN-02, TEN-03, TEN-04, TEN-05, TEN-09, NEW-04.

commit 8: ci: activate Step 2 CI checks in github workflows
- Enable Step 2 lines in .github/workflows/ci.yml (roles.sql execution, pgsql_owner migration, db:check-rls).
- Ensure all CI checks pass cleanly.
```

---

## 5. Verification Gate on Dev Server (Container 104)
Before merging Step 2:
1. `docker compose exec postgres psql -U erp_owner -d erp_test -f database/sql/roles.sql`
2. `php artisan migrate --database=pgsql_owner --force`
3. `php artisan db:check-rls`
4. `vendor/bin/pint --test`
5. `vendor/bin/phpstan analyse`
6. `php artisan test tests/Database` (Connecting as `app_rw` on PostgreSQL 17)
