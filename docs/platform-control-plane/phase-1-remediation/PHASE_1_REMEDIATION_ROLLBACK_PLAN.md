# KC ERP CONTROL PLANE
# PHASE 1 REMEDIATION ROLLBACK PLAN & PROCEDURES

**Application:** KC ERP Control Plane (`/platform`)  
**Scope:** Rollback Procedures for Phase 1 Remediation  
**Baseline Commit:** `b763a38e1415669a0d67b6b3a70d592f4d0733d0`  
**Date:** 2026-11-05  

---

## 1. ROLLBACK OBJECTIVES & SAFETY GUARANTEES

In the event that Phase 1 Remediation introduces unforeseen operational anomalies or is rejected by human stakeholders, this document provides the exact, non-destructive step-by-step procedures to revert to the baseline commit `b763a38`.

### Safety Guarantees
1. **Zero Tenant ERP Impact:** Platform audit table rollbacks have zero effect on tenant general ledgers, inventory stock movements, sales transactions, or POS terminals.
2. **Historical Data Preservation:** Rollback does not drop or truncate `platform_audit_logs`.
3. **Database Backup Restorability:** A JSON snapshot of `platform_audit_logs` is preserved in `database/backups/`.

---

## 2. DATABASE SCHEMA ROLLBACK PROCEDURE

### Step 1: Rollback Migration on `erp` Development Database
Execute on CT 104 using `pgsql_owner` connection:

```bash
php artisan migrate:rollback --step=1 --database=pgsql_owner
```

**Expected Effect of `down()`:**
1. Drops statement-level trigger `trg_platform_audit_logs_truncate`.
2. Restores PL/pgSQL function `trg_fn_enforce_platform_audit_log_immutability()` to its Phase 1 state (row-level `UPDATE`/`DELETE` check only).
3. Drops index `idx_platform_audit_logs_key_version`.
4. Drops column `key_version`.

### Step 2: Rollback Migration on `erp_test` Database
Execute on CT 104:

```bash
DB_DATABASE=erp_test php artisan migrate:rollback --step=1 --database=pgsql_owner
```

### Step 3: Re-grant Phase 1 Privileges (if required)
If necessary, restore runtime permissions to match Phase 1:

```sql
GRANT UPDATE, DELETE ON platform_audit_logs TO app_rw;
```

---

## 3. CODEBASE & GIT REVERSION PROCEDURE

### Step 1: Switch Back to Baseline Branch
On CT 104 and local development workstation:

```bash
git checkout control-plane-phase-1
git pull --ff-only origin control-plane-phase-1
```

### Step 2: Verify Exact Baseline Commit
```bash
git rev-parse HEAD
# Must output: b763a38e1415669a0d67b6b3a70d592f4d0733d0
```

### Step 3: Revert Environment Variables
In `.env` on CT 104, remove the remediation keys using sed or an editor:
- Remove `PLATFORM_AUDIT_HMAC_SECRET`
- Remove `PLATFORM_AUDIT_KEY_VERSION`
- Remove `POS_BRIDGE_HEALTH_URL`

### Step 4: Clear Application Cache
```bash
php artisan config:clear
php artisan cache:clear
```

---

## 4. POST-ROLLBACK VERIFICATION

1. Execute test suite: `php artisan test tests/Feature/Platform/`.
2. Execute RLS catalog check: `php artisan db:check-rls`.
3. Verify baseline functionality of `/platform` dashboard.
