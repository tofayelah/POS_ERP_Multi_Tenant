# KC ERP CONTROL PLANE
# PHASE 1 REMEDIATION CHANGELOG

**Application:** KC ERP Control Plane (`/platform`)  
**Release:** Control Plane v1.1.1-remediation  
**Baseline Commit:** `b763a38e1415669a0d67b6b3a70d592f4d0733d0`  
**Date:** 2026-11-05  

---

## 1. ADDED

### Database Security & Migrations
- Added migration `database/migrations/2026_11_16_000002_harden_platform_audit_logs_immutability.php`:
  - Added `key_version` column (`unsignedInteger`, default `0`) with index on `platform_audit_logs`.
  - Added statement-level trigger `trg_platform_audit_logs_truncate` on `platform_audit_logs` invoking `trg_fn_enforce_platform_audit_log_immutability()`.
  - Revoked `TRUNCATE`, `UPDATE`, and `DELETE` privileges from `PUBLIC`, `app_rw`, and `reporting_ro`.
  - Granted `SELECT` and `INSERT` to `app_rw`.
  - Upgraded trigger function to raise `Platform Audit Security Violation: platform_audit_logs table cannot be truncated.` on `TG_OP = 'TRUNCATE'`.

### Configuration
- Added `config/platform.php`:
  - `platform.audit.hmac_secret` (reads `PLATFORM_AUDIT_HMAC_SECRET`).
  - `platform.audit.key_version` (reads `PLATFORM_AUDIT_KEY_VERSION`, default `1`).
  - `platform.audit.historical_keys` registry for rotated keys.
  - `platform.pos_bridge.health_url` (default `http://127.0.0.1:9191/health`).
  - `platform.pos_bridge.timeout_seconds` (default `1.0`).

### Automated Test Coverage
- In `tests/Feature/Platform/PlatformAuditLogTest.php`:
  - Added `test_platform_audit_log_update_is_rejected_by_postgresql_trigger`.
  - Added `test_platform_audit_log_delete_is_rejected_by_postgresql_trigger`.
  - Added `test_app_rw_runtime_role_cannot_update_or_delete_audit_logs`.
  - Added `test_app_rw_runtime_role_cannot_truncate_audit_logs`.
  - Added `test_platform_audit_log_truncate_is_rejected_by_statement_trigger`.
  - Added `test_new_audit_record_verifies_using_dedicated_hmac_secret`.
  - Added `test_historical_audit_record_with_key_version_0_verifies_using_app_key`.
  - Added `test_tampered_audit_record_fails_verification`.
  - Added `test_key_rotation_allows_historical_and_new_records_to_coexist`.
  - Added `test_audit_verification_fails_closed_when_key_is_missing`.
  - Added `test_audit_recording_fails_closed_when_secret_is_unconfigured`.
- In `tests/Feature/Platform/PlatformTelemetryServiceTest.php`:
  - Added `test_go_bridge_probe_returns_ok_and_version_on_successful_health_response`.
  - Added `test_go_bridge_probe_reports_offline_on_connection_refusal`.
  - Added `test_go_bridge_probe_reports_degraded_on_http_server_error`.
  - Added `test_go_bridge_probe_reports_degraded_on_malformed_json_response`.

---

## 2. CHANGED

### Core Services
- `App\Core\Platform\Services\PlatformAuditService`:
  - Decoupled from `config('app.key')`.
  - Uses `PLATFORM_AUDIT_HMAC_SECRET` and stores `key_version`.
  - Implemented `verify(PlatformAuditLog $log): bool` supporting key version 0 (legacy `APP_KEY`), current version, and historical keys.
  - Implemented fail-closed behavior on missing keys.
- `App\Core\Platform\Services\PlatformTelemetryService`:
  - Corrected `getGoBridgeProbe()` endpoint from `127.0.0.1:8181/healthz` to `127.0.0.1:9191/health`.
  - Added JSON payload validation and version parsing.
  - Added graceful handling of `ConnectionException` and HTTP errors.

### Models
- `App\Models\PlatformAuditLog`:
  - Added `key_version` to docblock properties, `$fillable`, and cast to integer.

### Configuration & Environment
- `.env.example`: Added platform audit and bridge configuration keys.
- `phpunit.xml`: Added testing defaults for `PLATFORM_AUDIT_HMAC_SECRET`, `PLATFORM_AUDIT_KEY_VERSION`, and `POS_BRIDGE_HEALTH_URL`.

---

## 3. REMOVED
- Removed coupling between platform audit sealing and Laravel application key rotation.
- Removed insecure reliance on row-level triggers for TRUNCATE protection.

---

## 4. BREAKING CHANGES
**NONE.**  
All existing audit records are automatically preserved with `key_version = 0` and continue to verify against `APP_KEY`. Zero changes to tenant database schemas, accounting, or inventory document chains.
