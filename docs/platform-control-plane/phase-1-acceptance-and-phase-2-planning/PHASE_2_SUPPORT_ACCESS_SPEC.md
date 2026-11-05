# KC ERP CONTROL PLANE
# PHASE 2 TENANT SUPPORT ACCESS & BREAK-GLASS PROTOCOL SPECIFICATION

**Application:** KC ERP Control Plane (`/platform`)  
**Sprint:** 2.2 (Delegated Support Access, Tenant Authorization, and Dual-Audit Trails)  
**Security Standard:** Principle of Least Privilege & Explicit Custodial Consent  
**Date:** 2026-11-05  

---

## 1. ARCHITECTURAL OBJECTIVE

To resolve technical support tickets and assist store owners without compromising multi-tenant security, Phase 2 implements a cryptographically sealed, time-bounded, consent-governed **Delegated Support Access Protocol**.

### Core Security Mandates
1. **No Uncontrolled Impersonation:** Platform operators cannot click a button and silently access a tenant's ERP instance.
2. **Mandatory Tenant Consent:** The tenant store owner must explicitly authorize support access, except during declared catastrophic break-glass emergencies.
3. **Strict RLS Enforcement:** Support sessions connect through standard `app_rw` and set `set_config('app.current_tenant_id', id, true)`. Under no circumstances is Row-Level Security bypassed.
4. **Time-Bounded Ephemeral Access:** Maximum access duration is **4 hours (240 minutes)**. Sessions automatically expire and cannot be extended without new consent.
5. **Read-Only by Default:** Operators are granted read-only diagnostic access by default. Mutation privileges require separate high-tier elevation.
6. **Dual-Ledger Audit Trails:** Every action performed by the operator is recorded both in `platform_audit_logs` (for SaaS provider compliance) AND the tenant's store `audit_logs` (visible to the store owner).

---

## 2. DATABASE SCHEMA SPECIFICATION

### Table: `tenant_support_sessions`
```sql
CREATE TABLE tenant_support_sessions (
    id UUID PRIMARY KEY,
    ticket_id VARCHAR(50) NOT NULL,                    -- e.g. 'SUP-10492'
    platform_user_id UUID NOT NULL REFERENCES platform_users(id),
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    requested_by_name VARCHAR(150) NOT NULL,
    requested_by_email VARCHAR(150) NOT NULL,
    justification TEXT NOT NULL,
    scope VARCHAR(30) NOT NULL DEFAULT 'read_only',    -- 'read_only', 'read_write'
    status VARCHAR(30) NOT NULL DEFAULT 'pending',     -- 'pending', 'approved', 'rejected', 'active', 'expired', 'revoked'
    duration_minutes INT NOT NULL DEFAULT 60,           -- Max 240
    
    -- Tenant Authorization
    approved_by_user_id UUID NULL REFERENCES users(id),
    approved_by_name VARCHAR(150) NULL,
    approved_at TIMESTAMPTZ NULL,
    is_break_glass BOOLEAN NOT NULL DEFAULT FALSE,
    break_glass_approved_by_id UUID NULL REFERENCES platform_users(id),
    
    -- Session Lifecycle
    session_token_hash VARCHAR(64) NOT NULL,            -- SHA-256 hash of ephemeral bearer token
    started_at TIMESTAMPTZ NULL,
    expires_at TIMESTAMPTZ NULL,
    terminated_at TIMESTAMPTZ NULL,
    termination_reason VARCHAR(255) NULL,
    
    -- Diagnostics
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL
);

CREATE INDEX idx_support_sessions_tenant ON tenant_support_sessions(tenant_id);
CREATE INDEX idx_support_sessions_operator ON tenant_support_sessions(platform_user_id);
CREATE INDEX idx_support_sessions_token ON tenant_support_sessions(session_token_hash);
CREATE INDEX idx_support_sessions_status ON tenant_support_sessions(status);
```

---

## 3. SUPPORT ACCESS WORKFLOW

```
[ Platform Operator ]                   [ Tenant Admin ]                 [ Security Gateway ]
        |                                      |                                  |
        |--- 1. Submit Request --------------->|                                  |
        |    (Ticket ID, Reason, Duration)     |                                  |
        |                                      |--- 2. Review In-App Alert ------>|
        |                                      |    [Approve 2-Hour Read-Only]    |
        |                                      |                                  |
        |<-- 3. Token Issued -----------------------------------------------------|
        |    (Ephemeral Token, TTL: 120m)      |                                  |
        |                                      |                                  |
        |--- 4. Enter Store ERP (/admin) ---------------------------------------->|
        |    (Header: X-KC-Support-Token)      |                                  |
        |                                      |                                  | Verify Token
        |                                      |                                  | Check Expiry (<120m)
        |                                      |                                  | Mask PII Data
        |                                      |                                  | Enforce Forced RLS
        |                                      |                                  | Enforce Read-Only
        |                                      |                                  |
        |<-- 5. Serve Masked UI ---------------------------------------------------|
        |                                      |                                  |
        |                                      |                                  | Dual Audit Log
        |                                      |                                  | (Platform + Tenant)
```

### Stage 1: Request Submission
1. Operator navigates to `TenantResource` -> Actions -> "Request Support Access".
2. Must provide:
   - External Ticket Reference (regex validated: `^[A-Z]{2,6}-[0-9]{3,7}$`).
   - Detailed justification (minimum 25 characters).
   - Requested Duration (30m, 60m, 120m, or maximum 240m).
   - Requested Scope: `read_only` (default) or `read_write` (requires justification).

### Stage 2: Tenant Consent Authorization
1. Tenant store owner receives an in-app urgent notification in their ERP dashboard (`/admin`) and an email notification with an authorization link.
2. Store owner reviews operator identity, ticket reference, justification, requested scope, and requested duration.
3. Store owner clicks [APPROVE SUPPORT SESSION] or [REJECT].
4. **Emergency Break-Glass Exception:**
   - In P1 critical infrastructure outages where tenant admins are unreachable, a Super Administrator may initiate a Break-Glass session.
   - Requires mandatory secondary approval from the Security & Compliance Officer (Dual Custody).
   - Flags `is_break_glass = true`, triggers high-priority notifications to tenant executives, and enters immediate audit escalation.

### Stage 3: Ephemeral Token Issuance & Execution
1. Upon approval, the system generates a 64-character cryptographically secure token.
2. The raw token is displayed to the operator **once** and stored as an SHA-256 hash in `tenant_support_sessions.session_token_hash`.
3. Operator launches the store session. The session middleware (`EnforceTenantSupportAccess`):
   - Authenticates the operator as a virtual delegated auditor.
   - Sets `set_config('app.current_tenant_id', $session->tenant_id, true)`.
   - Injects the support session banner into the UI ("SUPPORT SESSION ACTIVE - TICKET SUP-10492 - 1h 45m REMAINING").

---

## 4. PII & FINANCIAL DATA MASKING

During a support session, sensitive customer and employee data is automatically redacted unless explicit unmasking is authorized:

| Entity | Field | Normal Tenant View | Masked Support Session View |
|---|---|---|---|
| **Customers** | Phone Number | `+8801712345678` | `+88017*****678` |
| **Customers** | Email Address | `customer@gmail.com` | `c***r@gmail.com` |
| **Suppliers** | Bank Account / Routing | `10528491823901` | `**********901` |
| **Employees** | National ID (NID) | `19902692810000491` | `*************0491` |
| **Payments** | Card Number | `4111 2222 3333 4444` | `•••• •••• •••• 4444` |

---

## 5. AUTOMATIC EXPIRY & IMMEDIATE REVOCATION

1. **Automatic Expiration:**
   - The token expires at exact timestamp `expires_at = approved_at + interval '{duration} minutes'`.
   - On expiration, subsequent HTTP requests immediately return HTTP 403 Forbidden with message: `Support session has expired.`
2. **Immediate Manual Revocation:**
   - Either the Tenant Store Owner (from ERP) OR the Platform Operator/Admin (from Control Plane) can click [TERMINATE SESSION NOW].
   - Instantly sets `status = 'revoked'`, `terminated_at = now()`, and blacklists the token hash in Redis cache (`Cache::set("support:blacklisted:{$tokenHash}", true, 86400)`).

---

## 6. DUAL-LEDGER AUDIT LOGGING SPECIFICATION

Every HTTP mutation or sensitive view accessed by the operator triggers two independent audit entries:

### 1. Platform Audit Ledger (`platform_audit_logs`)
- Records:
  - `actor_type`: `PlatformUser`
  - `actor_id`: Operator ID
  - `action`: `support.session.executed`
  - `resource_type`: Target Tenant Model
  - `resource_id`: Tenant ID
  - `new_values`: HTTP Request URL, Method, Request Parameters, Ticket ID.

### 2. Tenant Store Audit Ledger (`audit_logs`)
- Records:
  - `user_name`: "KC Support Agent: " . Operator Name
  - `action`: Action performed in store
  - `ticket_id`: External Ticket ID
  - `reason`: Justification provided by operator
- **Transparency Guarantee:** The tenant store owner can navigate to their store's Audit Log and see the exact timestamp, agent name, ticket ID, and actions taken during the support session.
