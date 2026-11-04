# Unified ERP v2.0.1: Database Architecture Review
**KC Retail ERP | Relational Schema, Row-Level Security & Storage Engine Audit**  
**Controlling Work Order:** `docs/master-work-order/KC_Retail_ERP_Unified_v2.0_Specification_Work_Order.md`  
**Database Engine:** PostgreSQL 17.4 on x86_64-pc-linux-gnu  
**Repository Baseline:** Branch `step-13a-watchdog-audit` (Commit: `17d0264`)  
**Auditor:** Principal Database Architect & Systems Auditor  
**Publication Date:** November 2026  
**Document Revision:** v2.0.1 (Empirical Code Review & Technical Correction)  

---

## 1. Executive Database Summary & Physical Engine Baseline

KC Retail ERP Unified v2.0.1 runs on **PostgreSQL 17.4**, deployed in a containerized environment (Proxmox LXC Container 104, `dev.kcerp.com`). 

Unlike conventional multi-tenant architectures that rely on fragile ORM query scopes (where a single omitted `where('tenant_id', ...)` leaks cross-tenant records), KC Retail ERP enforces isolation at the database engine level using **PostgreSQL Row-Level Security (RLS)** coupled with **composite primary and foreign keys** and **database-level PL/pgSQL immutability triggers**.

```
+---------------------------------------------------------------------------------------------------------------+
|                                      POSTGRESQL DATABASE METRICS AT A GLANCE                                  |
+------------------------------------+----------------------------------+---------------------------------------+
| Metric                             | Value                            | Audit Verification Source             |
+------------------------------------+----------------------------------+---------------------------------------+
| Database Engine & Version          | PostgreSQL 17.4 (Ubuntu Linux)   | Verified on dev container CT 104      |
| Application Connection Role        | `app_rw` (Unprivileged DML)      | config/database.php                   |
| DDL / Migration Connection Role    | `pgsql_owner` (Privileged DDL)   | config/database.php                   |
| Reporting Connection Role          | `reporting_ro` (Read-only DML)   | config/database.php                   |
| Total Tables in Schema             | 73 tables (68 Tenant + 5 Shared) | information_schema.tables             |
| Tables with Forced RLS             | 68 / 68 Tenant Tables (100%)     | php artisan db:check-rls              |
| Primary Key Scheme                 | UUIDv7 (Time-ordered 128-bit)    | app/Core/Database/TenantMigration.php |
| Foreign Key Structure              | Composite (tenant_id, id)        | database/migrations/*.php             |
| Immutability Triggers              | 6 Transactional Table Families   | pg_trigger / database/migrations      |
| Automated Test Suite               | 403 Tests / 6,274 Assertions     | php artisan test --compact (PASS)     |
+------------------------------------+----------------------------------+---------------------------------------+
```

---

## 2. Unprivileged Runtime Role Separation: `pgsql_owner` vs `app_rw`

A fundamental security principle of the database architecture is the strict division of database roles:

1. **`pgsql_owner` (Migration & DDL Owner):**
   * Owns all tables, schemas, functions, sequences, and triggers.
   * Used exclusively during `php artisan migrate` or schema deployment scripts.
   * Application web workers, API controllers, and queue jobs **never** connect as `pgsql_owner`.

2. **`app_rw` (Application Runtime User):**
   * Granted only `SELECT`, `INSERT`, `UPDATE`, `DELETE` on tenant tables.
   * Explicitly denied `TRUNCATE`, `DROP`, `ALTER`, and schema alteration permissions.
   * Bounded by Row-Level Security policies on every tenant table.
   * If an SQL injection vulnerability were ever introduced in application code, the attacker cannot alter tables, drop indexes, disable triggers, or read across tenant boundaries.

3. **`reporting_ro` (Read-Only Analytical User):**
   * Granted strictly `SELECT` privileges, subject to identical RLS tenant isolation policies.

---

## 3. Row-Level Security (RLS) Implementation Truth

### 3.1 Actual Helper Implementation in `TenantMigration.php`
Tenant tables are created via the abstract migration base class [`TenantMigration`](app/Core/Database/TenantMigration.php). The actual code executed during migration is:

```php
abstract class TenantMigration extends Migration
{
    protected function createTenantTable(string $table, Closure $callback, string $connection = 'pgsql_owner'): void
    {
        $schema = Schema::connection($connection);

        $schema->create($table, function (Blueprint $blueprint) use ($callback, $table) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('tenant_id');

            $callback($blueprint);

            $blueprint->unique(['tenant_id', 'id'], "uq_{$table}_tenant_id");
            $blueprint->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('restrict');
        });

        $this->enableForcedRls($table, $connection);
    }

    protected function enableForcedRls(string $table, string $connection = 'pgsql_owner'): void
    {
        DB::connection($connection)->statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::connection($connection)->statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");

        DB::connection($connection)->statement("
            CREATE POLICY tenant_isolation_policy ON {$table}
                FOR ALL
                TO app_rw, reporting_ro
                USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid)
        ");
    }
}
```

### 3.2 Key Technical Observations on the RLS Policy
1. **Permissive Policy Targeted by Role:**
   The policy is created without the `AS RESTRICTIVE` clause; in PostgreSQL, policies default to **`PERMISSIVE`**. However, the policy explicitly specifies `TO app_rw, reporting_ro`. This means that queries executed by `app_rw` or `reporting_ro` are subject to the policy, while superusers and table owners (`pgsql_owner`) are exempt during migrations.
2. **`FORCE ROW LEVEL SECURITY`:**
   Enabling `FORCE ROW LEVEL SECURITY` guarantees that even if a table owner executes queries via `app_rw`, or if table ownership is reassigned, RLS cannot be bypassed.
3. **Fail-Closed Session Context:**
   The policy uses `NULLIF(current_setting('app.current_tenant_id', true), '')::uuid`. 
   - If `app.current_tenant_id` has not been set (or was reset to `''`), `current_setting(..., true)` returns `''` (missing_ok = true).
   - `NULLIF('', '')` evaluates to `NULL`.
   - `tenant_id = NULL` evaluates to `UNKNOWN` in SQL 3-valued logic.
   - Result: Queries executed without setting the tenant context return **0 rows** and fail closed.

---

## 4. Connection Pooling, Session Context & Leakage Prevention

### 4.1 Tenant Context Lifecycle
The application manages tenant session context via [`TenantContext`](app/Core/Tenancy/TenantContext.php):

```php
public static function set(?Tenant $tenant, string $connection = 'pgsql'): void
{
    self::$currentTenant = $tenant;
    $tenantId = $tenant !== null ? $tenant->id : '';

    if (function_exists('setPermissionsTeamId')) {
        setPermissionsTeamId($tenant !== null ? $tenant->id : null);
    }

    DB::connection($connection)->statement(
        "SELECT set_config('app.current_tenant_id', :tenant_id, false)",
        ['tenant_id' => $tenantId]
    );
}

public static function clear(string $connection = 'pgsql'): void
{
    self::$currentTenant = null;

    if (function_exists('setPermissionsTeamId')) {
        setPermissionsTeamId(null);
    }

    DB::connection($connection)->statement(
        "SELECT set_config('app.current_tenant_id', '', false)"
    );
}
```

### 4.2 Connection Pool Leakage Risk & Mitigations
* **The Risk:** When database connections are pooled (e.g., via PgBouncer transaction/session pooling, or persistent PHP-FPM / Laravel Octane workers), an uncleaned `app.current_tenant_id` session setting could theoretically persist to a subsequent request belonging to a different tenant.
* **Mitigation 1 (Middleware Teardown):** [`TenantResolutionMiddleware`](app/Core/Http/Middleware/TenantResolutionMiddleware.php) executes `TenantContext::clear()` in its `terminate()` phase after HTTP responses are flushed.
* **Mitigation 2 (Queue Job Lifecycle):** [`TenantJobMiddleware`](app/Core/Queue/Middleware/TenantJobMiddleware.php) wraps every queued background job, explicitly setting `TenantContext::setById($job->tenantId)` before job execution and calling `TenantContext::clear()` in a `finally` block upon completion or failure.
* **Mitigation 3 (Integrity Watchdog & Commands):** All CLI commands (`PilotDailyCheckCommand`, `IntegrityWatchdogService`) invoke `TenantContext::clear()` after inspecting each tenant.

---

## 5. Composite Keys & Company Consistency Architecture

### 5.1 Primary Key & Foreign Key Conventions
1. **Primary Keys:** Every tenant table defines a UUID primary key (`id UUID PRIMARY KEY`) alongside a unique constraint `UNIQUE (tenant_id, id)`.
2. **Composite Relational Constraints:** Foreign keys between tenant-scoped entities include `tenant_id`:
   ```sql
   CONSTRAINT fk_journal_lines_entry 
       FOREIGN KEY (tenant_id, journal_entry_id) 
       REFERENCES journal_entries (tenant_id, id) 
       ON DELETE RESTRICT
   ```
   This prevents relational crossing where an entity in Tenant A points to an entity in Tenant B.

### 5.2 Company-Level Consistency (`company_id`)
In multi-company retail groups, a single tenant may operate multiple registered legal entities (companies). 
* **Enforced Tables:** Critical financial documents (`journal_entries`, `journal_lines`, `sales_invoices`, `sales_invoice_items`, `purchase_orders`, `goods_receipts`) enforce composite uniqueness and foreign keys on `(tenant_id, company_id, id)` or enforce matching company IDs via database triggers.
* **Database Trigger Protection:** In [`2026_11_03_000007_create_journal_immutability_triggers.php`](database/migrations/2026_11_03_000007_create_journal_immutability_triggers.php), `trg_fn_enforce_journal_lines_immutability()` prevents company drift:
  ```sql
  IF (NEW.company_id <> OLD.company_id) THEN
      RAISE EXCEPTION 'Financial Integrity Violation: Cannot change company_id on a journal line.';
  END IF;
  ```
* **Identified Schema Gap:** Master catalog entities (`products`, `categories`, `brands`) are scoped by `tenant_id` but currently do NOT require `company_id`. While this allows catalog sharing across sister companies under the same tenant, company-specific pricing rules must be enforced via `price_lists` rather than database foreign keys.

---

## 6. Financial & Document Immutability Triggers

The database does NOT rely on application-level Eloquent model events (which can be bypassed by `DB::table()->update()`, raw SQL, or background jobs) to enforce financial immutability. Immutability is strictly enforced by **PostgreSQL BEFORE UPDATE OR DELETE triggers** written in PL/pgSQL.

### 6.1 General Ledger Immutability Triggers
Implemented in [`database/migrations/2026_11_03_000007_create_journal_immutability_triggers.php`](database/migrations/2026_11_03_000007_create_journal_immutability_triggers.php):

1. **`trg_fn_journal_entry_insert_guard()`:**
   Enforces that journal entries cannot be created directly in `is_posted = TRUE` state:
   ```sql
   IF (NEW.is_posted = TRUE) THEN
       RAISE EXCEPTION 'Financial Integrity Violation: Journal entries must be created in draft state (is_posted = FALSE) first.';
   END IF;
   ```

2. **`trg_fn_enforce_journal_entry_immutability()`:**
   - On `DELETE`: Rejects deletion if `OLD.is_posted = TRUE`. Mandates reversing entry.
   - On `UPDATE`: If `OLD.is_posted = TRUE`, rejects any modification.
   - On Transition (`OLD.is_posted = FALSE AND NEW.is_posted = TRUE`):
     * Verifies that `accounting_periods` status is `'open'`.
     * Verifies that `posting_date` falls within the accounting period's start and end dates.
     * Verifies that line count $\ge 2$.
     * Verifies that $\sum(\text{base\_debit\_amount}) = \sum(\text{base\_credit\_amount})$.
     * If `is_reversal = TRUE`, verifies that the target journal exists, is posted, and belongs to the exact same company.

3. **`trg_fn_enforce_journal_lines_immutability()`:**
   - Blocks reassigning `journal_entry_id` or `company_id`.
   - Checks if the parent `journal_entries` is already posted; if posted, rejects line insertion, modification, or deletion.

### 6.2 Stock Movements Immutability Trigger
Implemented in [`database/migrations/2026_11_05_000004_create_stock_movements_immutability_trigger.php`](database/migrations/2026_11_05_000004_create_stock_movements_immutability_trigger.php):

```sql
CREATE OR REPLACE FUNCTION trg_fn_enforce_stock_movements_immutability()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION 'Inventory Audit Violation: Stock movements are an append-only ledger. Edits and deletions are prohibited (Movement ID: %).', OLD.id;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_stock_movements_immutability
BEFORE UPDATE OR DELETE ON stock_movements
FOR EACH ROW
EXECUTE FUNCTION trg_fn_enforce_stock_movements_immutability();
```
In addition, the migration executes:
```sql
REVOKE TRUNCATE ON stock_movements FROM app_rw;
REVOKE TRUNCATE ON stock_movements FROM reporting_ro;
```

### 6.3 Sales Invoice Statutory Immutability Trigger
Implemented in [`database/migrations/2026_11_09_000004_create_sales_invoice_immutability_triggers.php`](database/migrations/2026_11_09_000004_create_sales_invoice_immutability_triggers.php) and updated in [`2026_11_13_000002_update_sales_invoice_immutability_trigger_for_sync_metrics.php`](database/migrations/2026_11_13_000002_update_sales_invoice_immutability_trigger_for_sync_metrics.php):

- **On `DELETE`:**
  * If `status = 'issued'`, raises exception: *Statutory Audit Violation: Issued Sales Invoice cannot be deleted. Issue a Mushak 6.7 Credit Note.*
  * If `status = 'cancelled'`, raises exception: *Cancelled Sales Invoice cannot be deleted. Audit ledger records must be retained.*
  * If `status = 'draft'`, raises exception: *Draft Sales Invoice cannot be deleted. Use the void flow to preserve gapless sequence.*
- **On `UPDATE`:**
  * Cancelled invoices are strictly immutable.
  * On Draft to Issued transition: verifies at least 1 line item, verifies $\text{subtotal} = \sum(\text{line\_total})$, verifies $\text{tax\_amount} = \sum(\text{tax})$, verifies $\text{total\_payable} = \text{subtotal} - \text{discount} + \text{tax} + \text{SD}$.
  * Once `status = 'issued'`, all columns are immutable with exactly one exception: a one-time fiscal enrichment whitelist allowing `fiscal_signature` and `sdc_invoice_number` to transition from `NULL` to non-null when signed by the NBR SDC hardware device. Overwriting existing non-null fiscal fields is blocked.

### 6.4 Procurement & Document Chain Immutability
Implemented in [`database/migrations/2026_11_12_000007_create_document_chains_triggers.php`](database/migrations/2026_11_12_000007_create_document_chains_triggers.php):
- `delivery_challans`: Confirmed challans cannot be deleted; core attributes cannot be modified.
- `goods_receipts`: Received or billed GRNs cannot be deleted. Corrections require return challans or debit notes.

---

## 7. Storage Engine & Index Optimization

### 7.1 UUIDv7 Index Locality
All tables utilize sequential 128-bit UUIDv7 primary keys. UUIDv7 embeds a 48-bit UNIX millisecond timestamp in the high-order bits:
- New rows are inserted monotonically into the rightmost leaf of PostgreSQL B-Tree indexes.
- Index page splits and random disk I/O are completely eliminated compared to UUIDv4.
- High-efficiency index range scans for date-based reporting queries.

### 7.2 Partial & Composite Indexes
The schema leverages targeted partial indexes to optimize active operational queries without bloating disk storage:
1. `CREATE INDEX idx_outbox_unprocessed ON outbox_messages (created_at) WHERE processed_at IS NULL;`
2. `CREATE INDEX idx_stock_layers_active ON stock_layers (product_id, warehouse_id, layer_date) WHERE remaining_quantity > 0;`
3. `CREATE INDEX idx_journal_lines_account_period ON journal_lines (tenant_id, account_id, period_id);`

---

## 8. Database Architecture Gaps & Recommendations

```
+---------------------------------------------------------------------------------------------------------------+
|                                      DATABASE ARCHITECTURE AUDIT GAPS & REMEDIATION                           |
+----+--------------------------------+----------+--------------------------------------------------------------+
| ID | Area                           | Severity | Technical Description & Remediation Recommendation           |
+----+--------------------------------+----------+--------------------------------------------------------------+
| G1 | Global Product Catalog Scoping | MEDIUM   | `products` table does not enforce `company_id`. Sister       |
|    |                                |          | companies share catalog SKUs. Documented as intended design; |
|    |                                |          | company-specific prices must use `price_lists`.              |
| G2 | Partitioning on Audit Logs     | LOW      | `audit_logs` and `stock_movements` will grow rapidly.        |
|    |                                |          | Recommend implementing PostgreSQL declarative range          |
|    |                                |          | partitioning by year/month prior to multi-store rollout.     |
| G3 | Read-Replica Routing           | LOW      | `reporting_ro` role exists in database but dynamic query     |
|    |                                |          | splitting is not yet configured in config/database.php.      |
+----+--------------------------------+----------+--------------------------------------------------------------+
```

**Related Document Links:**
- Acceptance Criteria Baseline: [ERP_ACCEPTANCE_CRITERIA_v2.0.1.md](docs/unified-v2/ERP_ACCEPTANCE_CRITERIA_v2.0.1.md)
- Security & Compliance Audit: [ERP_SECURITY_AND_COMPLIANCE_AUDIT_v2.0.1.md](docs/unified-v2/ERP_SECURITY_AND_COMPLIANCE_AUDIT_v2.0.1.md)
- Architecture Decision Register: [ERP_ARCHITECTURE_DECISION_REGISTER_v2.0.1.md](docs/unified-v2/ERP_ARCHITECTURE_DECISION_REGISTER_v2.0.1.md)
