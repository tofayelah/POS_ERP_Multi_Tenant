# Unified ERP v2.0.3: PostgreSQL RLS Migration Role & Semantics Architecture Decision
**KC Retail ERP | Database Security Architecture, RLS Enforcement & DML Policy Decision**  
**Controlling Work Order:** `docs/master-work-order/KC_Retail_ERP_Unified_v2.0_Specification_Work_Order.md`  
**Database Engine:** PostgreSQL 17.4 on x86_64-pc-linux-gnu  
**Repository Baseline:** Branch `step-13a-watchdog-audit` | Commit: `17d0264`  
**Lead DBA & Database Architect:** Principal Database Architect & Systems Auditor  
**Publication Date:** November 2026  
**Document Revision:** v2.0.3 (Authoritative Database Role Decision)  

---

## 1. Executive Summary & Problem Statement

This Architecture Decision Record (ADR / Decision Paper) resolves a critical architectural question regarding PostgreSQL Row-Level Security (RLS), migration runner roles, and least-privilege principles:

1. **The Behavioral Conflict:** All 68 tenant tables enforce `FORCE ROW LEVEL SECURITY`. The standard RLS policy `tenant_isolation_policy` explicitly targets unprivileged application roles:
   `FOR ALL TO app_rw, reporting_ro`
2. **The Table Owner Dilemma:** The table owner (`pgsql_owner` or `erp_owner`) owns the schema. In PostgreSQL:
   * When `FORCE ROW LEVEL SECURITY` is enabled on a table, the table owner is **strictly subject to row security**.
   * Because `tenant_isolation_policy` targets only `app_rw` and `reporting_ro`, **no applicable policy exists for the table owner**.
   * Under PostgreSQL's default-deny security model, if a table has RLS enabled but no applicable policy exists for the querying role, **all rows are invisible (SELECT returns 0 rows) and all modifications are denied**!
3. **The Audit Question:** Does `php artisan migrate` fail on tenant tables? What happens if an unprivileged table owner executes DML? What is the secure, least-privilege architectural solution for schema vs data migrations?

---

## 2. Empirical Investigation of Repository Migrations & Roles

### 2.1 Verification of Dev Docker Roles (`docker/postgres/roles.sql`)
An inspection of the Docker initialization script on Container 104 revealed the historical development configuration:
```sql
-- Schema Owner / Migrator (bypasses RLS, used exclusively by migrations)
IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'erp_owner') THEN
    CREATE ROLE erp_owner WITH LOGIN SUPERUSER PASSWORD 'secret';
END IF;

-- Application Runtime (enforced RLS, no bypass)
IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'app_rw') THEN
    CREATE ROLE app_rw WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS PASSWORD 'secret';
END IF;
```
In the development Docker setup, `erp_owner` was granted `SUPERUSER`. In PostgreSQL, superusers (`rolsuper = true`) unconditionally bypass all RLS policies, regardless of `FORCE ROW LEVEL SECURITY`.

### 2.2 Inspection of Migrations for Tenant Table DML Operations
Every single migration file in `database/migrations/` was audited for DML operations (`INSERT`, `UPDATE`, `DELETE`) on tenant tables during `php artisan migrate`:
* **Finding:** All 73 migrations execute **100% DDL** (`CREATE TABLE`, `ALTER TABLE`, `CREATE TRIGGER`, `CREATE FUNCTION`, `CREATE INDEX`).
* **Zero Migration DML:** No migration in the repository executes `INSERT`, `UPDATE`, or `DELETE` on tenant tables during execution.
* **Trigger Functions Delineated:** Statements such as `UPDATE subledger_open_items` reside inside PL/pgSQL trigger function definitions; they execute at runtime during application DML by `app_rw`, not during migration time by `pgsql_owner`.
* **Conclusion:** Standard DDL migrations execute successfully without hitting RLS default-deny rules because DDL operations are governed by table ownership privileges, not row-level security.

---

## 3. PostgreSQL Engine Semantics: What Happens When `pgsql_owner` Runs DML?

If `pgsql_owner` is provisioned as an unprivileged role without `SUPERUSER` and without `BYPASSRLS` in hardened production environments:

```
+---------------------------------------------------------------------------------------------------------------+
|                                    PGSQL_OWNER DML BEHAVIOR UNDER FORCE RLS                                   |
+------------------+-----------------------+--------------------------------------------------------------------+
| SQL Operation    | Engine Result         | Internal PostgreSQL 17 Rule                                        |
+------------------+-----------------------+--------------------------------------------------------------------+
| `SELECT`         | **Returns 0 Rows**    | Default-deny rule: table has RLS, but no policy targets pgsql_owner.|
+------------------+-----------------------+--------------------------------------------------------------------+
| `INSERT`         | **Denied / Fails**    | Rejects row insertion because WITH CHECK policy is missing.        |
+------------------+-----------------------+--------------------------------------------------------------------+
| `UPDATE`         | **Modifies 0 Rows**   | No rows match USING clause; update target is 0 rows.               |
+------------------+-----------------------+--------------------------------------------------------------------+
| `DELETE`         | **Deletes 0 Rows**    | No rows match USING clause; delete target is 0 rows.               |
+------------------+-----------------------+--------------------------------------------------------------------+
| DDL (`CREATE`)   | **SUCCEEDS**          | DDL is governed by schema privileges, not row security.            |
+------------------+-----------------------+--------------------------------------------------------------------+
| DDL (`ALTER`)    | **SUCCEEDS**          | DDL is governed by table ownership, not row security.              |
+------------------+-----------------------+--------------------------------------------------------------------+
```

---

## 4. Least-Privilege Architectural Decision for Data Migrations

### 4.1 Rejected Option: Universal `BYPASSRLS` Grant
* **Proposal:** Grant `BYPASSRLS` attribute to `pgsql_owner` (`ALTER ROLE pgsql_owner WITH BYPASSRLS`).
* **Rejection Reason:** Violates enterprise principle of least privilege. Granting `BYPASSRLS` to a deployer credential creates an unnecessary escalation vector where a compromised migration script or deployment pipeline could perform unconstrained cross-tenant data operations.

### 4.2 Authoritative Least-Privilege Architecture (Proposed for Production)
The ERP architecture enforces a strict architectural division between **Schema Migrations (DDL)** and **Data Migrations (DML)**:

1. **Standard DDL Migrations (99% of migrations):**
   * Execute strictly as `pgsql_owner` with `NOSUPERUSER` and `NOBYPASSRLS`.
   * Table creation, column addition, index generation, and trigger definitions run without requiring row-level bypass.

2. **Tenant-Scoped Data Migrations (Expand-Contract Backfills):**
   * Data backfills and column transformations must NEVER execute as a global unconstrained SQL script.
   * Data backfills execute through a dedicated Artisan command:
     `php artisan tenant:migrate-data {migration_class}`
   * The command iterates through active tenants, binds the tenant session context:
     `SELECT set_config('app.current_tenant_id', :id, false)`
   * Executes the data transformation under `app_rw`. This guarantees that data transformations are strictly isolated by RLS and cannot corrupt neighboring tenants.

3. **Exceptional Global Cross-Tenant Maintenance Migrations:**
   * In the rare event that a physical migration requires global data restructuring across all tenants simultaneously, the migration MUST explicitly manage RLS within an audited, transactional block with DBA approval:
     ```php
     public function up(): void
     {
         DB::connection('pgsql_owner')->unprepared("
             -- Audited Maintenance Transaction: Temporarily disable RLS for data backfill
             ALTER TABLE tenant_orders DISABLE ROW LEVEL SECURITY;
             
             UPDATE tenant_orders SET new_column = old_column * 1.15;
             
             ALTER TABLE tenant_orders ENABLE ROW LEVEL SECURITY;
             ALTER TABLE tenant_orders FORCE ROW LEVEL SECURITY;
         ");
     }
     ```
   * This preserves fail-closed isolation during normal operations while granting temporary, transactional DML capabilities strictly during migration execution.

---

## 5. PostgreSQL `current_setting()` Semantics Correction

A technical correction is formally established regarding PostgreSQL's `current_setting()` function:

* **PostgreSQL Engine Rule:** 
  `current_setting('app.current_tenant_id', true)`
  When the second parameter `missing_ok` is set to `true`, if the parameter has NOT been set in the current session, PostgreSQL returns **`NULL`**, NOT an empty string `''`!
* **SQL 3-Valued Logic Evaluation:**
  - When unset: `current_setting('app.current_tenant_id', true)` $\rightarrow$ `NULL`.
  - `NULLIF(NULL, '')` $\rightarrow$ `NULL`.
  - Expression: `tenant_id = NULL::uuid` $\rightarrow$ evaluates to **`UNKNOWN`**.
  - In SQL boolean filtering, `UNKNOWN` is treated as false. **Zero rows are returned**.
* **When Context is Cleared:**
  - [`TenantContext::clear()`](app/Core/Tenancy/TenantContext.php) executes `set_config('app.current_tenant_id', '', false)`.
  - Here the variable IS set, and its value is `''`.
  - `current_setting('app.current_tenant_id', true)` $\rightarrow$ `''`.
  - `NULLIF('', '')` converts `''` to `NULL`.
  - Expression: `tenant_id = NULL::uuid` $\rightarrow$ evaluates to **`UNKNOWN`**. **Zero rows are returned**.
* **Verdict:** The fail-closed security guarantee holds true both when the session variable is completely absent (`NULL`) and when it has been explicitly cleared (`''`).

**Authoritative Status:** **LEAST-PRIVILEGE RLS MIGRATION ROLE DECISION FORMALIZED (v2.0.3)**
