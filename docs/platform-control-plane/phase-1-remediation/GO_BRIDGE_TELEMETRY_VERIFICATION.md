# KC ERP CONTROL PLANE
# GO POS BRIDGE TELEMETRY VERIFICATION REPORT

**Application:** KC ERP Control Plane (`/platform`)  
**Scope:** Milestone 3 (Go POS Bridge Telemetry Port Correction & Fault Tolerance)  
**Date:** 2026-11-05  

---

## 1. MISCONFIGURATION ROOT CAUSE & CODEBASE ALIGNMENT

In the Phase 1 Acceptance Audit, a discrepancy was discovered between the telemetry probe and the Go bridge daemon codebase:
- **Phase 1 Telemetry Probe:** Target was hardcoded as `http://127.0.0.1:8181/healthz`.
- **Authoritative Go Source:** Inspection of `bridge/pkg/config/config.go` and `bridge/pkg/server/health.go` revealed:
  - Default Listen Address: `127.0.0.1:9191` (`cfg.ListenAddr = "127.0.0.1:9191"`).
  - Health Endpoint: `GET /health` (`handleHealth`).
  - Response Format: `{"status": "ok", "version": "1.0.0"}`.

### Remediation Implemented
1. Updated `PlatformTelemetryService::getGoBridgeProbe()` to target `config('platform.pos_bridge.health_url')` defaulting to `http://127.0.0.1:9191/health`.
2. Structured JSON response verification requiring `status === 'ok'` and parsing daemon version.
3. Enhanced fault tolerance:
   - `ConnectionException`: Correctly catches socket errors when the daemon is not running and reports `status: 'offline'` without throwing uncaught exceptions.
   - HTTP 4xx/5xx: Reports `status: 'degraded'` with `HTTP {code} (daemon error)`.
   - Malformed JSON: Reports `status: 'degraded'` with `Malformed health response`.
   - Configurable socket timeout (default 1.0s) to prevent dashboard rendering delays.

---

## 2. GO SOURCE VERIFICATION & COMPILATION INTEGRITY

Executed directly on CT 104 in `/var/www/dev.kcerp.com/current/bridge`:

```bash
cd bridge && go vet ./... && go test -count=1 ./...
```

**Raw CT 104 Output:**
```
?   	bridge/cmd/bridge	[no test files]
?   	bridge/pkg/escpos	[no test files]
?   	bridge/pkg/sdc	[no test files]
?   	bridge/pkg/server	[no test files]
?   	bridge/pkg/updater	[no test files]
ok  	bridge	2.032s
ok  	bridge/pkg/config	0.001s
```

**Verification:** The Go codebase compiles cleanly, passes `go vet`, and all unit tests pass with zero cached results (`-count=1`).

---

## 3. TELEMETRY PROBE FAULT TOLERANCE TESTS

Automated tests in `tests/Feature/Platform/PlatformTelemetryServiceTest.php`:

| Scenario | Simulated Behavior | Telemetry Output | Assertion |
|---|---|---|---|
| **Daemon Online (200 OK)** | `{"status": "ok", "version": "1.0.0"}` | `status: 'ok'`, details: `127.0.0.1:9191 online v1.0.0 (Xms)` | **PASS** |
| **Connection Refused** | `ConnectionException` | `status: 'offline'`, details: `127.0.0.1:9191 offline (connection refused / daemon stopped)` | **PASS** |
| **Server Error (500)** | HTTP 500 Internal Error | `status: 'degraded'`, details: `HTTP 500 (daemon error)` | **PASS** |
| **Malformed Response** | HTML/Plain text (`<html>...</html>`) | `status: 'degraded'`, details: `Malformed health response` | **PASS** |

---

## 4. DASHBOARD RENDERING & SYSTEM HEALTH WIDGET

In `resources/views/filament/platform/widgets/system-health.blade.php`:
- `status === 'ok'`: Renders a bright emerald green indicator (`bg-emerald-500`).
- `status === 'degraded'`: Renders an amber alert indicator (`bg-amber-500`).
- `status === 'offline'`: Renders a neutral slate indicator (`bg-slate-500`) with clear message indicating the daemon is stopped.
- **Security Confirmation:** The health endpoint is restricted to localhost (`127.0.0.1:9191`) and is not exposed through the public Nginx reverse proxy.
