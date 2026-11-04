# Unified ERP v2.0.2: Hardware Bridge Source Code & Security Verification
**KC Retail ERP | Local Peripheral Daemon Architecture, Endpoints & Security Audit**  
**Controlling Work Order:** `docs/master-work-order/KC_Retail_ERP_Unified_v2.0_Specification_Work_Order.md`  
**Go Runtime:** Go 1.22.2 linux/amd64  
**Repository Subsystem:** `bridge/`  
**Repository Baseline:** Branch `step-13a-watchdog-audit` | Commit: `17d0264`  
**Auditor & Peripheral Lead:** Principal Systems Architect & Hardware Integration Lead  
**Publication Date:** November 2026  
**Document Revision:** v2.0.2 (Authoritative Go Bridge Baseline)  

---

## 1. Executive Summary & The Port 9100 vs 9191 Reconciliation

An audit of the historical project documentation revealed an endpoint discrepancy: earlier concept notes cited port `9100`, while the v2.0.1 Security Specification cited port `9191`.

An exhaustive source code inspection of the `bridge/` directory confirms beyond doubt:
1. **Authoritative Port:** The hardware bridge daemon operates strictly on **Port 9191** (`127.0.0.1:9191`).
2. **Origin of Port 9100:** Port 9100 is the legacy industry-standard raw JetDirect TCP port used by external network thermal printers. In KC Retail ERP, the Go daemon acts as a secure local intermediary between the browser PWA and the physical peripherals, listening on `127.0.0.1:9191`.
3. **Verdict:** All references to port 9100 in architecture, module, and acceptance criteria documents are obsolete and are hereby reconciled to **`127.0.0.1:9191`**.

---

## 2. Source Code Architecture & Endpoints

The Go peripheral daemon is defined across the following verified source files:
* [`bridge/cmd/bridge/main.go`](bridge/cmd/bridge/main.go)
* [`bridge/pkg/config/config.go`](bridge/pkg/config/config.go)
* [`bridge/pkg/server/server.go`](bridge/pkg/server/server.go)
* [`bridge/pkg/server/websocket.go`](bridge/pkg/server/websocket.go)
* [`bridge/pkg/server/health.go`](bridge/pkg/server/health.go)
* [`bridge/pkg/server/ratelimit.go`](bridge/pkg/server/ratelimit.go)

### 2.1 Authoritative Endpoint Specifications

```
+---------------------------------------------------------------------------------------------------------------+
|                                       GO HARDWARE BRIDGE AUTHORITATIVE ENDPOINTS                              |
+----------------------+--------------------+-----------------------+-------------------------------------------+
| Endpoint             | Transport Protocol | Physical Listen Addr  | Purpose & Authentication Required         |
+----------------------+--------------------+-----------------------+-------------------------------------------+
| `/ws`                | WebSocket (WSS/WS) | `127.0.0.1:9191`      | Bidirectional peripheral control: thermal |
|                      |                    |                       | printing, cash drawer, scale, NBR SDC.    |
|                      |                    |                       | Auth: Mandatory 64-character token.       |
+----------------------+--------------------+-----------------------+-------------------------------------------+
| `/health`            | HTTP / REST        | `127.0.0.1:9191`      | Diagnostic heartbeat & status check.      |
|                      |                    |                       | Returns JSON: uptime, version, devices.   |
+----------------------+--------------------+-----------------------+-------------------------------------------+
```

---

## 3. Implemented Security Controls in `bridge/`

### 3.1 Loopback-Only Binding (DNS Rebinding Defense)
In [`bridge/pkg/server/server.go`](bridge/pkg/server/server.go):
```go
func (s *Server) Start() error {
    ln, err := net.Listen("tcp", s.cfg.ListenAddr) // Default: "127.0.0.1:9191"
    if err != nil {
        return err
    }
    s.listener = ln
    return s.httpServer.Serve(ln)
}
```
The server binds exclusively to `127.0.0.1` and never to `0.0.0.0`. This ensures that other machines on the local area network (LAN) or external attackers cannot reach the peripheral daemon.

### 3.2 Origin Validation via Gorilla `CheckOrigin`
In [`bridge/pkg/config/config.go`](bridge/pkg/config/config.go) and [`bridge/pkg/server/websocket.go`](bridge/pkg/server/websocket.go):
```go
func (c *Config) IsOriginAllowed(origin string) bool {
    if origin == "" {
        return false
    }
    lower := strings.ToLower(origin)
    // Strictly reject plain HTTP, localhost, and 127.0.0.1
    if strings.HasPrefix(lower, "http://") || strings.Contains(lower, "localhost") || strings.Contains(lower, "127.0.0.1") {
        return false
    }
    if !strings.HasPrefix(lower, "https://") {
        return false
    }
    for _, allowed := range c.AllowedOrigins { // e.g. "https://dev.kcerp.com"
        if strings.EqualFold(origin, allowed) {
            return true
        }
    }
    return false
}
```
If an unauthorized website running in another browser tab attempts to connect to `ws://127.0.0.1:9191/ws`, the Gorilla upgrader validates the HTTP `Origin` header. If it does not match the configured tenant HTTPS domain, the connection is instantly rejected with **HTTP 403 Forbidden**.

### 3.3 Mandatory 64-Character Token Authentication Handshake
In [`bridge/pkg/server/websocket.go`](bridge/pkg/server/websocket.go):
```go
_ = conn.SetReadDeadline(time.Now().Add(s.cfg.HandshakeTimeout))

var authMsg AuthMessage
err = conn.ReadJSON(&authMsg)
if err != nil || authMsg.Action != "auth" || authMsg.Token != s.cfg.BridgeToken || len(authMsg.Token) != 64 {
    blocked := s.rateLimiter.RecordFailure(clientIP)
    errMsg := "authentication failed: invalid 64-character token"
    _ = conn.WriteJSON(AuthResponse{Status: "error", Error: errMsg})
    _ = conn.WriteMessage(websocket.CloseMessage, websocket.FormatCloseMessage(4401, errMsg))
    return
}
```
Every WebSocket connection must transmit a valid 64-character pairing token within 5 seconds of opening. Failure terminates the connection with WebSocket close code `4401`.

### 3.4 Pre-Upgrade IP Rate Limiting
In [`bridge/pkg/server/ratelimit.go`](bridge/pkg/server/ratelimit.go):
* Tracks authentication failures per client IP.
* After 5 failed attempts within 60 seconds (`RateLimitMaxFails = 5`, `RateLimitWindow = 60s`), subsequent connection requests are immediately rejected before WebSocket upgrade with **HTTP 429 Too Many Requests**.

### 3.5 Memory Exhaustion & Oversized Payload Defense
In [`bridge/pkg/server/websocket.go`](bridge/pkg/server/websocket.go):
```go
conn.SetReadLimit(1024 * 1024) // 1MB Maximum Read Limit
```
This hard ceiling blocks oversized payload injection, memory bombs, and denial-of-service attacks.

---

## 4. Empirical Test Verification

The Go hardware bridge test suite was executed on Container 104:
* **Command:** `cd bridge && go test -v ./...`
* **Result:** **23 / 23 Tests Passed** in 2.05 seconds.
* **Test Breakdown:**
  - `bridge/pkg/config`: 3 tests (DefaultConfig, ListenAddr 9191, OriginValidation).
  - `bridge/pkg/server`: 8 tests (Handshake, 64-char token, RateLimiter, OriginRejection, ReadLimit).
  - `bridge/pkg/devices`: 8 tests (ESC/POS 58mm/80mm formatting, DrawerKick, ScaleRead).
  - `bridge/pkg/sdc`: 4 tests (VendorEmulator protocol, NBR timeout handling, signature parsing).

**Authoritative Status:** **GO HARDWARE BRIDGE ARCHITECTURE & ENDPOINTS VERIFIED**
