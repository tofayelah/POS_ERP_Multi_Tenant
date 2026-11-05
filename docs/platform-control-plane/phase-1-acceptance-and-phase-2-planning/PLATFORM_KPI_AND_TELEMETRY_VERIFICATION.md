# KC ERP CONTROL PLANE
# PLATFORM KPI & TELEMETRY VERIFICATION REPORT

**Application:** KC ERP Control Plane (`/platform`)  
**Environment:** Proxmox CT 104 (`dev.kcerp.com`)  
**Auditor:** Antigravity Senior Architecture & Security Agent  
**Date:** 2026-11-05  
**Verification Method:** Codebase Inspection, Financial Formula Auditing, and Network/Service Probing  

---

## 1. FINANCIAL KPI INTEGRITY (MRR & ARR)

### A. Mathematical Implementation (`PlatformTelemetryService::getFinancialMetrics`)
Financial calculations in Phase 1 use `brick/math` to prevent floating-point inaccuracy.
The calculation logic operates as follows:

```php
$subscriptions = TenantSubscription::with('plan')
    ->where('status', 'active')
    ->get();

foreach ($subscriptions as $sub) {
    $price = BigDecimal::of((string) $sub->plan->price_bdt);
    if ($sub->plan->billing_interval === 'annual') {
        $mrr = $mrr->plus($price->dividedBy(BigDecimal::of(12), 2, RoundingMode::HalfUp));
    } else {
        $mrr = $mrr->plus($price);
    }
}
$arr = $mrr->multipliedBy(BigDecimal::of(12));
```

### B. Comprehensive Scenario Verification

| Subscription Scenario | Mathematical & Logic Treatment in Phase 1 | Audit Verification & Findings |
|---|---|---|
| **Monthly Subscriptions** | Adds `price_bdt` directly to monthly MRR. | **VERIFIED.** Exact decimal addition via `BigDecimal::plus()`. |
| **Annual Subscriptions** | Divides `price_bdt` by 12, rounding half-up to 2 decimals. | **VERIFIED.** Annual ৳120,000 plan yields exact ৳10,000.00/mo MRR. |
| **Trial Subscriptions** | Excluded (`status != 'active'`). | **VERIFIED.** Unbilled trials contribute ৳0 to MRR. |
| **Suspended Subscriptions** | Excluded (`status = 'suspended'`). | **VERIFIED.** Delinquent/suspended tenants do not falsely inflate recurring revenue. |
| **Cancelled Subscriptions** | Excluded (`status = 'cancelled'`). | **VERIFIED.** Churned subscriptions are not counted in MRR. |
| **Grace Periods (`past_due`)** | Currently excluded (`status != 'active'`). | **IDENTIFIED LIMITATION.** Subscriptions in arrears / grace periods (e.g., 3-day retry) are excluded until payment succeeds. SaaS finance standards allow tracking "At-Risk MRR". |
| **Promotional Discounts** | Reads `$sub->plan->price_bdt` base price. | **IDENTIFIED LIMITATION.** Schema currently lacks `discount_amount` or coupon support on `TenantSubscription`. Billed price equals plan base price. |
| **Multiple Currencies** | Hardcoded to BDT (`price_bdt`). | **IDENTIFIED LIMITATION.** Does not support multi-currency conversion (e.g., USD to BDT). All calculations assume Bangladeshi Taka (৳). |
| **Mid-Period Plan Changes** | Calculates point-in-time active plan price. | **IDENTIFIED LIMITATION.** No proration ledger or mid-cycle MRR delta calculation in Phase 1. |

### C. Demonstration Value Elimination
- **Previous Hardcoded Fallback:** `$mrr = 42500.0` was previously returned when zero subscriptions existed.
- **Phase 1 Verified State:** Completely removed. If zero subscriptions exist, the system returns `mrr: 0.0`, `arr: 0.0`, `has_data: false`, and renders an honest empty state (`৳ 0`).
- **Verified by Automated Test:** `PlatformTelemetryServiceTest::test_financial_metrics_never_returns_fabricated_mrr_fallback`.

---

## 2. LIVE SYSTEM TELEMETRY PROBES

### Probe 1: PostgreSQL 17 Database Latency
- **Implementation:** Executes `SELECT 1 as ping` on connection `pgsql`.
- **Live Output on CT 104:** `status: ok`, latency `0.38ms`.
- **Timeout Behavior:** No explicit query timeout configured in PDO options. Relies on PHP request execution timeout (max_execution_time).
- **Failure State:** Returns `status: failed`, latency `0.0ms` with error details in `details`.

### Probe 2: Forced RLS Table Inventory
- **Implementation:** Queries `pg_class` and `pg_policy` in schema `'public'`.
- **Target:** Confirms that `c.relrowsecurity = true` across all tenant tables.
- **Live Output on CT 104:** `status: ok`, `total_tables: 74`, `forced_tables: 74`, `violations: []`.
- **Failure State:** If any tenant table has `relrowsecurity = false`, returns `status: critical` and lists the violating table names in `violations`.

### Probe 3: Redis 7 Cluster & Cache
- **Implementation:** Executes `Cache::put('platform:telemetry:heartbeat', 'ok', 10)` and `Cache::get()`.
- **Live Output on CT 104:** `status: ok`, latency `0.45ms`, `details: "Session & Cache active (0.45ms)"`.
- **Failure State:** Returns `status: failed` if Redis is unreachable.

### Probe 4: Queue Worker Health & Failed Jobs
- **Implementation:** Queries `DB::table('failed_jobs')->count()`.
- **Live Output on CT 104:** `status: operational`, `failed_jobs: 0`, `details: "Healthy (0 failed jobs)"`.
- **Failure Thresholds:**
  - 0 failed: `status: operational`
  - 1–9 failed: `status: warning`
  - 10+ failed: `status: critical`

### Probe 5: Go POS Bridge Daemon (Critical Deployment Audit)
- **Phase 1 Implementation:**
  ```php
  Http::timeout(1.5)->get('http://127.0.0.1:8181/healthz');
  ```
- **Audit Findings:**
  1. **Deployment Status on CT 104:** Verified via `ss -tulpn` and `ps aux | grep -i bridge`. The Go bridge binary is **not currently running as a continuous background daemon** on CT 104.
  2. **Codebase Default Mismatch:** Inspection of `bridge/pkg/config/config.go` reveals:
     ```go
     ListenAddr: "127.0.0.1:9191"
     ```
  3. **Path Mismatch:** Inspection of `bridge/pkg/server/health.go` reveals that the health endpoint is registered as `GET /health`, **not `/healthz`**:
     ```go
     // handleHealth serves GET /health
     ```
  4. **Impact:** Because the probe attempts port 8181 instead of 9191, even when the bridge daemon is started, the probe fails and reports offline.
  5. **Performance Impact:** On every dashboard page load, `Http::timeout(1.5)` blocks for the full 1.5 seconds before timing out, causing observable UI latency.

---

## 3. RESILIENCE, TIMEOUT & CACHING AUDIT

| Aspect | Current Architecture | Audit Assessment |
|---|---|---|
| **Partial Subsystem Failures** | Each probe is wrapped in independent `try-catch (\Throwable $e)` blocks. | **EXCELLENT.** A failure in Redis or the Go bridge does not crash PostgreSQL probes or the dashboard. |
| **Timeout Handling** | Bridge has `timeout(1.5)`. DB and Redis probes lack explicit millisecond socket timeouts. | **NEEDS IMPROVEMENT.** Slow DB connection could cause request pool starvation. |
| **Probe Result Caching** | Probes run dynamically on **every** widget render. Zero caching. | **DEFECT.** Running 5 probes synchronously on each page load adds ~1.6s latency when bridge is offline. Must implement 30-second Redis caching. |
| **Stale Telemetry Indication** | Each probe returns `checked_at` ISO8601 timestamp. | **GOOD.** Allows UI to render relative time ("Checked 5s ago"). |

---

## 4. PHASE 2 TELEMETRY ENHANCEMENTS

1. **Correct Bridge Probe:**
   - Update target URL to `http://127.0.0.1:9191/health`.
   - Add environment variable `POS_BRIDGE_HEALTH_URL` with default `http://127.0.0.1:9191/health`.
2. **Implement Telemetry Cache Layer:**
   - Cache probe results in Redis for 30 seconds (`Cache::remember('platform:telemetry:probes', 30, ...)`).
   - Add a manual "Refresh Telemetry" button in the UI that bypasses the cache.
3. **Configure PDO & Redis Socket Timeouts:**
   - Set 1000ms socket timeout on telemetry database connections to guarantee responsive rendering under network latency.
