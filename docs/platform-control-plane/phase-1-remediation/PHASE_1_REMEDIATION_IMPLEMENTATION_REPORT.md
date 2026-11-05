# KC ERP CONTROL PLANE
# PHASE 1 REMEDIATION IMPLEMENTATION REPORT

**Project:** KC Retail ERP / `POS_ERP_Multi_Tenant`  
**Application:** KC ERP Control Plane (`/platform`)  
**Environment:** Proxmox CT 104 (`dev.kcerp.com`)  
**Baseline Commit:** `b763a38e1415669a0d67b6b3a70d592f4d0733d0`  
**Remediation Branch:** `control-plane-phase-1-remediation`  
**Date:** 2026-11-05  
**Status:** COMPLETED & VERIFIED ON CT 104  

---

## 1. EXECUTIVE SUMMARY

Under the directive of **HUMAN AUTHORIZATION: KC ERP CONTROL PLANE - PHASE 1 REMEDIATION IMPLEMENTATION**, implementation was strictly restricted to the three authorized remediation items:
1. **Milestone 1: PostgreSQL Platform Audit Log Protection**  
   - Statement-level `BEFORE TRUNCATE` trigger implemented (`trg_platform_audit_logs_truncate`).
   - Revoked `TRUNCATE`, `UPDATE`, and `DELETE` from application runtime roles (`app_rw`, `reporting_ro`).
   - Hardened immutability function `trg_fn_enforce_platform_audit_log_immutability()` to reject `TRUNCATE`.
   - Verified that `app_rw` is blocked at permission check, and table owner is blocked by statement trigger.
2. **Milestone 2: Dedicated HMAC Key Management**  
   - Decoupled platform audit log signing from Laravel `APP_KEY` by introducing dedicated `PLATFORM_AUDIT_HMAC_SECRET`.
   - Added `key_version` column to `platform_audit_logs` (default `0` for legacy Phase 1 records signed with `APP_KEY`).
   - Implemented `PlatformAuditService::verify(PlatformAuditLog $log): bool` supporting historical key verification and rotation.
   - Enforced fail-closed behavior when the secret is unconfigured or key version is missing.
3. **Milestone 3: Go POS Bridge Telemetry Correction**  
   - Corrected telemetry endpoint in `PlatformTelemetryService` to `http://127.0.0.1:9191/health`.
   - Added JSON status verification and version parsing.
   - Added resilient handling for connection refused, timeout, server errors, and malformed responses.

Zero unauthorized features (Phases 2, 3, 4, 5) were modified or implemented.

---

## 2. COMMIT LINEAGE ON REMEDIATION BRANCH

In strict compliance with Git policy, all milestones were committed separately:

```
a61e0a1 fix(phpstan): simplify Carbon instance check in PlatformAuditService
293db2c style: apply pint formatting fixes to platform remediation files
938142f test: verify both app_rw permission denial and owner trigger rejection
16c120d fix(telemetry): correct Go POS bridge endpoint to 9191/health with comprehensive fault tolerance (Milestone 3)
e1d67b4 feat(security): dedicated audit HMAC key with key-version metadata and historical verification (Milestone 2)
fd6f866 feat(security): statement-level truncate trigger and privilege revocation for platform audit logs (Milestone 1)
cc1dd94 docs(platform): add Phase 1 acceptance audit and Phase 2 security planning reports
b763a38 [BASELINE COMMIT] docs(platform): add Phase 1 implementation reports, test evidence, and changelog
```

---

## 3. FILES CREATED OR MODIFIED

### A. Infrastructure & Migrations
- `database/migrations/2026_11_16_000002_harden_platform_audit_logs_immutability.php` *(NEW)*: Adds `key_version` column, statement-level truncate trigger, and revokes `TRUNCATE/UPDATE/DELETE` from runtime roles.
- `config/platform.php` *(NEW)*: Dedicated platform configuration defining audit HMAC secret, key version, historical key registry, and POS bridge settings.
- `.env.example` *(MODIFIED)*: Added `PLATFORM_AUDIT_HMAC_SECRET`, `PLATFORM_AUDIT_KEY_VERSION`, and `POS_BRIDGE_HEALTH_URL`.
- `phpunit.xml` *(MODIFIED)*: Added test environment defaults for dedicated HMAC secret and bridge health URL.

### B. Core Services & Models
- `app/Models/PlatformAuditLog.php` *(MODIFIED)*: Added `key_version` property, fillable attribute, and integer cast.
- `app/Core/Platform/Services/PlatformAuditService.php` *(MODIFIED)*: Integrated dedicated HMAC signing, fail-closed secret validation, `key_version` persistence, `verify()` method, and historical key resolution.
- `app/Core/Platform/Services/PlatformTelemetryService.php` *(MODIFIED)*: Updated `getGoBridgeProbe()` to target `127.0.0.1:9191/health` with JSON validation and fault tolerance.

### C. Automated Test Suites
- `tests/Feature/Platform/PlatformAuditLogTest.php` *(MODIFIED)*: Added tests for statement-level TRUNCATE rejection, runtime role permission denial, dedicated key verification, historical APP_KEY verification, key rotation, and fail-closed missing key behavior.
- `tests/Feature/Platform/PlatformTelemetryServiceTest.php` *(MODIFIED)*: Added tests for Go bridge `200 OK`, connection refusal, timeout, server error, and malformed response.
- `tests/Feature/Platform/PlatformDashboardKpiTest.php` *(MODIFIED)*: Configured test audit secret in `setUp()`.
- `tests/Feature/Platform/PlatformNavigationTest.php` *(MODIFIED)*: Configured test audit secret in `setUp()`.

---

## 4. STATUS SUMMARY OF REMEDIATION ITEMS

| Remediation Item | Target Requirement | Implemented Solution | Verification Evidence |
|---|---|---|---|
| **Audit Protection** | Statement-level TRUNCATE protection | `trg_platform_audit_logs_truncate` trigger created on `pgsql_owner` | Tested via `PlatformAuditLogTest::test_platform_audit_log_truncate_is_rejected_by_statement_trigger` |
| **Audit Privileges** | Revoke TRUNCATE/UPDATE/DELETE from runtime roles | `REVOKE TRUNCATE, UPDATE, DELETE FROM app_rw, reporting_ro` | Tested via `PlatformAuditLogTest::test_app_rw_runtime_role_cannot_update_or_delete_audit_logs` |
| **Dedicated HMAC Key** | Key independent of `APP_KEY` | `PLATFORM_AUDIT_HMAC_SECRET` in `config/platform.php` | Tested via `PlatformAuditLogTest::test_new_audit_record_verifies_using_dedicated_hmac_secret` |
| **Historical Audits** | Verify historical Phase 1 records signed with `APP_KEY` | `key_version = 0` resolves to `app.key` | Tested via `PlatformAuditLogTest::test_historical_audit_record_with_key_version_0_verifies_using_app_key` |
| **Key Rotation** | Support multi-key rotation | Historical key registry in `config/platform.php` | Tested via `PlatformAuditLogTest::test_key_rotation_allows_historical_and_new_records_to_coexist` |
| **Fail-Closed Key** | Reject unconfigured or missing keys | Throws `RuntimeException` on record; returns `false` on verify | Tested via `PlatformAuditLogTest::test_audit_verification_fails_closed_when_key_is_missing` |
| **Go Bridge Port** | Correct port to 9191/health | `PlatformTelemetryService` probes `127.0.0.1:9191/health` | Tested via `PlatformTelemetryServiceTest` (5 probe scenarios) |

---

## 5. CONCLUSION
All three authorized Phase 1 remediation items are implemented, migrated on both test and development databases, and certified on CT 104. Zero regressions were introduced.
