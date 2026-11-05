# KC ERP CONTROL PLANE
# PHASE 1 REMEDIATION TEST EVIDENCE

**Application:** KC ERP Control Plane (`/platform`)  
**Environment:** Proxmox CT 104 (`dev.kcerp.com`)  
**PHP Version:** 8.4.27  
**PostgreSQL Version:** 17.4  
**Laravel Framework:** 13.39.0  
**Test Database:** `erp_test` on `pgsql` connection (running as `app_rw`)  
**Remediation Branch:** `control-plane-phase-1-remediation`  
**Date:** 2026-11-05  

---

## 1. PLATFORM FEATURE & REMEDIATION TESTS (`tests/Feature/Platform/`)

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
  ✓ platform audit log update is rejected by postgresql trigger          0.04s  
  ✓ platform audit log delete is rejected by postgresql trigger          0.04s  
  ✓ app rw runtime role cannot update or delete audit logs               0.03s  
  ✓ app rw runtime role cannot truncate audit logs                       0.03s  
  ✓ platform audit log truncate is rejected by statement trigger         0.05s  
  ✓ new audit record verifies using dedicated hmac secret                0.03s  
  ✓ historical audit record with key version 0 verifies using app key    0.03s  
  ✓ tampered audit record fails verification                             0.03s  
  ✓ key rotation allows historical and new records to coexist            0.03s  
  ✓ audit verification fails closed when key is missing                  0.03s  
  ✓ audit recording fails closed when secret is unconfigured             0.03s  

   PASS  Tests\Feature\Platform\PlatformDashboardKpiTest
  ✓ stats overview widget renders live metrics without hardcoded mrr fallback 0.32s  
  ✓ plan distribution chart renders without fake counts                  0.04s  
  ✓ system health widget renders live probes                             0.10s  
  ✓ quick actions widget renders dynamic active tenants                  0.05s  

   PASS  Tests\Feature\Platform\PlatformNavigationTest
  ✓ platform panel configuration has six navigation groups and full width 0.04s  
  ✓ resources are mapped to correct navigation groups                    0.03s  
  ✓ platform user can access platform panel                              0.45s  
  ✓ unauthenticated user redirected to platform login                    0.04s  

   PASS  Tests\Feature\Platform\PlatformTelemetryServiceTest
  ✓ postgres probe returns ok and latency                                0.03s  
  ✓ rls compliance checks catalog and verifies all tenant tables         0.07s  
  ✓ redis probe returns ok and latency                                   0.02s  
  ✓ queue probe reports failed jobs                                      0.03s  
  ✓ financial metrics never returns fabricated mrr fallback              0.20s  
  ✓ go bridge probe returns ok and version on successful health response 0.02s  
  ✓ go bridge probe reports offline on connection refusal                0.02s  
  ✓ go bridge probe reports degraded on http server error                0.02s  
  ✓ go bridge probe reports degraded on malformed json response          0.02s  

  Tests:    32 passed (96 assertions)
  Duration: 2.20s
```

---

## 2. GO POS BRIDGE TESTS (`bridge/`)

### Execution Command:
```bash
cd bridge && go vet ./... && go test -count=1 ./...
```

### Raw CT 104 Output:
```
?   	bridge/cmd/bridge	[no test files]
?   	bridge/pkg/escpos	[no test files]
?   	bridge/pkg/sdc	[no test files]
?   	bridge/pkg/server	[no test files]
?   	bridge/pkg/updater	[no test files]
ok  	bridge	2.032s
ok  	bridge/pkg/config	0.001s
```

---

## 3. CODE STYLE & FORMATTING VERIFICATION (`Laravel Pint`)

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
  ..........................

  ──────────────────────────────────────────────────────────────────── Laravel  
    PASS   ......................................................... 634 files  
```

---

## 4. STATIC ANALYSIS VERIFICATION (`PHPStan / Larastan Level 8`)

### Execution Command:
```bash
vendor/bin/phpstan analyse --memory-limit=2G
```

### Raw CT 104 Output:
```
Note: Using configuration file /var/www/dev.kcerp.com/current/phpstan.neon.
 452/452 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

 [OK] No errors
```

---

## 5. ROW-LEVEL SECURITY CATALOG AUDIT (`php artisan db:check-rls`)

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

## 6. FULL SYSTEM REGRESSION SUITE (`php artisan test`)

### Execution Command:
```bash
php artisan test
```

### Raw CT 104 Output:
```
  Tests:    474 passed (6644 assertions)
  Duration: 166.79s
```

### Suite Breakdown
- **Unit Tests:** 28 passed (MoneyTest, TaxCalculatorTest, BarcodeResolverTest, CostingServiceTest, AccountingPostingServiceTest, ModuleEntitlementServiceTest, MrpCeilingRuleTest, TaxRateResolverTest).
- **Architecture Tests:** 12 passed (ExpandAndContractMigrationRuleTest, ArchitectureTest).
- **Platform Feature & Remediation Tests:** 32 passed (15 new tests in Phase 1 Remediation).
- **Database Domain Tests:** 402 passed (TenantIsolation, StockLedgerPropertyTest, SubledgerGlReconciliation, SubledgerSchemaTenantIsolation, VdsWithholdingCertificate, SupplierPaymentSod, DoubleEntryBookkeeping, MushakCompliance, POSShiftAndCheckout, ProcurementChain, etc.).

**Zero regressions detected.** 100% of the entire application test suite is green.
