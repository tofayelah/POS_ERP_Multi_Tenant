# KC ERP CONTROL PLANE
# PLATFORM HMAC KEY MANAGEMENT, MIGRATION & VERIFICATION

**Application:** KC ERP Control Plane (`/platform`)  
**Scope:** Milestone 2 (Dedicated Audit HMAC Key, Key Versioning, Historical Verification, and Rotation)  
**Date:** 2026-11-05  

---

## 1. DEDICATED KEY ARCHITECTURE

Prior to Phase 1 Remediation, `PlatformAuditService` derived its cryptographic signing key from Laravel's master application encryption key (`config('app.key')`). 

### Risks Identified
1. **Coupling:** Rotating the application key (`php artisan key:generate`) for session or cookie security invalidated historical audit records.
2. **Blast Radius:** If the application key was rotated due to session leakage, audit integrity verification was broken across the entire platform.

### Remediation Design
1. **Dedicated Independent Secret:** Platform audit log sealing now uses `PLATFORM_AUDIT_HMAC_SECRET` configured in `config/platform.php`.
2. **Metadata Key Versioning:** Each audit record persists the exact integer key version under which it was sealed (`key_version`).
3. **Preservation of Historical Phase 1 Records:** Existing audit records signed with the old `APP_KEY` are assigned `key_version = 0`. The verification service seamlessly resolves `key_version = 0` to `config('app.key')` without re-signing or modifying historical records.
4. **Fail-Closed Design:** If a required signing secret is missing or unconfigured, recording throws a `RuntimeException`. If a verification key cannot be resolved, verification returns `false`.

---

## 2. DATABASE SCHEMA EXTENSION

Implemented in `database/migrations/2026_11_16_000002_harden_platform_audit_logs_immutability.php`:

```sql
ALTER TABLE platform_audit_logs 
    ADD COLUMN key_version INT NOT NULL DEFAULT 0;

CREATE INDEX idx_platform_audit_logs_key_version ON platform_audit_logs(key_version);
```

- **Default Value `0`:** Automatically maps all pre-existing Phase 1 audit rows to the legacy verification pathway (`app.key`).
- **PostgreSQL 17 Metadata Safety:** Adding a column with a default value does not invoke row-level `UPDATE` triggers and preserves table immutability.

---

## 3. KEY ROTATION & VERIFICATION ENGINE

### `PlatformAuditService::verify(PlatformAuditLog $log): bool`
The verification pipeline re-computes the HMAC signature using the key version embedded in the record:

```php
public function verify(PlatformAuditLog $log): bool
{
    $key = $this->resolveVerificationKey($log->key_version);

    if ($key === '') {
        return false; // Fail closed
    }

    $createdAtString = $log->created_at->toIso8601String();

    $serialized = json_encode([
        'action' => $log->action,
        'actor_id' => $log->platform_user_id,
        'resource_type' => $log->resource_type,
        'resource_id' => $log->resource_id,
        'old' => $log->old_values,
        'new' => $log->new_values,
        'reason' => $log->reason,
        'status' => $log->status,
        'created_at' => $createdAtString,
    ], JSON_THROW_ON_ERROR);

    $expectedHash = hash_hmac('sha256', $serialized, $key);

    return hash_equals($expectedHash, (string) $log->payload_hash);
}
```

### Key Resolution Hierarchy (`resolveVerificationKey`)
1. **Version 0:** Returns `(string) config('app.key')` (Legacy Phase 1).
2. **Current Version ($V_{active}$):** Returns `(string) config('platform.audit.hmac_secret')`.
3. **Historical Versions ($V < V_{active}$):** Looks up in `config('platform.audit.historical_keys')`.
4. **Unknown Version or Empty Key:** Returns `''` (Fails closed).

---

## 4. SAFE KEY ROTATION PROCEDURE

When rotating the audit HMAC key:

1. **Step 1:** Generate a new 64-character cryptographic secret:
   ```bash
   openssl rand -hex 32
   ```
2. **Step 2:** Archive the current active key into the historical keys registry in `config/platform.php` or `.env`:
   ```env
   PLATFORM_AUDIT_KEY_VERSION=2
   PLATFORM_AUDIT_HMAC_SECRET=new_generated_secret_here
   PLATFORM_AUDIT_HISTORICAL_KEY_1=old_v1_secret_here
   ```
3. **Step 3:** Deploy configuration.
   - All newly created audit records are sealed with Version 2.
   - Records with `key_version = 1` verify against `PLATFORM_AUDIT_HISTORICAL_KEY_1`.
   - Records with `key_version = 0` continue verifying against `APP_KEY`.
   - Zero historical records are re-signed or updated (maintaining trigger immutability).

---

## 5. AUTOMATED TEST EVIDENCE

| Test Scenario | Test Method | Result on CT 104 |
|---|---|:---:|
| **New Record Signing** | `test_new_audit_record_verifies_using_dedicated_hmac_secret` | **PASS** |
| **Historical Record (v0)** | `test_historical_audit_record_with_key_version_0_verifies_using_app_key` | **PASS** |
| **Multi-Version Coexistence**| `test_key_rotation_allows_historical_and_new_records_to_coexist` | **PASS** |
| **Tampered Record Detection** | `test_tampered_audit_record_fails_verification` | **PASS** |
| **Missing Key Fail-Closed** | `test_audit_verification_fails_closed_when_key_is_missing` | **PASS** |
| **Unconfigured Secret Fail**| `test_audit_recording_fails_closed_when_secret_is_unconfigured` | **PASS** |
