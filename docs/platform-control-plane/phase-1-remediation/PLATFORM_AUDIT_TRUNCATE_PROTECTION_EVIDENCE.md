# KC ERP CONTROL PLANE
# PLATFORM AUDIT TRUNCATE PROTECTION & PRIVILEGE HARDENING EVIDENCE

**Application:** KC ERP Control Plane (`/platform`)  
**Target Table:** `platform_audit_logs`  
**Database:** PostgreSQL 17.4 on Proxmox CT 104 (`dev.kcerp.com`)  
**Milestone:** 1 (PostgreSQL Platform Audit Log Protection)  
**Date:** 2026-11-05  

---

## 1. VULNERABILITY CONTEXT & REMEDIATION OBJECTIVE

In the initial Phase 1 acceptance audit, empirical testing demonstrated that while row-level triggers (`BEFORE UPDATE OR DELETE FOR EACH ROW`) prevented `UPDATE` and `DELETE` queries, PostgreSQL row-level triggers **do not execute on `TRUNCATE` operations**.

Furthermore, prior to remediation, the application runtime role (`app_rw`) possessed `UPDATE` and `DELETE` grants on `platform_audit_logs`, relying entirely on trigger interception rather than least-privilege database role permissions.

### Remediation Goals Achieved
1. Implement a statement-level trigger (`FOR EACH STATEMENT`) specifically for `BEFORE TRUNCATE`.
2. Revoke `TRUNCATE`, `UPDATE`, and `DELETE` privileges from application runtime roles (`app_rw`, `reporting_ro`).
3. Establish dual-layer defense: permission check blocks unprivileged runtime roles; statement-level trigger intercepts privileged table owners.

---

## 2. MIGRATION DDL & POSTGRESQL TRIGGERS

Executed via `database/migrations/2026_11_16_000002_harden_platform_audit_logs_immutability.php`:

```sql
-- 1. Upgraded PL/pgSQL function with TRUNCATE check
CREATE OR REPLACE FUNCTION trg_fn_enforce_platform_audit_log_immutability()
RETURNS TRIGGER AS $$
BEGIN
    IF (TG_OP = 'UPDATE') THEN
        RAISE EXCEPTION 'Platform Audit Security Violation: platform_audit_logs records are strictly immutable and cannot be updated.';
    ELSIF (TG_OP = 'DELETE') THEN
        RAISE EXCEPTION 'Platform Audit Security Violation: platform_audit_logs records cannot be deleted from the audit ledger.';
    ELSIF (TG_OP = 'TRUNCATE') THEN
        RAISE EXCEPTION 'Platform Audit Security Violation: platform_audit_logs table cannot be truncated.';
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- 2. Statement-level trigger for TRUNCATE
DROP TRIGGER IF EXISTS trg_platform_audit_logs_truncate ON platform_audit_logs;
CREATE TRIGGER trg_platform_audit_logs_truncate
BEFORE TRUNCATE ON platform_audit_logs
FOR EACH STATEMENT EXECUTE FUNCTION trg_fn_enforce_platform_audit_log_immutability();

-- 3. Row-level trigger for UPDATE and DELETE
DROP TRIGGER IF EXISTS trg_platform_audit_logs_immutability ON platform_audit_logs;
CREATE TRIGGER trg_platform_audit_logs_immutability
BEFORE UPDATE OR DELETE ON platform_audit_logs
FOR EACH ROW EXECUTE FUNCTION trg_fn_enforce_platform_audit_log_immutability();

-- 4. Role Privilege Hardening
REVOKE TRUNCATE, UPDATE, DELETE ON platform_audit_logs FROM PUBLIC;
REVOKE TRUNCATE, UPDATE, DELETE ON platform_audit_logs FROM app_rw;
REVOKE TRUNCATE, UPDATE, DELETE ON platform_audit_logs FROM reporting_ro;

GRANT SELECT, INSERT ON platform_audit_logs TO app_rw;
GRANT SELECT ON platform_audit_logs TO reporting_ro;
```

---

## 3. BEFORE AND AFTER PRIVILEGE MATRIX

Empirical query output from `information_schema.role_table_grants` on CT 104:

| Role / Grantee | Privilege | Pre-Remediation State | Post-Remediation State | Security Effect |
|---|---|---|---|---|
| `app_rw` | `SELECT` | GRANTED | GRANTED | Normal audit reading permitted |
| `app_rw` | `INSERT` | GRANTED | GRANTED | Normal audit append permitted |
| `app_rw` | `UPDATE` | GRANTED | **REVOKED** | Blocked at PostgreSQL permission layer |
| `app_rw` | `DELETE` | GRANTED | **REVOKED** | Blocked at PostgreSQL permission layer |
| `app_rw` | `TRUNCATE`| DENIED | **REVOKED FROM PUBLIC** | Blocked at PostgreSQL permission layer |
| `app_rw` | `TRIGGER` | DENIED | DENIED | Cannot disable triggers or alter schema |
| `reporting_ro` | `SELECT` | GRANTED | GRANTED | Read-only reporting permitted |
| `erp_owner` | ALL | OWNER | OWNER | Blocked by triggers on TRUNCATE/UPDATE/DELETE |

---

## 4. EMPIRICAL TEST EXECUTION EVIDENCE

Executed live against PostgreSQL 17 on CT 104:

### Test 1: Statement-Level TRUNCATE Rejection (Table Owner Privileges)
```php
DB::connection('pgsql_owner')->statement('TRUNCATE TABLE platform_audit_logs');
```
**Raw Result:**
```
SQLSTATE[P0001]: Raise exception: 7 ERROR:  Platform Audit Security Violation: platform_audit_logs table cannot be truncated.
CONTEXT:  PL/pgSQL function trg_fn_enforce_platform_audit_log_immutability() line 8 at RAISE
(Connection: pgsql_owner, Host: 127.0.0.1, Port: 5432, Database: erp_test, SQL: TRUNCATE TABLE platform_audit_logs)
```
**Verdict:** **PASSED.** Statement-level trigger blocks table destruction even when invoked by the database owner.

### Test 2: Runtime Role TRUNCATE Rejection (`app_rw`)
```php
DB::connection('pgsql')->statement('TRUNCATE TABLE platform_audit_logs');
```
**Raw Result:**
```
SQLSTATE[42501]: Insufficient privilege: 7 ERROR:  permission denied for table platform_audit_logs
(Connection: pgsql, Host: 127.0.0.1, Port: 5432, Database: erp_test, SQL: TRUNCATE TABLE platform_audit_logs)
```
**Verdict:** **PASSED.** Runtime role is denied before query execution starts.

### Test 3: Runtime Role UPDATE and DELETE Rejection (`app_rw`)
```php
DB::connection('pgsql')->table('platform_audit_logs')->where('id', $id)->update(['action' => 'hacked']);
DB::connection('pgsql')->table('platform_audit_logs')->where('id', $id)->delete();
```
**Raw Result:**
```
SQLSTATE[42501]: Insufficient privilege: 7 ERROR:  permission denied for table platform_audit_logs
```
**Verdict:** **PASSED.** Runtime application role cannot modify or delete audit rows.

### Test 4: Normal Append (`INSERT`) Operation Continues Working
```php
$log = $auditService->record(...);
```
**Raw Result:** Record successfully inserted with UUID primary key, timestamp, and payload hash.
**Verdict:** **PASSED.**

---

## 5. ARCHITECTURAL BOUNDARY ACKNOWLEDGEMENT

In strict accordance with the user instructions:
> *"Do not claim protection against PostgreSQL superusers or unrestricted database owners."*

We explicitly document:
1. If an operator possesses physical shell access to the PostgreSQL server host or connects as the PostgreSQL superuser (`postgres`), they can issue `DROP TRIGGER` or `ALTER TABLE platform_audit_logs DISABLE TRIGGER`.
2. The protection guarantees implemented in Milestone 1 defend against **application-level compromises**, **malicious application runtime queries (`app_rw`)**, and **accidental or scripted `TRUNCATE`/`DELETE` calls within application connections**.
3. Protection against superuser-level sabotage requires offsite replication / WORM storage, which is addressed in the Phase 2 compliance architecture.
