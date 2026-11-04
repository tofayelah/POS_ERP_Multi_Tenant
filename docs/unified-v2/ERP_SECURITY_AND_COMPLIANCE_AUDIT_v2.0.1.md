# Unified ERP v2.0.1: Security & Compliance Audit
**KC Retail ERP | Security Engineering, RBAC, Hardware Bridge & Regulatory Audit**  
**Controlling Work Order:** `docs/master-work-order/KC_Retail_ERP_Unified_v2.0_Specification_Work_Order.md`  
**Repository Baseline:** Branch `step-13a-watchdog-audit` (Commit: `17d0264`)  
**Auditor & Security Lead:** Principal Security Architect & Lead Compliance Auditor  
**Publication Date:** November 2026  
**Document Revision:** v2.0.1 (Empirical Audit & Architecture Correction)  

---

## 1. Security Architecture Summary & Threat Profile

KC Retail ERP Unified v2.0.1 operates across a distributed architecture: a central cloud web and API application running on **PostgreSQL 17.4 and PHP 8.4**, offline-first browser POS clients running on **Alpine.js and Dexie.js (IndexedDB)**, and a local peripheral daemon running in **Go 1.22**. 

The security architecture addresses four distinct threat surfaces:
1. **Multi-Tenant Isolation & Lateral Movement:** Preventing cross-tenant database access or unauthorized tenant enumeration.
2. **Local Peripheral & Hardware Bridge Attack Surface:** Securing the localhost Go daemon against malicious websites executing Cross-Origin WebSocket hijacking or DNS rebinding.
3. **Role-Based Access Control (RBAC) & Privileged Access:** Enforcing tenant-scoped roles, granular permissions, and location boundaries.
4. **Statutory Immutability & Audit Trail Integrity:** Enforcing append-only audit ledgers and compliance with the Bangladesh VAT Act 2012.

---

## 2. Go Hardware Bridge Architecture & Security Controls

### 2.1 Protocol & Network Architecture Truth
The local peripheral bridge daemon ([`bridge/`](bridge/)) does NOT expose raw HTTP printing endpoints on port 9100. It is implemented as a hardened **WebSocket daemon** running on:
* **WebSocket URL:** `ws://127.0.0.1:9191/ws`
* **Healthcheck URL:** `http://127.0.0.1:9191/health`
* **Default Port:** `9191` (configurable via `bridge.yaml` / environment variables)

```
+---------------------------------------------------------------------------------------------------------------+
|                                    HARDWARE BRIDGE THREAT MITIGATION MATRIX                                   |
+--------------------------+------------------------------------+-----------------------------------------------+
| Attack Vector            | Technical Risk                     | Implemented Security Control                  |
+--------------------------+------------------------------------+-----------------------------------------------+
| Cross-Site WebSocket     | Malicious public website opens WS  | Gorilla `CheckOrigin` origin validation       |
| Hijacking (CSWSH)        | connection to 127.0.0.1:9191       | validates exact tenant frontend domain        |
+--------------------------+------------------------------------+-----------------------------------------------+
| DNS Rebinding Attack     | External attacker domain resolves  | Bridge binds strictly to `127.0.0.1`; Host    |
|                          | to 127.0.0.1 to bypass CORS        | and Origin headers strictly validated         |
+--------------------------+------------------------------------+-----------------------------------------------+
| Brute-Force Auth Token   | Local unauthorized process guesses | IP-based rate limiter blocks after failed     |
| Guessing                 | bridge authentication token        | attempts; mandatory 64-character token check  |
+--------------------------+------------------------------------+-----------------------------------------------+
| Denial of Service /      | Oversized WebSocket payload crashes| Hard 1MB read limit (`conn.SetReadLimit`)     |
| Memory Exhaustion        | Go bridge memory                   | enforced on every client connection           |
+--------------------------+------------------------------------+-----------------------------------------------+
```

### 2.2 Security Controls Implemented in `bridge/pkg/server/`
As implemented in [`bridge/pkg/server/websocket.go`](bridge/pkg/server/websocket.go):

1. **Origin Verification via Gorilla CheckOrigin:**
   ```go
   func (s *Server) setupWebSocketUpgrader() websocket.Upgrader {
       return websocket.Upgrader{
           ReadBufferSize:  4096,
           WriteBufferSize: 4096,
           CheckOrigin: func(r *http.Request) bool {
               origin := r.Header.Get("Origin")
               return s.cfg.IsOriginAllowed(origin)
           },
       }
   }
   ```
   If the browser origin does not match the configured tenant application domain (e.g. `https://dev.kcerp.com`), the Gorilla upgrader automatically aborts with **HTTP 403 Forbidden**.

2. **Pre-Upgrade IP Rate Limiting:**
   ```go
   if s.rateLimiter.IsBlocked(clientIP) {
       http.Error(w, "Too Many Requests: Rate limited due to failed authentication attempts", http.StatusTooManyRequests)
       return
   }
   ```

3. **Memory Bomb Defense (1MB Read Limit):**
   ```go
   conn.SetReadLimit(1024 * 1024) // 1MB maximum payload
   ```

4. **Mandatory 64-Character Token Authentication Handshake:**
   Every WebSocket connection must transmit an authentication payload within a strict handshake timeout:
   ```go
   var authMsg AuthMessage
   err = conn.ReadJSON(&authMsg)
   if err != nil || authMsg.Action != "auth" || authMsg.Token != s.cfg.BridgeToken || len(authMsg.Token) != 64 {
       blocked := s.rateLimiter.RecordFailure(clientIP)
       // Reject and close with code 4401 or 4429
   }
   ```

5. **Loopback IP Binding (DNS Rebinding Defense):**
   The server binds exclusively to `127.0.0.1:9191` (`net.Listen("tcp", "127.0.0.1:9191")`). It does not bind to `0.0.0.0`, ensuring external network interfaces cannot reach the peripheral bridge.

### 2.3 Physical SDC Hardware Testing Gap
* **Current Status:** In automated unit tests (`bridge/pkg/devices/sdc_test.go`), 23/23 tests pass using an internal NBR emulator (`sdc.NewVendorEmulator("NBR-EFD-0042")`).
* **Physical Hardware Gap:** Live testing with physical NBR Sales Data Controller (SDC) hardware is currently **`BLOCKED`**. The development environment runs within Proxmox LXC Container 104, which does not have a physical RS232 / USB-to-Serial converter physically wired to a certified hardware SDC. Physical hardware lab certification is scheduled prior to store deployment.

---

## 3. Role-Based Access Control (RBAC) & Multi-Tenancy

### 3.1 Tenancy Scoping in Spatie Laravel Permission
The application utilizes `spatie/laravel-permission` with multi-tenancy enabled:
* The `team_id` attribute is mapped directly to `tenant_id`.
* [`TenantContext::set()`](app/Core/Tenancy/TenantContext.php) executes:
  ```php
  if (function_exists('setPermissionsTeamId')) {
      setPermissionsTeamId($tenant !== null ? $tenant->id : null);
  }
  ```
* This guarantees that a user granted the `Store Manager` role in Tenant A possesses zero roles and zero permissions in Tenant B.

### 3.2 Implemented Roles and Permission Matrix
1. **SuperAdmin (Platform):** Manages tenant onboarding, subscription plans, and billing. Cannot access tenant commercial data without an active, audit-logged support access grant.
2. **Tenant Admin:** Complete administrative access within a single tenant boundary (company configuration, users, branches, accounting setup).
3. **Store Manager:** Authorized for branch POS shifts, inventory counts, stock transfers, and quotation generation. Restricted from altering General Ledger journal entries or accounting period locks.
4. **Cashier:** Restricted strictly to POS sales, refunds (subject to supervisor override), shift drawer float counts, and Z-Reports within their assigned location scope.
5. **Accountant / Auditor:** Full read access to General Ledger, Trial Balance, Mushak tax returns, and subledger aging reports; write access restricted to draft journal entries.

### 3.3 Location Scoping Enforcement
Cashiers and Store Managers are bounded by location scopes via `user_location_scopes`:
* A cashier assigned to Branch A cannot open a POS register shift or ring sales for Branch B.
* Enforced at the policy layer ([`app/Policies/`](app/Policies/)) and validated in automated test [`tests/Feature/Security/LocationScopingTest.php`](tests/Feature/Security/LocationScopingTest.php).

---

## 4. Audit Trail, Logging & Statutory Compliance

### 4.1 Append-Only Audit Trail
Implemented in [`database/migrations/2026_11_14_000001_create_audit_logs_table.php`](database/migrations/):
* **Table:** `audit_logs` (scoped by `tenant_id` under forced RLS).
* **Columns:** `id`, `tenant_id`, `user_id`, `event_type`, `auditable_type`, `auditable_id`, `old_values` (JSONB), `new_values` (JSONB), `ip_address`, `user_agent`, `created_at`.
* **Database Trigger Protection:** `trg_audit_logs_append_only` prevents any SQL `UPDATE` or `DELETE` on the `audit_logs` table:
  ```sql
  CREATE TRIGGER trg_audit_logs_immutability
  BEFORE UPDATE OR DELETE ON audit_logs
  FOR EACH ROW
  EXECUTE FUNCTION trg_fn_enforce_append_only();
  ```

### 4.2 Audit Trail Limitations & Coverage
* **Covered Events:** User login/logout, support access grants, manual journal postings, sales invoice voiding, inventory adjustments, price list overrides, and POS shift open/close.
* **Audit Gap:** High-volume operational read events (e.g. browsing product catalog or searching customers) are not written to `audit_logs` to prevent storage explosion; they are captured in standard web access logs.

---

## 5. Security & Compliance Findings Register

```
+---------------------------------------------------------------------------------------------------------------+
|                                      SECURITY & COMPLIANCE FINDINGS REGISTER                                  |
+----+----------------------------+----------+------------------------------------------------------------------+
| ID | Area                       | Severity | Technical Description & Status                                   |
+----+----------------------------+----------+------------------------------------------------------------------+
| S1 | Physical SDC Lab Testing   | MEDIUM   | Physical RS232 communication with NBR SDC hardware is currently  |
|    |                            |          | simulated via software emulator. Physical lab sign-off BLOCKED.  |
| S2 | SuperAdmin Token Expiration| LOW      | Support access tokens expire after 4 hours; automated revocation |
|    |                            |          | job verified in tests/Feature/Security/SupportAccessTest.php.    |
| S3 | Secret Rotation Protocol   | LOW      | Go bridge 64-character token rotation requires manual update in  |
|    |                            |          | bridge.yaml and POS terminal record. Recommend automated API.   |
+----+----------------------------+----------+------------------------------------------------------------------+
```

**Related Document Links:**
- Acceptance Criteria Baseline: [ERP_ACCEPTANCE_CRITERIA_v2.0.1.md](docs/unified-v2/ERP_ACCEPTANCE_CRITERIA_v2.0.1.md)
- Database Architecture Review: [ERP_DATABASE_ARCHITECTURE_REVIEW_v2.0.1.md](docs/unified-v2/ERP_DATABASE_ARCHITECTURE_REVIEW_v2.0.1.md)
- Architecture Decision Register: [ERP_ARCHITECTURE_DECISION_REGISTER_v2.0.1.md](docs/unified-v2/ERP_ARCHITECTURE_DECISION_REGISTER_v2.0.1.md)
