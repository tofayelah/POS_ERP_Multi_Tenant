# Milestone M6 Pilot Gate Readiness Report

**Date:** 2 November 2026  
**Milestone:** M6 (Step 13: Outbox, Audit, Watchdog, Import & Pilot QA)  
**Status:** PASS / READY FOR PILOT (Weeks 20–21)

---

## 1. Executive Summary

Milestone M6 completes the foundational enterprise resiliency, data portability, and pilot-gating capabilities of the Retail ERP platform. All critical gate reviews (`NEW-30`, `NEW-31`, `MOD-07`, `TEN-06`, `OBS-01`) have been designed, implemented with tests first, and verified against PostgreSQL 17, forced RLS, Pint, and PHPStan Level 8.

---

## 2. Review Findings Closed & Architectural Directives

### 2.1 NEW-30 (Critical Gate): Cross-Tenant Business Integrity Watchdog
- **Architecture:** `App\Core\Watchdog\Services\IntegrityWatchdogService` runs under `app_rw`. It iterates all active tenants in isolated database transactions and executes `SELECT set_config('app.current_tenant_id', :id, true)`.
- **Invariants Audited:**
  1. Posted journal balance assertion ($\sum \text{debits} = \sum \text{credits}$ per entry).
  2. Inventory ledger balance assertion ($\text{stock\_balances.quantity\_on\_hand} = \sum \text{stock\_movements.quantity}$).
  3. Orphan negative stock detection: Negative stock is permitted **only** when accompanied by an `OPEN` record in `stock_oversell_exceptions` (ADR-016); all other negative balances raise critical alerts.
- **Fail-Closed Rule:** The service fails loudly with a `RuntimeException` if tenant context is uninitialized or empty—it never reports "all clear" on missing context.
- **Console Interface:** `php artisan integrity:check-books {--tenant=}` (scheduled hourly).

### 2.2 NEW-31 (High Gate): Zero-Lock Tamper-Evident Hash-Chained Audit Trail
- **Zero-Lock Insertion:** Cashier checkouts and transactions insert audit rows with `status = 'pending_hash'` and UUIDv7 IDs with zero lock contention.
- **Per-Tenant Hashing Worker:** `App\Core\Audit\Workers\BuildAuditHashChainWorker` batches pending logs and computes sequential cryptographic SHA-256 links (`sha256(prev_hash || payload_hash)`) under an isolated per-tenant advisory lock `pg_advisory_xact_lock(hashtext('audit_hasher_' || tenant_id))`.
- **Database Immutability Trigger:** `trg_audit_logs_immutable` prohibits any `DELETE`, and permits an `UPDATE` only for the one-time transition from `pending_hash` to `verified` with valid chain metrics.
- **Verification Traversal:** `App\Core\Audit\Services\VerifyAuditHashChainService` traverses strictly in `chain_position` order, correctly handling out-of-order transaction commits without false tamper alarms.
- **Console Interface:** `php artisan audit:verify-chain {tenant_id?}`.

### 2.3 MOD-07 (Medium): Transactional Outbox & Webhook Dispatch with Post-DNS SSRF Protection
- **Outbox Pattern:** Table `transactional_outbox` with composite key `(tenant_id, id)` and forced RLS. Written within the same database transaction as the business change.
- **Asynchronous Worker:** `App\Core\Outbox\Services\OutboxDispatcherService` polls events using `FOR UPDATE SKIP LOCKED`, sets tenant context per tenant batch, applies exponential backoff retries, and dead-letters after 5 failed attempts.
- **HMAC Signatures & Replay Prevention:** `WebhookDispatcher` attaches `X-Signature: sha256={hmac}` and `X-Timestamp: {epoch}`. Verifier strictly rejects requests older than 300 seconds.
- **Post-DNS SSRF Protection:** `App\Core\Webhooks\Services\SsrfProtectionService` resolves target hostnames to socket IPs before HTTP connection, refusing all loopback (`127.0.0.0/8`, `::1`), private RFC 1918 (`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`), link-local/cloud metadata (`169.254.0.0/16`, `fe80::/10`), CGNAT (`100.64.0.0/10`), and multicast ranges.

### 2.4 TEN-06 (High Gate): Master Data Import & Single-Tenant Export/Restore
- **Master Data Import:** `MasterDataImportService` imports products, variants, bar codes, and customers. Features a `dry-run` validation mode that detects and reports all row errors without writing to the database, followed by idempotent upsert execution.
- **Opening Balances:** Posts a single balanced journal entry to the GL with matching debits and credits, rejecting any unbalanced balance sheet input.
- **Tenant Export & Restore:**
  - `TenantExportService` dynamically computes the topological dependency order of all tenant tables via `pg_constraint` and exports records with financial/stock checksums.
  - `TenantRestoreService` restores an archive into any target tenant, dynamically remapping UUID primary and foreign keys to prevent conflict with existing data in the same cluster while guaranteeing exact trial balance and stock valuation equality.
- **Controlled Offboarding:** `TenantOffboardingService` archives tenant data for PDPO compliance and purges tenant rows in reverse topological order, honoring all `ON DELETE RESTRICT` foreign key constraints without database cascades.

### 2.5 OBS-01 (High Gate): Pilot Gate QA, Observability & Security Audits
- **Health & Readiness Endpoints:**
  - `/health`: Liveness probe returning HTTP 200.
  - `/ready`: Readiness probe verifying PostgreSQL query execution, Redis cache write/read, and filesystem storage write access.
- **OWASP Security Headers:** `SecurityHeadersMiddleware` enforces `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, and `Content-Security-Policy`.
- **k6 Load Test Suites:**
  - `loadtests/eid-peak.js`: Sustained 15 checkouts/second load testing against the POS batch sync API with 95th percentile latency target < 200ms.
  - `loadtests/sync-storm.js`: Burst sync storm simulating 50 reconnecting terminals with exponential backoff and `Retry-After` adherence.
- **Nightly Automated CI:** `.github/workflows/nightly.yml` runs full regressions, concurrency suites, RLS checks, static analysis, and security dependency audits.

---

## 3. Test & Verification Summary

| Test Suite | File | Tests / Assertions | Status |
|---|---|---|---|
| Business Integrity Watchdog | `tests/Feature/Watchdog/IntegrityWatchdogTest.php` | 5 passed (18 assertions) | PASS |
| Hash-Chained Audit Trail | `tests/Database/AuditTrailHashChainTest.php` | 6 passed (29 assertions) | PASS |
| Transactional Outbox & Webhooks | `tests/Feature/Outbox/TransactionalOutboxTest.php` | 8 passed (50 assertions) | PASS |
| Master Data Import & Export/Restore | `tests/Feature/DataPortability/TenantImportExportTest.php` | 5 passed (39 assertions) | PASS |
| Pilot Gate Health & OWASP | `tests/Feature/PilotGateTest.php` | 3 passed (8 assertions) | PASS |

**Dev Server Verification:**
- Pint Code Formatter: PASS (0 style issues)
- PHPStan Level 8 Analysis: PASS (0 errors)
- RLS Verification (`php artisan db:check-rls`): PASS (All 68 tenant tables enforce forced RLS)
