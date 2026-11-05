# KC ERP CONTROL PLANE - PHASE 1 KPI & TELEMETRY VALIDATION

**Application:** KC ERP Control Plane (`/platform`)  
**Scope:** Dashboard Metrics, Financial Precision, and System Telemetry  
**Environment:** Proxmox CT 104 (`dev.kcerp.com`)  
**Date:** 2026-11-05  

---

## 1. REMOVAL OF HARDCODED / FABRICATED DATA

Prior to Phase 1, the control plane dashboard exhibited several static mocks, simulated sparklines, and fallback values. In Phase 1, all synthetic data was eliminated.

| Metric / Component | Previous State | Phase 1 Verified State | Validation Proof |
|---|---|---|---|
| Monthly Recurring Revenue (MRR) | Fallback to `$42,500.00` if no data existed | Computed dynamically via `PlatformTelemetryService::calculateMrr()` using `brick/math`. If zero subscriptions, honestly reports `$0.00` | `PlatformTelemetryServiceTest::test_financial_metrics_never_returns_fabricated_mrr_fallback` |
| Annual Recurring Revenue (ARR) | Static 12x calculation of fallback MRR | Exact calculation: `MRR * 12` normalized via `BigDecimal` | Tested with active/trial/canceled subscription states |
| Tenant Sparkline Chart | Static hardcoded array `[4, 7, 10, 12, 14, 18, 24]` | Dynamic count of tenant creations over past 7 calendar days | Tested in `PlatformStatsOverviewWidget` |
| Plan Distribution Chart | Static mock distribution `[3, 1, 2]` | Live count of `TenantSubscription` records grouped by `Plan::name`. If no subscriptions exist, displays an honest empty state | `PlatformPlanDistributionChartTest` |
| System Health Widget | Static HTML cards with hardcoded "99.98%" badges | Live probe measuring latency against PostgreSQL 17, Redis 7 ping, RLS catalog query, queue failure count, and Go POS bridge | `PlatformSystemHealthWidgetTest` |
| Quick Actions Launchpad | Static hardcoded "Downtown Flagship / Gulshan Branch" cards | Dynamically queries active `Tenant` records and generates direct dashboard launch links | `PlatformQuickActionsWidgetTest` |

---

## 2. FINANCIAL PRECISION: `brick/math` SPECIFICATION

In compliance with Non-Negotiable Rule 6 of the retail ERP rules:
> *"Money uses brick/math and numeric columns, is a string in JSON, and rounds half-up to 2 places. No floats anywhere in money code."*

### Implementation in `PlatformTelemetryService`

```php
public function calculateMrr(): string
{
    $activeSubscriptions = TenantSubscription::with('plan')
        ->whereIn('status', ['active', 'past_due', 'trialing'])
        ->get();

    if ($activeSubscriptions->isEmpty()) {
        return '0.00';
    }

    $totalMrr = BigDecimal::of(0);

    foreach ($activeSubscriptions as $subscription) {
        $plan = $subscription->plan;
        if (! $plan || ! $plan->price) {
            continue;
        }

        $planPrice = BigDecimal::of((string) $plan->price);

        // Normalize billing intervals to 30-day MRR
        if ($plan->billing_interval === 'yearly') {
            $monthlyEquivalent = $planPrice->dividedBy(
                BigDecimal::of(12),
                4,
                RoundingMode::HALF_UP
            );
            $totalMrr = $totalMrr->plus($monthlyEquivalent);
        } else {
            $totalMrr = $totalMrr->plus($planPrice);
        }
    }

    return (string) $totalMrr->toScale(2, RoundingMode::HALF_UP);
}
```

### Key Mathematical Guarantees
1. No floating-point rounding errors or IEEE 754 precision drift.
2. Exact scale normalization to 2 decimal places using `RoundingMode::HALF_UP`.
3. String output representation guaranteed.

---

## 3. LIVE SUBSYSTEM TELEMETRY PROBES

### PostgreSQL 17 DB Probe
- **Method:** `DB::connection('pgsql')->select('SELECT 1 as ping')`
- **Metric Captured:** Execution duration in milliseconds.
- **Fail State:** If query times out or fails, returns `status: degraded`, latency `-1`, with caught exception message.

### PostgreSQL RLS Catalog Probe
- **Method:** Direct query on `pg_class` and `pg_policy` in schema `'public'`.
- **Target:** Validates that `c.relrowsecurity = true` across all 74 tenant tables.
- **Fail State:** If any tenant table has `relrowsecurity = false`, returns `status: critical` with an alert detailing non-compliant tables.

### Redis 7 Cluster Probe
- **Method:** `Redis::connection()->ping()`
- **Metric Captured:** Round-trip latency in milliseconds.
- **Fail State:** Returns `status: degraded` if Redis is unreachable.

### Horizon / Queue Worker Probe
- **Method:** `DB::table('failed_jobs')->count()`
- **Logic:**
  - 0 failed jobs: `status: operational`, message: `Healthy (0 failed)`.
  - 1-9 failed jobs: `status: warning`, message: `X failed jobs`.
  - 10+ failed jobs: `status: critical`, message: `Attention: X failed jobs`.

### Go POS Bridge Probe
- **Method:** `Http::timeout(1.5)->get('http://127.0.0.1:8181/healthz')`
- **Fail State:** Reports `status: offline` if bridge daemon is stopped.
