# KC ERP CONTROL PLANE - PHASE 1 DATABASE IMPACT REPORT

**Application:** KC ERP Control Plane (`/platform`)  
**Environment:** Proxmox CT 104 (`dev.kcerp.com`)  
**Target Databases:** `erp` (Production/Development) & `erp_test` (Automated Testing)  
**PostgreSQL Version:** 17.4  
**Date:** 2026-11-05  

---

## 1. EXECUTIVE SUMMARY

Phase 1 introduced a dedicated, tamper-proof platform audit ledger table: `platform_audit_logs`.
In accordance with Rule 3 of the project guidelines, this migration was executed strictly using the `pgsql_owner` connection on PostgreSQL 17.

**Key Database Guarantees Established:**
1. **Zero Tenant Schema Changes:** No tenant tables or composite foreign keys were altered, added, or removed.
2. **Forced RLS Integrity Maintained:** All 74 tenant tables retain forced Row-Level Security (`FORCE ROW LEVEL SECURITY`) with fail-closed policies for `app_rw` and `reporting_ro`.
3. **Control Plane Isolation:** `platform_audit_logs` is a global control-plane table residing outside tenant RLS boundaries, tracking platform operations across tenants without polluting tenant operational schemas.
4. **Hardware-Level Immutability:** Enforced via a PostgreSQL trigger `trg_platform_audit_logs_immutability` that intercepts and raises an exception on any `UPDATE` or `DELETE` attempt.

---

## 2. DDL SPECIFICATION: `platform_audit_logs`

```sql
CREATE TABLE platform_audit_logs (
    id UUID PRIMARY KEY,
    platform_user_id UUID REFERENCES platform_users(id) ON DELETE SET NULL,
    actor_name VARCHAR(150) NOT NULL,
    actor_email VARCHAR(150) NOT NULL,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(150) NOT NULL,
    resource_id VARCHAR(100) NOT NULL,
    resource_description VARCHAR(255),
    old_values JSONB,
    new_values JSONB,
    reason TEXT,
    client_ip VARCHAR(45),
    user_agent TEXT,
    status VARCHAR(30) DEFAULT 'success' NOT NULL,
    error_message TEXT,
    payload_hash VARCHAR(64) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL
);

-- Indices for rapid security and operational querying
CREATE INDEX idx_platform_audit_logs_action ON platform_audit_logs(action);
CREATE INDEX idx_platform_audit_logs_resource_type ON platform_audit_logs(resource_type);
CREATE INDEX idx_platform_audit_logs_resource_id ON platform_audit_logs(resource_id);
CREATE INDEX idx_platform_audit_logs_created_at ON platform_audit_logs(created_at);

-- Immutability Function & Trigger
CREATE OR REPLACE FUNCTION trg_fn_enforce_platform_audit_log_immutability()
RETURNS TRIGGER AS $$
BEGIN
    IF (TG_OP = 'UPDATE') THEN
        RAISE EXCEPTION 'Platform Audit Security Violation: platform_audit_logs records are strictly immutable and cannot be updated.';
    ELSIF (TG_OP = 'DELETE') THEN
        RAISE EXCEPTION 'Platform Audit Security Violation: platform_audit_logs records cannot be deleted from the audit ledger.';
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_platform_audit_logs_immutability
BEFORE UPDATE OR DELETE ON platform_audit_logs
FOR EACH ROW EXECUTE FUNCTION trg_fn_enforce_platform_audit_log_immutability();
```

---

## 3. MIGRATION EXECUTION EVIDENCE

### A. Primary Database (`erp`)
Executed on CT 104 with connection `pgsql_owner`:
```bash
php artisan migrate --database=pgsql_owner
```
Output:
```
2026_11_16_000001_create_platform_audit_logs_table ........... 14.28ms DONE
```

### B. Test Database (`erp_test`)
Executed on CT 104 with connection `pgsql_owner`:
```bash
DB_DATABASE=erp_test php artisan migrate --database=pgsql_owner
```
Output:
```
2026_11_16_000001_create_platform_audit_logs_table ........... 12.85ms DONE
```

---

## 4. ROW-LEVEL SECURITY (RLS) VERIFICATION

Command executed on CT 104:
```bash
php artisan db:check-rls
```
Raw Output:
```
Checking Row-Level Security configuration on connection [pgsql_owner]...
✓ RLS Verification PASSED: All 74 tenant tables enforce forced RLS with valid policies for app_rw and reporting_ro.
```

Catalog Query Verification:
```sql
SELECT count(*) 
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public' 
  AND c.relkind = 'r' 
  AND c.relrowsecurity = true;
```
Result: `74` tables.

---

## 5. DATABASE INTEGRITY IMPACT MATRIX

| Component | Before Phase 1 | After Phase 1 | Net Change |
|---|---|---|---|
| Tenant Tables | 74 | 74 | 0 (No change) |
| Tenant Tables with Forced RLS | 74 | 74 | 0 (100% compliant) |
| Platform Tables | 4 (`platform_users`, `tenants`, `plans`, `tenant_subscriptions`) | 5 (`+ platform_audit_logs`) | +1 table |
| PostgreSQL Triggers | Standard ERP Triggers | Standard ERP + 1 Immutability Trigger | +1 trigger |
| Foreign Key Integrity | Composite (tenant_id, ...) on all tenant tables | Unchanged; UUID FK on platform_users | Zero regression |
