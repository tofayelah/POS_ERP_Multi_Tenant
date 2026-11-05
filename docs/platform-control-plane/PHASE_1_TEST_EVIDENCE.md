# KC ERP CONTROL PLANE - PHASE 1 TEST EVIDENCE

**Application:** KC ERP Control Plane (`/platform`)  
**Environment:** Proxmox CT 104 (`dev.kcerp.com`)  
**PHP Version:** 8.4.27  
**PostgreSQL Version:** 17.4  
**Laravel Framework:** 13.39.0  
**Test Runner:** PHPUnit via `php artisan test` on `pgsql` connection / `erp_test` database  
**Date:** 2026-11-05  

---

## 1. PLATFORM FEATURE TEST EXECUTION (`tests/Feature/Platform/`)

### Execution Command:
```bash
php artisan test tests/Feature/Platform/
```

### Raw CT 104 Output:
```
   PASS  Tests\Feature\Platform\PlatformAuditLogTest
  ✓ audit service records action with actor and payload hash             0.20s  
  ✓ sensitive attributes are automatically redacted                      0.03s  
  ✓ platform audit log is immutable in eloquent                          0.03s  
  ✓ platform audit log cannot be deleted                                 0.03s  

   PASS  Tests\Feature\Platform\PlatformDashboardKpiTest
  ✓ stats overview widget renders live metrics without hardcoded mrr fallback 0.29s  
  ✓ plan distribution chart renders without fake counts                  0.04s  
  ✓ system health widget renders live probes                             0.10s  
  ✓ quick actions widget renders dynamic active tenants                  0.05s  

   PASS  Tests\Feature\Platform\PlatformNavigationTest
  ✓ platform panel configuration has six navigation groups and full width 0.04s  
  ✓ resources are mapped to correct navigation groups                    0.03s  
  ✓ platform user can access platform panel                              0.42s  
  ✓ unauthenticated user redirected to platform login                    0.05s  

   PASS  Tests\Feature\Platform\PlatformTelemetryServiceTest
  ✓ postgres probe returns ok and latency                                0.04s  
  ✓ rls compliance checks catalog and verifies all tenant tables         0.07s  
  ✓ redis probe returns ok and latency                                   0.02s  
  ✓ queue probe reports failed jobs                                      0.03s  
  ✓ financial metrics never returns fabricated mrr fallback              0.19s  

  Tests:    17 passed (66 assertions)
  Duration: 1.70s
```

---

## 2. CODE STYLE & FORMATTING VERIFICATION (`Laravel Pint`)

### Execution Command:
```bash
vendor/bin/pint --test
```

### Raw CT 104 Output:
```
  ............................................................................
  ............................................................................
  ............................................................................
  ............................................................................
  ............................................................................
  ............................................................................
  ............................................................................
  ............................................................................
  ........................

  ──────────────────────────────────────────────────────────────────── Laravel  
    PASS   ......................................................... 632 files  
```

---

## 3. STATIC ANALYSIS VERIFICATION (`PHPStan / Larastan Level 8`)

### Execution Command:
```bash
vendor/bin/phpstan analyse --memory-limit=2G
```

### Raw CT 104 Output:
```
Note: Using configuration file /var/www/dev.kcerp.com/current/phpstan.neon.
 451/451 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

 [OK] No errors
```

---

## 4. ROW-LEVEL SECURITY CATALOG CHECK (`php artisan db:check-rls`)

### Execution Command:
```bash
php artisan db:check-rls
```

### Raw CT 104 Output:
```
Checking Row-Level Security configuration on connection [pgsql_owner]...
✓ RLS Verification PASSED: All 74 tenant tables enforce forced RLS with valid policies for app_rw and reporting_ro.
```

---

## 5. FULL SYSTEM REGRESSION SUITE EXECUTION (`php artisan test`)

### Execution Command:
```bash
php artisan test
```

### Raw CT 104 Output:
```
  Tests:    459 passed (6614 assertions)
  Duration: 163.45s
```

### Suite Breakdown
- **Unit Tests:** 28 passed (MoneyTest, TaxCalculatorTest, BarcodeResolverTest, CostingServiceTest, AccountingPostingServiceTest, ModuleEntitlementServiceTest, MrpCeilingRuleTest, TaxRateResolverTest).
- **Architecture Tests:** 12 passed (ExpandAndContractMigrationRuleTest, ArchitectureTest).
- **Platform Feature Tests:** 17 passed (Navigation, KPI/Widgets, Telemetry, Audit Immutability).
- **Database & Domain Feature Tests:** 402 passed (TenantIsolation, StockLedgerPropertyTest, SubledgerGlReconciliation, SubledgerSchemaTenantIsolation, VdsWithholdingCertificate, SupplierPaymentSod, DoubleEntryBookkeeping, MushakCompliance, POSShiftAndCheckout, ProcurementChain, etc.).

**Zero regressions detected.** Every existing domain, subledger, and RLS constraint remains 100% operational.
