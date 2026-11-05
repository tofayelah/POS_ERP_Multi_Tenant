# KC ERP CONTROL PLANE
# PHASE 2 TEST & ACCEPTANCE PLAN

**Application:** KC ERP Control Plane (`/platform`)  
**Scope:** Phase 2 (Six-Tier RBAC, MFA Enforcement, and Tenant Support Access Protocol)  
**Date:** 2026-11-05  

---

## 1. TESTING STRATEGY & TEST PYRAMID

Phase 2 security testing employs a multi-tiered verification strategy designed to guarantee zero privilege escalation, zero tenant data leakage, and cryptographic integrity:

```
                    / \
                   /   \
                  / SEC \      Penetration & Red-Team Tests (Cross-Tenant, Bypass)
                 /-------\
                / FEATURE \    Filament UI, Livewire, Middleware & Workflows
               /-----------\
              /    UNIT     \  RBAC Evaluator, TOTP Math, Hash Chains, Token Hashes
             /---------------\
```

---

## 2. AUTOMATED UNIT TESTS (`tests/Unit/Platform/`)

| Test Suite | Test Class | Target Verifications |
|---|---|---|
| **RBAC Evaluator** | `PlatformRbacServiceTest` | - Grants permissions mapped to assigned tier.<br>- Denies unassigned permissions default-deny.<br>- Prevents lower-tier role from creating or modifying higher-tier user.<br>- Evaluates custom JSON permission overrides. |
| **TOTP RFC 6238** | `TotpEngineTest` | - Validates correct 6-digit code against RFC 6238 test vectors.<br>- Accepts codes within $\pm 1$ time step (30s drift).<br>- Rejects codes outside time window.<br>- Encrypts and decrypts Base32 secret using AES-256-GCM. |
| **Recovery Codes** | `MfaRecoveryCodeTest` | - Generates 8 unambiguous formatted codes (`XXXXX-XXXXX`).<br>- Hashes codes with Argon2id prior to database write.<br>- Invalidates and deletes code immediately upon single use.<br>- Rejects already consumed recovery codes. |
| **Cryptographic Chains**| `AuditHashChainTest` | - Computes sequential hash: $H_N = \text{HMAC}(H_{N-1} \parallel \text{Payload}_N)$.<br>- Detects omission of any record in chain.<br>- Detects modification of any record in chain. |

---

## 3. FEATURE & WORKFLOW TESTS (`tests/Feature/Platform/`)

### A. Sprint 2.1: RBAC, MFA, and Session Management
- `PlatformRbacRouteProtectionTest`:
  - `test_super_admin_can_access_all_platform_routes`
  - `test_platform_ops_cannot_access_financial_plan_management`
  - `test_finance_admin_cannot_access_infrastructure_telemetry`
  - `test_compliance_officer_can_view_audit_logs_but_cannot_modify_tenants`
  - `test_support_tier2_cannot_access_audit_logs_or_user_management`
  - `test_read_only_auditor_cannot_trigger_any_mutation_or_action`
- `PlatformMfaWorkflowTest`:
  - `test_unauthenticated_user_redirected_to_login`
  - `test_authenticated_user_without_mfa_redirected_to_mfa_challenge`
  - `test_first_time_login_redirected_to_mfa_setup`
  - `test_valid_totp_code_grants_access_to_dashboard`
  - `test_five_consecutive_failed_totp_attempts_locks_account_for_15_minutes`
  - `test_mfa_code_cannot_be_replayed_within_same_time_step`
- `PlatformSudoModeTest`:
  - `test_tenant_suspension_requires_sudo_reauthentication`
  - `test_valid_password_reprompt_enables_sudo_mode_for_10_minutes`
  - `test_sudo_mode_expires_after_10_minutes`

### B. Sprint 2.2: Delegated Tenant Support Access
- `TenantSupportAccessWorkflowTest`:
  - `test_operator_can_submit_support_access_request_with_valid_ticket`
  - `test_request_without_ticket_or_justification_is_rejected`
  - `test_tenant_admin_can_approve_support_request_from_erp_dashboard`
  - `test_approved_support_session_generates_ephemeral_token`
  - `test_support_token_allows_operator_to_access_tenant_erp_in_read_only_mode`
  - `test_support_session_automatically_expires_after_approved_duration`
  - `test_tenant_admin_can_immediately_revoke_active_support_session`
  - `test_break_glass_emergency_access_requires_dual_superadmin_approval`
- `SupportSessionDataMaskingTest`:
  - `test_customer_phone_numbers_and_emails_are_masked_in_support_view`
  - `test_supplier_bank_details_are_masked_in_support_view`
  - `test_employee_nid_numbers_are_masked_in_support_view`
- `DualLedgerAuditLoggingTest`:
  - `test_support_action_records_entry_in_platform_audit_logs`
  - `test_support_action_records_entry_in_tenant_audit_logs`
  - `test_tenant_admin_can_view_support_operator_actions_in_store_audit_log`

---

## 4. RED-TEAM SECURITY & PENETRATION TESTS

### Pen Test 1: Cross-Tenant Data Access Attempt
- **Objective:** Attempt to read Tenant B's customers using an active support token issued for Tenant A.
- **Execution:**
  - Authenticate as Support Operator with Token for Tenant A.
  - Send HTTP GET request to `/admin/customers` with header `X-Tenant-ID: {Tenant-B-UUID}` or URL parameter `?tenant_id={Tenant-B-UUID}`.
- **Expected Result:** **DENIED (HTTP 403 / 0 rows)**. Middleware enforces `app.current_tenant_id` exclusively from token session; PostgreSQL forced RLS isolates tables fail-closed.

### Pen Test 2: Support Token Replay & Forgery
- **Objective:** Craft or replay a modified support token.
- **Execution:** Tamper with the bearer token string or present an expired token hash.
- **Expected Result:** **DENIED (HTTP 401 Unauthorized)**. Token lookup in `tenant_support_sessions` fails; token rejected.

### Pen Test 3: SQL Truncate Protection
- **Objective:** Verify statement-level TRUNCATE trigger blocks table destruction.
- **Execution:** Execute `TRUNCATE TABLE platform_audit_logs` via `pgsql_owner` and `app_rw`.
- **Expected Result:** **DENIED (PostgreSQL Exception)** via `trg_platform_audit_logs_truncate`.

---

## 5. ACCEPTANCE CRITERIA & SIGN-OFF GATES

Phase 2 will be accepted for deployment only when all 5 sign-off gates are satisfied:

1. **Gate 1 - Unit & Feature Tests:** 100% of all Phase 2 tests pass on CT 104 with zero failures.
2. **Gate 2 - Static Analysis & Code Style:** 100% clean run of `vendor/bin/pint --test` and `vendor/bin/phpstan analyse --memory-limit=2G` (Level 8).
3. **Gate 3 - Database Security Check:** `php artisan db:check-rls` passes 100% across all 74 tenant tables, and statement-level trigger blocks `TRUNCATE` on `platform_audit_logs`.
4. **Gate 4 - Security Review:** Completed penetration test report confirming zero cross-tenant leakage and non-bypassable MFA.
5. **Gate 5 - Human Authorization:** Formal written sign-off from Project Owner / Human Principal before merging to staging or production.
