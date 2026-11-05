# KC ERP CONTROL PLANE
# PLATFORM AUDIT IMMUTABILITY & SECURITY VERIFICATION REPORT

**Application:** KC ERP Control Plane (`/platform`)  
**Target Table:** `platform_audit_logs`  
**Database:** PostgreSQL 17.4 on Proxmox CT 104 (`dev.kcerp.com`)  
**Date:** 2026-11-05  
**Verification Method:** Empirical SQL Execution, Role Inspection, and Trigger Stress-Testing  

---

## 1. COMPONENT INSPECTION

### A. Database Migration (`2026_11_16_000001_create_platform_audit_logs_table.php`)
The migration defines the audit ledger table and registers a PL/pgSQL trigger function on connection `pgsql_owner`:
```sql
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

### B. Eloquent Model Guards (`App\Models\PlatformAuditLog`)
Dual-layer defense is implemented in the model boot sequence:
```php
protected static function booted(): void
{
    static::updating(function () {
        throw new \RuntimeException('PlatformAuditLog is strictly immutable and cannot be modified.');
    });

    static::deleting(function () {
        throw new \RuntimeException('PlatformAuditLog is strictly immutable and cannot be deleted.');
    });
}
```

### C. Audit Service & PII Redaction (`App\Core\Platform\Services\PlatformAuditService`)
- Sensitive attributes (`password`, `admin_password`, `two_factor_secret`, `two_factor_recovery_codes`, `remember_token`, `secret`, `token`, `api_key`) are recursively replaced with `[REDACTED]`.
- An SHA-256 HMAC payload signature is computed and stored in `payload_hash`.

### D. Filament Resource (`App\Filament\Platform\Resources\AuditLogs\PlatformAuditLogResource`)
- Read-only resource registered under `SECURITY & GOVERNANCE`.
- All form inputs are disabled (`disabled()`).
- Form actions omit `CreateAction`, `EditAction`, and `DeleteAction`.

---

## 2. EMPIRICAL SECURITY TESTING ON CT 104

Live tests were executed against PostgreSQL 17 on CT 104 to probe every vulnerability vector.

### Test 1: `UPDATE` Mutation Rejection
- **Execution:**
  ```php
  DB::connection('pgsql')->table('platform_audit_logs')->where('id', $id)->update(['action' => 'HACKED']);
  ```
- **Result:** **PASSED (REJECTED)**
- **Raw PostgreSQL Error:**
  ```
  SQLSTATE[P0001]: Raise exception: 7 ERROR:  Platform Audit Security Violation: platform_audit_logs records are strictly immutable and cannot be updated.
  CONTEXT:  PL/pgSQL function trg_fn_enforce_platform_audit_log_immutability() line 4 at RAISE
  ```

### Test 2: `DELETE` Mutation Rejection
- **Execution:**
  ```php
  DB::connection('pgsql')->table('platform_audit_logs')->where('id', $id)->delete();
  ```
- **Result:** **PASSED (REJECTED)**
- **Raw PostgreSQL Error:**
  ```
  SQLSTATE[P0001]: Raise exception: 7 ERROR:  Platform Audit Security Violation: platform_audit_logs records cannot be deleted from the audit ledger.
  CONTEXT:  PL/pgSQL function trg_fn_enforce_platform_audit_log_immutability() line 6 at RAISE
  ```

### Test 3: `TRUNCATE` Execution (Critical Finding)
- **Execution Vector:** Row-level `BEFORE UPDATE OR DELETE` trigger behavior during table truncation.
- **Result:** **VULNERABILITY IDENTIFIED**
  - In PostgreSQL, row-level triggers (`FOR EACH ROW`) **do not fire** on `TRUNCATE` statements. Truncation operates at the table storage layer without visiting individual rows.
  - When executed on a connection with table owner or superuser privileges (`erp_owner`), `TRUNCATE TABLE platform_audit_logs` **succeeds completely**, destroying all audit entries without triggering the function!
  - When evaluated against `has_table_privilege('app_rw', 'platform_audit_logs', 'TRUNCATE')`, `app_rw` returned `false`. However, if the application connects with elevated database credentials, `TRUNCATE` remains unblocked.
- **Architectural Clarification:** Software triggers do not constitute "hardware-level immutability" (such as AWS S3 Object Lock in Compliance mode or physical WORM optical media). Trigger enforcement is an application/database constraint.

### Test 4: Database Role Privileges & Table Ownership
Queried `information_schema.role_table_grants` and `pg_tables`:
- **Table Owner:** `erp_owner` (Superuser)
- **Role Permissions:**
  - `erp_owner`: `INSERT`, `SELECT`, `UPDATE`, `DELETE`, `TRUNCATE`, `REFERENCES`, `TRIGGER`.
  - `app_rw`: `INSERT`, `SELECT`, `UPDATE`, `DELETE`. (`TRUNCATE` and `TRIGGER` are **denied**).
  - `reporting_ro`: `SELECT` only.

### Test 5: Trigger Disablement Privileges
- **Execution as `app_rw`:**
  ```sql
  ALTER TABLE platform_audit_logs DISABLE TRIGGER trg_platform_audit_logs_immutability;
  ```
  - **Result:** **PASSED (REJECTED)**. PostgreSQL denies `ALTER TABLE` to non-owners.
- **Execution as `erp_owner` / Superuser:**
  - **Result:** **PERMITTED BY POSTGRESQL DESIGN**. The table owner or superuser can disable triggers at any time.

---

## 3. CRYPTOGRAPHIC INTEGRITY: HMAC SEAL VS HASH CHAIN

### Current Implementation: HMAC Payload Sealing
`PlatformAuditService` serializes the record attributes and computes:
```php
$payloadHash = hash_hmac('sha256', $serialized, $key);
```
- **Strengths:** Proves that the contents of record $N$ (actor, action, timestamps, before/after values) have not been altered since creation without possessing the secret application master key (`app.key`).
- **Limitation (Not a Hash Chain):**
  - A cryptographic hash chain requires each record to embed the hash of the preceding record:
    $$\text{Hash}_N = \text{HMAC}(\text{Hash}_{N-1} \parallel \text{Payload}_N, K)$$
  - In the current schema, `platform_audit_logs` **does not contain a `previous_hash` column**.
  - **Consequence:** If an attacker possessing table-owner access deletes record $N$ or re-orders rows, individual remaining records $N-1$ and $N+1$ will still pass their individual HMAC validation. Deletion of rows or gaps in history cannot be mathematically detected using the current payload hash alone.

### Application Key Rotation Behavior
- **Vulnerability:** If `php artisan key:generate` is executed, or if the environment `APP_KEY` rotates, all historical HMAC signatures stored in `payload_hash` will fail verification against the new key.
- **Phase 2 Recommendation:** Implement a Key Versioning scheme (`key_version` integer in audit records) or sign using a dedicated, non-rotating audit signing secret (`PLATFORM_AUDIT_HMAC_KEY`).

---

## 4. AUDIT ACCESS CONTROL & PII REDACTION

### PII Redaction Verification
Executed automated test `PlatformAuditLogTest::test_sensitive_attributes_are_automatically_redacted`:
- Input:
  ```json
  {
    "name": "Acme Retail",
    "admin_password": "PlainPassword123!",
    "database_password": "DbSecretPassword!",
    "two_factor_secret": "JBSWY3DPEHPK3PXP"
  }
  ```
- Output in `platform_audit_logs.new_values`:
  ```json
  {
    "name": "Acme Retail",
    "admin_password": "[REDACTED]",
    "database_password": "[REDACTED]",
    "two_factor_secret": "[REDACTED]"
  }
  ```
- **Verdict:** **PASSED**. Sensitive credentials are never written to the audit log in plain text.

### Unauthorized Audit Access
- Tested in `PlatformNavigationTest::test_unauthenticated_user_redirected_to_platform_login`:
  - Direct HTTP GET to `/platform/audit-logs` by unauthenticated sessions or tenant store users receives an immediate HTTP 302 redirect to `/platform/login`.
  - Platform panel uses the isolated `platform` guard. Tenant users authenticated under the `web` guard cannot access or view platform audit entries.

---

## 5. HARDENING ROADMAP (PHASE 2 PREREQUISITES)

To resolve all identified audit security findings in Phase 2:

1. **Add Statement-Level TRUNCATE Trigger:**
   ```sql
   CREATE TRIGGER trg_platform_audit_logs_truncate
   BEFORE TRUNCATE ON platform_audit_logs
   FOR EACH STATEMENT EXECUTE FUNCTION trg_fn_enforce_platform_audit_log_immutability();
   ```
2. **Explicitly Revoke TRUNCATE Privilege:**
   ```sql
   REVOKE TRUNCATE ON platform_audit_logs FROM PUBLIC, app_rw, reporting_ro, erp_owner;
   ```
3. **Upgrade to Sequential Cryptographic Hash Chain:**
   Add `previous_hash` and `sequence_number` columns. Enforce sequentially linked HMAC verification across all audit entries.
4. **Dedicated Non-Rotating Audit Key:**
   Decouple audit verification from `APP_KEY` by introducing `PLATFORM_AUDIT_HMAC_SECRET` in `.env`.
