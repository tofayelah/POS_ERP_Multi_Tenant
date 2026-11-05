# KC ERP Control Plane: Dashboard KPI & Telemetry Specification

**Document Reference:** `docs/platform-control-plane/CONTROL_PLANE_DASHBOARD_KPI_SPEC.md`  
**Application Target:** KC ERP Control Plane Dashboard (`/platform`)  
**Environment:** Proxmox CT 104 (`dev.kcerp.com`)  
**Design Authority:** Senior Architecture & Financial Systems Team  
**Scope Boundary:** Read-Only Specification (Zero code mutations, Zero schema alterations)

---

## 1. Executive Summary & Non-Negotiable Rules

The **Dashboard KPI Engine** provides executive leadership, billing administrators, and NOC engineers with an instantaneous, trustworthy snapshot of platform commercial performance and operational integrity.

### The Zero-Hardcoding Mandate
During the audit of `PlatformStatsOverviewWidget.php` and `PlatformPlanDistributionChart.php`, multiple instances of fallback demonstration data were uncovered:
```php
// Existing hardcoded fallback in PlatformStatsOverviewWidget:
if ($mrr === 0.0 && $totalTenants > 0) {
    $mrr = 42500.0; // FORBIDDEN IN PRODUCTION
}
// Static decorative chart arrays:
->chart([1, 2, 3, 4, 5, max(6, $totalTenants)])
// Hardcoded status text:
Stat::make('Daily Invariant Audits', '100% Passed')
```

**Non-Negotiable Rule:** Every KPI card and chart rendered in `/platform` must derive **100% of its value from actual database records or live infrastructure probes**. Under no circumstances may a widget render fabricated or fallback values. If a count is zero or data is uncalculated, the widget must display `0`, `৳0.00`, or an explicit `Uncalculated / Pending Run` badge.

---

## 2. KPI Portfolio Overview

The improved Control Plane dashboard organizes metrics into two distinct visual tiers:
1. **Tier 1: Commercial & Fleet Health (Top Strip - 8 Cards in a 4x2 Responsive Grid)**
   - Total Tenants & Status Breakdown (Active, Trial, Suspended)
   - Platform MRR & ARR (Recurring SaaS Revenue)
   - SaaS Subscription Invoicing & Collections (Overdue vs Collected MTD)
   - Store Fleet & Active POS Edge Terminals
2. **Tier 2: Infrastructure & Operational Invariants (Telemetry Strip - 6 Status Cards)**
   - Daily Invariant Watchdog Status
   - PostgreSQL 17 Forced RLS Coverage
   - Horizon Queue Latency & Failed Jobs
   - Go POS Bridge & Edge Sync Error Rate
   - Backup Freshness & WAL Replication Continuity
   - Audit Trail Cryptographic Seal

---

## 3. Tier 1: Commercial & Fleet KPI Specifications

### Metric 1: Total Retail Tenants & Status Distribution
- **Card Title:** `Retail Organizations`
- **Primary Value:** Total count of all tenant organizations.
- **Description / Badges:** Active chains vs Suspended/Trial chains.
- **Mathematical Definition:**
  $$\text{Total Tenants} = \sum_{\text{status} \in \text{All}} 1$$
- **Eloquent Implementation:**
  ```php
  $total = Tenant::count();
  $active = Tenant::where('status', 'active')->count();
  $trial = Tenant::where('status', 'trial')->count();
  $suspended = Tenant::whereIn('status', ['suspended', 'in_arrears'])->count();
  ```
- **Historical Sparkline (7-day trend):**
  Derived from `Tenant::selectRaw('DATE(created_at) as date, count(*) as count')->groupBy('date')->orderBy('date')->pluck('count')->toArray()`.
- **Threshold Rules:**
  - `Green (Success)`: $\text{Suspended} = 0$
  - `Amber (Warning)`: $\text{Suspended} > 0$ and $\text{Suspended} \le 0.1 \times \text{Total}$
  - `Red (Danger)`: $\text{Suspended} > 0.1 \times \text{Total}$

---

### Metric 2: Platform MRR (Monthly Recurring Revenue)
- **Card Title:** `Platform MRR`
- **Primary Value:** `৳ {Formatted Value}` (BDT)
- **Description:** Normalized monthly recurring subscription revenue across all active paying tenant subscriptions.
- **Mathematical Definition:**
  $$\text{MRR} = \sum_{s \in \text{Active Subscriptions}} \begin{cases} \text{price\_bdt} & \text{if interval} = \text{'monthly'} \\ \frac{\text{price\_bdt}}{12} & \text{if interval} = \text{'annual'} \end{cases}$$
- **Eloquent Implementation (Strict `brick/math` rounding):**
  ```php
  $activeSubs = TenantSubscription::with('plan')
      ->where('status', 'active')
      ->get();

  $mrr = BigDecimal::of(0);
  foreach ($activeSubs as $sub) {
      if (! $sub->plan) continue;
      $price = BigDecimal::of((string) $sub->plan->price_bdt);
      if ($sub->plan->billing_interval === 'annual') {
          $mrr = $mrr->plus($price->dividedBy(BigDecimal::of(12), 2, RoundingMode::HALF_UP));
      } else {
          $mrr = $mrr->plus($price);
      }
  }
  $formattedMrr = '৳ ' . number_format((float) (string) $mrr, 0);
  ```
- **Threshold Rules:**
  - `Primary (Amber)`: Active paying subscriptions present.
  - `Muted (Gray)`: $\text{MRR} = 0$ (e.g. all tenants on trial).

---

### Metric 3: Platform ARR (Annual Recurring Revenue)
- **Card Title:** `Platform ARR (Run Rate)`
- **Primary Value:** `৳ {Formatted Value}`
- **Description:** Annualized run-rate of recurring subscription contracts.
- **Mathematical Definition:**
  $$\text{ARR} = \text{MRR} \times 12$$
- **Eloquent Implementation:**
  ```php
  $arr = $mrr->multipliedBy(BigDecimal::of(12));
  $formattedArr = '৳ ' . number_format((float) (string) $arr, 0);
  ```

---

### Metric 4: Subscription Receivables & Overdue Dunning
- **Card Title:** `Subscription Receivables`
- **Primary Value:** `৳ {Overdue Sum}` / `{Count} Invoices`
- **Description:** Unpaid commercial subscription invoices past their due date.
- **Mathematical Definition:**
  $$\text{Overdue Amount} = \sum_{\substack{i \in \text{Platform Invoices} \\ \text{status} = \text{'overdue'}}} \text{total\_amount}$$
- **Query (once `platform_invoices` schema lands):**
  ```php
  $overdueCount = PlatformInvoice::where('status', 'overdue')->count();
  $overdueSum = PlatformInvoice::where('status', 'overdue')->sum('total_amount');
  ```
- **Threshold Rules:**
  - `Green (Success)`: Overdue Amount $= 0$
  - `Amber (Warning)`: Overdue Invoices between 1 and 3
  - `Red (Danger)`: Overdue Invoices $> 3$ or overdue for $> 14$ days

---

### Metric 5: Collected Platform Revenue (MTD)
- **Card Title:** `Collected Revenue (MTD)`
- **Primary Value:** `৳ {Collected Sum}`
- **Description:** Cash collections received during the current calendar month for SaaS subscriptions.
- **Mathematical Definition:**
  $$\text{Collected MTD} = \sum_{\substack{p \in \text{Platform Payments} \\ \text{paid\_at} \ge \text{Start of Month}}} \text{amount}$$
- **Eloquent Implementation:**
  ```php
  $startOfMonth = now()->startOfMonth();
  $collectedMtd = PlatformPayment::where('status', 'completed')
      ->where('paid_at', '>=', $startOfMonth)
      ->sum('amount');
  ```

---

### Metric 6: Physical Outlets & Branches Fleet
- **Card Title:** `Retail Outlets Fleet`
- **Primary Value:** `{Total Outlets} Outlets`
- **Description:** Total physical storefronts and warehouses operated across all tenant chains.
- **Eloquent Implementation:**
  ```php
  $outletCount = Branch::count();
  $avgOutletsPerTenant = $total > 0 ? round($outletCount / $total, 1) : 0;
  ```

---

### Metric 7: Registered Edge Terminals & Active POS Sessions
- **Card Title:** `POS Terminal Fleet`
- **Primary Value:** `{Active Sessions} / {Total Registered}`
- **Description:** Offline-first PWA cash registers currently operating open cashier shifts.
- **Mathematical Definition:**
  $$\text{Active Sessions} = \text{PosShift::where('status', 'open')->count()}$$
- **Eloquent Implementation:**
  ```php
  $totalTerminals = PosTerminal::count();
  $openShifts = PosShift::where('status', 'open')->count();
  ```
- **Threshold Rules:**
  - `Info (Sky Blue)`: Normal daily cashier operations.

---

### Metric 8: Active Trials & Growth Pipeline
- **Card Title:** `Trial Organizations`
- **Primary Value:** `{Trial Count} Chains`
- **Description:** Tenants currently in their 14-day evaluation window.
- **Eloquent Implementation:**
  ```php
  $expiringSoon = TenantSubscription::where('status', 'trial')
      ->where('trial_ends_at', '<=', now()->addDays(3))
      ->count();
  ```

---

## 4. Tier 2: Infrastructure & Operational Invariant Specifications

### Metric 9: Daily Invariant Watchdog Status
- **Card Title:** `Daily Invariant Watchdog`
- **Primary Value:** `100% Passed` or `Failed Invariants: N`
- **Description:** Automated cross-tenant reconciliation of GL zero-balance, inventory valuation layers, VAT ledgers, and hash chains.
- **Real-Time Data Source:**
  Query the most recent run recorded in `invariant_audit_runs`:
  ```php
  $latestRun = DB::table('invariant_audit_runs')->latest('executed_at')->first();
  $status = $latestRun?->is_passed ? '100% Passed' : 'Discrepancy Detected';
  $badgeColor = $latestRun?->is_passed ? 'success' : 'danger';
  $lastRunTime = $latestRun ? Carbon::parse($latestRun->executed_at)->diffForHumans() : 'Never';
  ```
- **Threshold Rules:**
  - `Green (Success)`: All 4 invariant audits (GL, Stock, VAT, Hashes) passed cleanly.
  - `Red (Danger)`: Any invariant failed; immediate link to audit failure report.

---

### Metric 10: PostgreSQL 17 Forced RLS Coverage
- **Card Title:** `Forced RLS Coverage`
- **Primary Value:** `{Compliant Tables} / {Total Tenant Tables}` (e.g. `74 / 74 Forced`)
- **Description:** Verification that every tenant table has both `rowsecurity = true` and `forcerowsecurity = true` in PostgreSQL catalog.
- **Real-Time SQL Probe:**
  ```sql
  SELECT 
      count(*) AS total_tables,
      count(*) FILTER (WHERE c.relrowsecurity = true AND c.relforcerowsecurity = true) AS compliant_tables
  FROM pg_tables t
  JOIN pg_class c ON c.relname = t.tablename
  JOIN pg_namespace n ON n.oid = c.relnamespace AND n.nspname = t.schemaname
  WHERE t.schemaname = 'public'
    AND EXISTS (
        SELECT 1 FROM information_schema.columns col 
        WHERE col.table_schema = 'public' 
          AND col.table_name = t.tablename 
          AND col.column_name = 'tenant_id'
    );
  ```
- **Threshold Rules:**
  - `Green (Success)`: $\text{Compliant Tables} = \text{Total Tables}$ (Currently 74/74).
  - `Red (Danger)`: $\text{Compliant Tables} < \text{Total Tables}$ (CRITICAL SECURITY ALERT).

---

### Metric 11: Queue Workers & Horizon Latency
- **Card Title:** `Queue Backlog & Workers`
- **Primary Value:** `{Pending Jobs} Pending • {Failed Jobs} Failed`
- **Description:** Real-time throughput of Laravel Horizon and Redis background workers.
- **Probe Implementation:**
  ```php
  $failedJobsCount = DB::table('failed_jobs')->count();
  $queueSize = Queue::size('default') + Queue::size('audit') + Queue::size('sync');
  ```
- **Threshold Rules:**
  - `Green (Success)`: $\text{Failed Jobs} = 0$ and $\text{Pending Jobs} < 100$
  - `Amber (Warning)`: $\text{Failed Jobs} \le 5$ or $\text{Pending Jobs} \ge 100$
  - `Red (Danger)`: $\text{Failed Jobs} > 5$ or workers stalled for $> 5$ mins

---

### Metric 12: Go POS Bridge Connectivity
- **Card Title:** `Go POS Bridge (127.0.0.1:8181)`
- **Primary Value:** `Connected (N ms)` or `Offline`
- **Description:** HTTP health check probing local hardware communication daemon.
- **Probe Implementation:**
  ```php
  try {
      $start = microtime(true);
      $response = Http::timeout(1)->get('http://127.0.0.1:8181/health');
      $latency = round((microtime(true) - $start) * 1000);
      $bridgeStatus = $response->successful() ? "Online ({$latency}ms)" : "Degraded";
  } catch (\Throwable $e) {
      $bridgeStatus = "Offline";
  }
  ```

---

### Metric 13: Backup Freshness & Point-In-Time Recovery (PITR)
- **Card Title:** `Backup Freshness`
- **Primary Value:** `Snapshot: {Age} • WAL: Live`
- **Description:** Verifies that physical database dumps and WAL archival streams are fresh.
- **Probe Implementation:**
  Inspects the file modification timestamp of the latest backup artifact in storage:
  ```php
  $latestBackup = Storage::disk('backups')->files('postgres');
  // Check age < 24 hours and WAL archive delay < 10 minutes
  ```

---

### Metric 14: Audit Trail Hash Chain Sealed
- **Card Title:** `Audit Trail Seal`
- **Primary Value:** `HMAC Validated`
- **Description:** Confirms that `audit_logs` table has not suffered retroactive record mutation or hash chain breaks.

---

## 5. Caching, Polling & Refresh Architecture

To ensure the Control Plane dashboard does not impose unnecessary query load on the primary PostgreSQL 17 instance, all metric calculations must adhere to strict caching policies:

| Metric Category | Refresh Interval | Cache Driver & Tag | Invalidation Trigger |
| :--- | :--- | :--- | :--- |
| **Tenant & Fleet Counts** | 60 seconds | Redis (`tag:platform_stats`) | `TenantCreated`, `TenantSuspended`, `BranchCreated` |
| **MRR / ARR Financials** | 5 minutes | Redis (`tag:platform_billing`) | `SubscriptionCreated`, `PlanUpdated`, `PaymentCompleted` |
| **Overdue Receivables** | 5 minutes | Redis (`tag:platform_billing`) | `InvoiceCreated`, `InvoicePaid`, `DunningExecuted` |
| **Queue & Worker Health** | 15 seconds | Redis (`tag:platform_telemetry`) | Livewire Polling (`wire:poll.15s`) |
| **Forced RLS Coverage** | 10 minutes | Redis (`tag:platform_security`) | Post-migration event, or Manual "Probe Now" click |
| **Go POS Bridge Ping** | 30 seconds | In-Memory (No Cache) | Real-time probe during page render |
| **Invariant Watchdog** | 1 hour | Redis (`tag:platform_watchdog`) | `PilotDailyCheckCompleted` event |

---

## 6. Filament Widget Implementation Matrix

To present these metrics cleanly without visual clutter, the widgets will be structured as:
1. `PlatformCommercialOverviewWidget` (Metrics 1, 2, 3, 4, 5) - Sort `1`
2. `PlatformFleetOverviewWidget` (Metrics 6, 7, 8) - Sort `2`
3. `PlatformInfrastructureTelemetryWidget` (Metrics 9, 10, 11, 12, 13, 14) - Sort `3`
4. `PlatformTenantsTableWidget` (Dynamic Registered Tenants List) - Sort `4`

This eliminates monolithic 80-line widget files and enforces separation of concerns across commercial finance and technical operations.
