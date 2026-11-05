# KC ERP CONTROL PLANE
# PHASE 2 SECURITY THREAT MODEL & RISK ASSESSMENT

**Application:** KC ERP Control Plane (`/platform`)  
**Scope:** Phase 2 (Six-Tier RBAC, MFA Enforcement, Tenant Support Access Protocol)  
**Methodology:** STRIDE (Spoofing, Tampering, Repudiation, Information Disclosure, Denial of Service, Elevation of Privilege)  
**Date:** 2026-11-05  

---

## 1. SYSTEM DECOMPOSITION & TRUST BOUNDARIES

The KC ERP platform architecture spans two fundamentally distinct trust zones:

```
[ PLATFORM TRUST ZONE ]                      [ TENANT TRUST ZONE ]
/platform                                   /admin
Platform Administrators                     Tenant Store Employees & Customers
PlatformUser Model (platform guard)         User Model (web guard)
Platform Database Tables                    Tenant Tables (Forced PostgreSQL RLS)
platform_audit_logs                         tenant_id isolation via app_rw
```

### Trust Boundary Rules
1. **Zero Shared Authentication Sessions:** A session created under the `platform` guard has zero authentication status in the tenant `web` guard, and vice versa.
2. **PostgreSQL RLS Non-Bypass:** Platform operators accessing a tenant environment during an authorized support session **must still connect through `app_rw`** with `set_config('app.current_tenant_id', id, true)`. Under no circumstances may support sessions connect as `pgsql_owner` or bypass RLS policies.
3. **Data Segregation:** SaaS subscription ledgers, platform audit trails, and platform telemetry reside outside tenant schemas and cannot be read or manipulated from tenant store sessions.

---

## 2. STRIDE THREAT ANALYSIS MATRIX

| STRIDE Category | Threat Description | Attack Vector | Severity | Phase 2 Mitigation Strategy |
|---|---|---|---|---|
| **Spoofing** | Attacker impersonates a Platform Administrator. | Credential stuffing, phishing, brute force on `/platform/login`. | **CRITICAL** | Mandatory TOTP MFA (RFC 6238), rate-limiting (5 attempts/min), IP-restricted login, and device fingerprinting. |
| **Spoofing** | Platform operator masquerades as a tenant user without permission. | Direct session crafting or forging tenant session cookies. | **CRITICAL** | Delegated Support Access Token with cryptographic HMAC signature, explicit tenant consent, and short TTL (max 4h). |
| **Tampering** | Operator alters audit logs to hide unauthorized actions. | Direct SQL update or trigger disable on `platform_audit_logs`. | **HIGH** | PostgreSQL statement-level TRUNCATE trigger, revocation of TRUNCATE privilege, and chained hash verification. |
| **Tampering** | Operator modifies tenant financial ledger during support session. | Altering stock movements or posted journal lines while diagnosing. | **HIGH** | Read-Only Default on support sessions. Mutation privileges require explicit Dual-Custody Elevation by Tenant Admin. |
| **Repudiation** | Operator denies performing destructive action on tenant. | "I didn't suspend that store; someone else did." | **HIGH** | Non-repudiable audit logging: captures Actor ID, Name, Email, IP, User-Agent, Before/After Diff, and HMAC signature. |
| **Information Disclosure** | Operator harvests tenant customer PII, phone numbers, or credit card data. | Viewing customer registries or payment configs during support. | **HIGH** | Automated PII masking in support sessions: Customer phone, email, and tax IDs masked unless unmasking is authorized. |
| **Denial of Service** | Rogue operator suspends all active tenants. | Bulk tenant suspension via compromised Super Admin account. | **CRITICAL** | Rate limits on administrative mutations, sensitive-action re-authentication (sudo mode), and 2-person approval for bulk ops. |
| **Elevation of Privilege** | Support Tier 2 agent grants themselves Super Admin role. | Parameter tampering or unvalidated role assignment in user resource. | **CRITICAL** | Strict Role-Based Access Control (RBAC) policy enforcement; users cannot grant roles higher than their own hierarchy tier. |

---

## 3. DELEGATED SUPPORT ACCESS THREAT MODEL (BREAK-GLASS / IMPERSONATION)

Allowing platform operators to access tenant instances introduces the highest risk profile in multi-tenant SaaS.

### Traditional Anti-Pattern (Unacceptable)
- Operator clicks "Login as Tenant" -> System logs in as tenant user ID without restrictions, without consent, and without time limit.
- **Why this is forbidden:** Violates SOC 2, ISO 27001, GDPR, and Bangladesh statutory financial auditing standards.

### Approved Phase 2 Architecture: Cryptographically Governed Support Access

```
+-----------------------------------------------------------------------------------+
| 1. OPERATOR REQUEST                                                               |
| Platform Operator creates Support Access Request:                                 |
| - Target Tenant ID & Store ID                                                     |
| - External Ticket ID (e.g. SUP-94812)                                             |
| - Justification & Reason                                                          |
| - Requested Duration (1h - 4h max)                                                |
| - Scope: READ_ONLY (Default) or READ_WRITE (Requires emergency approval)          |
+-----------------------------------------------------------------------------------+
                                          |
                                          v
+-----------------------------------------------------------------------------------+
| 2. TENANT CONSENT                                                                 |
| - Notification sent to Tenant Owner / Store Admin in ERP notification center      |
| - Tenant Owner clicks [APPROVE SUPPORT SESSION]                                   |
| - Emergency Break-Glass: Allowed ONLY for P1 Critical Outages with dual platform   |
|   officer approval (e.g. Super Admin + Security Officer)                          |
+-----------------------------------------------------------------------------------+
                                          |
                                          v
+-----------------------------------------------------------------------------------+
| 3. TIMED EPHEMERAL TOKEN ISSUANCE                                                 |
| - System issues TenantSupportToken (UUID, HMAC-SHA256 sealed)                     |
| - TTL: Fixed to approved duration (Absolute max: 240 minutes)                     |
| - Injected with: `support_operator_id`, `tenant_id`, `scope`, `expires_at`        |
+-----------------------------------------------------------------------------------+
                                          |
                                          v
+-----------------------------------------------------------------------------------+
| 4. RESTRICTED EXECUTION & DUAL AUDIT LOGGING                                      |
| - Middleware verifies token on every request; enforces READ_ONLY if scope is RO   |
| - Sensitive PII automatically masked in Blade templates and Filament forms        |
| - Every action logged to:                                                         |
|     1. `platform_audit_logs` (for SaaS provider governance)                       |
|     2. Tenant's `audit_logs` (visible to Tenant Admin in their store)               |
+-----------------------------------------------------------------------------------+
```

---

## 4. ATTACK VECTOR ANALYSIS & VERIFICATION CONTROLS

### Attack Vector 1: Cross-Tenant Escalation via Support Session
- **Scenario:** Operator with an active support token for Tenant A attempts to read Tenant B's data by changing the URL or query parameters.
- **Prevention:** The support session middleware binds `app.current_tenant_id` to the token's authenticated `tenant_id`. PostgreSQL forced RLS isolates all 74 tenant tables fail-closed. Cross-tenant queries return 0 rows.
- **Verification Test:** `tests/Feature/Platform/SupportAccessSecurityTest::test_support_operator_cannot_access_unauthorized_tenant`.

### Attack Vector 2: Support Token Replay after Expiration
- **Scenario:** Attacker captures an expired support session token and replays requests.
- **Prevention:** Support token expiration is checked on every HTTP request in middleware (`now() > $token->expires_at`). Revoked and expired tokens are immediately invalidated in Redis cache.
- **Verification Test:** `tests/Feature/Platform/SupportAccessSecurityTest::test_expired_support_token_is_immediately_rejected`.

### Attack Vector 3: MFA Bypass via Session Manipulation
- **Scenario:** Attacker bypasses the TOTP prompt by navigating directly to `/platform` dashboard after entering password.
- **Prevention:** Platform authentication pipeline requires `mfa_verified_at` session flag. Middleware `EnsurePlatformMfaVerified` intercepts all authenticated requests and redirects unverified sessions to `/platform/mfa/challenge`.
- **Verification Test:** `tests/Feature/Platform/PlatformMfaTest::test_mfa_challenge_cannot_be_bypassed_by_direct_navigation`.
