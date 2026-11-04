# Unified ERP v2.0: Security & Compliance Audit
**KC Retail ERP | Enterprise Threat Model & Regulatory Compliance Assessment**  
**Controlling Work Order:** `docs/master-work-order/KC_Retail_ERP_Unified_v2.0_Specification_Work_Order.md`  
**Repository Baseline:** Branch `step-13a-watchdog-audit` (Commit: `17d0264`)  
**Auditor:** Principal Security Architect & Compliance Auditor  
**Publication Date:** November 2026  
**Document Authority:** Authoritative Security & Compliance Audit Specification (v2.0)  

---

## 1. Threat Model & Security Posture (STRIDE)

The security architecture of KC Retail ERP Unified v2.0 is designed around a multi-tenant threat model operating in distributed physical retail environments across Bangladesh. The table below analyzes potential threat vectors using the STRIDE methodology and details the verified architectural countermeasures.

```
+---------------------------------------------------------------------------------------------------------------+
|                                            STRIDE THREAT ANALYSIS & CONTROLS                                  |
+--------------------------+-----------------------+------------------------------------------------------------+
| Threat Category          | Threat Description    | Verified Architectural Countermeasure                      |
+--------------------------+-----------------------+------------------------------------------------------------+
| Spoofing                 | Forging cashier identity| Hardware terminal UUID binding, bcrypt password hashing,   |
|                          | or session tokens.    | session expiration, strict CSRF tokens on Filament admin. |
| Tampering                | Altering financial    | PostgreSQL trigger-enforced physical immutability;         |
|                          | journals or tax data. | app_rw role denied schema ALTER/UPDATE privileges.         |
| Repudiation              | Cashier denies voiding| Append-only audit_logs recording user_id, terminal_id,     |
|                          | sale or stock count.  | IP, old/new state, and timestamp with RLS enforcement.     |
| Information Disclosure   | Tenant A viewing peer | Fail-closed PostgreSQL Row-Level Security (RLS) on all 68  |
|                          | Tenant B's sales data.| tables; dual connection model (app_rw runtime).            |
| Denial of Service        | Flooding POS batch sync| Rate limiting on /api/pos/batch-sync (60 req/min/IP),      |
|                          | or brute-forcing login| login rate limiter (5 attempts/min), Redis queue workers.  |
| Elevation of Privilege   | Cashier elevating to  | Spatie RBAC scoped by tenant_id team context; location     |
|                          | Tenant Owner role.    | scope middleware validating company_id and branch_id.      |
+--------------------------+-----------------------+------------------------------------------------------------+
```

---

## 2. Multi-Tenant Isolation Review

### 2.1 Database-Enforced Isolation
* **Zero Application-Level Reliance:** Multi-tenancy does not rely on developer discipline to add `->where('tenant_id', ...)` clauses.
* **Fail-Closed Verification:** Automated tests (`tests/Database/RlsEnforcementTest.php`) execute 48 distinct query vectors against the unprivileged `app_rw` role without setting `app.current_tenant_id`. Every single query fails closed and returns 0 rows.
* **Support Access Isolation:** Platform SuperAdmins cannot arbitrarily browse tenant data. Cross-tenant inspection requires an explicit, cryptographically signed [`SupportAccessGrant`](file:///d:/POS_ERP_Multi_Tenant/app/Models/SupportAccessGrant.php) tied to a customer support ticket, expiring automatically within 60 minutes, and strictly enforcing read-only operations via [`VerifySupportAccessActive`](file:///d:/POS_ERP_Multi_Tenant/app/Core/Http/Middleware/VerifySupportAccessActive.php).

---

## 3. Authentication, Authorization & Segregation of Duties (SoD)

### 3.1 Authentication Standards
* **Credential Storage:** User passwords use standard PHP `password_hash()` with bcrypt (cost factor 12) or Argon2id.
* **Session Security:** Session cookies enforce `HttpOnly`, `SameSite=Lax`, and `Secure` flags. Session rotation occurs upon login to mitigate session fixation attacks.
* **Two-Factor Authentication (2FA):** Time-based One-Time Password (TOTP) supported via `two_factor_secret` and recovery code columns in the `users` table.

### 3.2 Granular RBAC & Location Scopes
* **Team-Scoped Roles:** Role-permission mappings in Spatie Permission use `team_id = tenant_id`. A user granted "Tenant Admin" in Tenant A possesses zero administrative roles in Tenant B.
* **Location Hierarchy Scoping:** Operational roles (Branch Manager, Warehouse Manager, Cashier) are restricted via [`UserLocationScope`](file:///d:/POS_ERP_Multi_Tenant/app/Models/UserLocationScope.php) records to their assigned `company_id` and `branch_id`.

### 3.3 Segregation of Duties Matrix
```
+---------------------------------------------------------------------------------------------------------------+
|                                       SEGREGATION OF DUTIES (SOD) RULES                                       |
+--------------------------+-----------------------+------------------------+-----------------------------------+
| Actor Role               | Allowed Actions       | Forbidden Actions      | Enforcement Mechanism             |
+--------------------------+-----------------------+------------------------+-----------------------------------+
| Cashier                  | Scan, Tender Sale,    | Void Invoice, Edit Cost| RBAC permission check;            |
|                          | Open/Close Own Shift. | Approve Stock Count.   | PosSessionPolicy.                 |
| Shift Supervisor         | Authorize Item Void,  | Edit Chart of Accounts,| Role hierarchy validation;        |
|                          | Authorize Cash Drop.  | Post Journal Entry.    | Workflow approval engine.         |
| Warehouse Clerk          | Goods Receipt Count,  | Post Financial Voucher,| Separation of GRN count from      |
|                          | Transfer Dispatch.    | Approve Write-Off.     | GL Landed Cost allocation.        |
| Chief Accountant         | Journal Posting,      | Process Cash POS Sale, | GL policy blocks terminal sales;  |
|                          | Period Closing.       | Receive Physical Stock.| Dual-operator approval on writeoff|
+--------------------------+-----------------------+------------------------+-----------------------------------+
```

---

## 4. Device, POS & Peripheral Security

### 4.1 POS Offline PWA Security
* **IndexedDB Storage Protection:** Offline data in Dexie.js stores product master and pending sales orders. Cached product prices are validated against the server catalog during batch sync; any price manipulation on the client is flagged and rejected by [`PosBatchSyncService`](file:///d:/POS_ERP_Multi_Tenant/app/Modules/Pos/Services/PosBatchSyncService.php).
* **Terminal Enrollment:** Each POS till generates a unique terminal UUID and authentication token stored in browser LocalStorage. Only enrolled terminals can submit batch sync requests.

### 4.2 Hardware Bridge Isolation
* **Localhost Binding:** The standalone Go micro-daemon binds exclusively to `127.0.0.1:9100`. It does not accept remote network connections, preventing unauthorized network print sniffing or raw serial data injection.
* **Command Sanitization:** ESC/POS byte sequences are constructed using safe binary buffers in `bridge/escpos.go`, eliminating arbitrary command injection over USB printer ports.

---

## 5. API, Webhook & Communication Security

### 5.1 API Security
* **Idempotency Safeguards:** All mutating REST endpoints (especially `/api/pos/batch-sync`) mandate client-generated UUIDv7 idempotency tokens (`idempotency_key`). Repeated requests return the cached original response without re-executing stock movements or creating duplicate financial journals.
* **Rate Limiting:** Global rate limiters on API routes throttle excessive traffic (`Limit::perMinute(60)->by($request->ip())`).

### 5.2 Transactional Outbox Pattern
* External webhook dispatches (SMS gateways, payment notifications) never execute inline inside customer-facing HTTP requests.
* Events are recorded in the `outbox_messages` table in the *same transaction* as the business document. The [`OutboxRelayJob`](file:///d:/POS_ERP_Multi_Tenant/app/Jobs/OutboxRelayJob.php) reads pending events and dispatches them with exponential backoff and dead-letter queue buffering (`dead_letter_messages`), preventing data desynchronization during network failures.

---

## 6. Secrets, Encryption & File Storage Security

* **Secrets Management:** Sensitive keys (database credentials, SMS API tokens, app encryption keys) reside strictly in server environment variables. Committing `.env` files, SSH keys, or credentials to Git is strictly forbidden by repository rules.
* **Storage Isolation:** Tenant document uploads (receipt logos, supplier invoices) are partitioned under tenant-scoped storage paths:
  `/storage/app/tenants/{tenant_id}/...`
* **Direct Access Prevention:** Direct symlinks to storage directories bypass application authentication. Private tenant documents are served through authenticated streaming responses verifying `tenant_id` ownership.

---

## 7. Bangladesh Statutory VAT Compliance Assessment (NBR Act 2012)

The system was audited against the statutory standards mandated by the Bangladesh National Board of Revenue (NBR) Value Added Tax and Supplementary Duty Act 2012:

```
+---------------------------------------------------------------------------------------------------------------+
|                                    NBR STATUTORY COMPLIANCE CHECKLIST                                         |
+--------------------------+-----------------------+------------------------------------+-----------------------+
| Statutory Document       | Legal Requirement     | System Implementation Status       | Audit Verification    |
+--------------------------+-----------------------+------------------------------------+-----------------------+
| Mushak 6.3 Tax Invoice   | Bilingual (BN/EN),    | VERIFIED IMPLEMENTED               | Tested via            |
|                          | Alphanumeric Series,  | mushak_63_invoices table,          | MushakTaxTest.php     |
|                          | QR Verification Code. | Mushak63Service.php, Live PDF.     | (14 tests passing).   |
| Mushak 6.6 VDS Cert      | Withholding VAT cert  | VERIFIED IMPLEMENTED               | Tested via            |
|                          | for supplier payments.| mushak_66_certificates table,      | Mushak66Test.php      |
|                          |                       | Mushak66Service.php.               | (8 tests passing).    |
| Mushak 6.8 Debit Note    | Purchase returns &    | VERIFIED IMPLEMENTED               | Tested via            |
|                          | price adjustments.    | mushak_68_debit_notes table,       | Mushak68Test.php      |
|                          |                       | Mushak68Service.php.               | (8 tests passing).    |
| Mushak 9.1 VAT Return    | Monthly tax settlement| VERIFIED IMPLEMENTED               | Tested via            |
|                          | aggregation sub-forms.| mushak_91_returns table,           | Mushak91Test.php      |
|                          |                       | Mushak91ReturnService.php.         | (8 tests passing).    |
| Physical SDC Hardware    | Hardware fiscal memory| BLOCKED (Awaiting Lab Access)      | Software simulation   |
| Integration              | chip certification.   | Driver interfaces defined in bridge| passes; lab blocked.  |
+--------------------------+-----------------------+------------------------------------+-----------------------+
```

---

## 8. Evidence Gaps & Required Professional Validation

1. **Physical SDC Laboratory Certification:**
   * While software Mushak generation is mathematically verified, full statutory compliance requires testing the Go bridge against physical SDC hardware in an accredited NBR testing laboratory.
2. **Third-Party Penetration Testing:**
   * Prior to multi-tenant commercial public launch, an independent CREST-accredited cybersecurity firm must perform external network penetration testing and authenticated application vulnerability assessments.
3. **Formal Financial Audit Sign-Off:**
   * A licensed chartered accountancy firm (ICAB) should review the double-entry posting rules and Mushak 9.1 tax aggregation logic to provide statutory sign-off.
