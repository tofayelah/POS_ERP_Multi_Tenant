package main

import (
	"encoding/json"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"bridge/pkg/config"
	"bridge/pkg/server"

	"github.com/gorilla/websocket"
)

const (
	testValidOrigin = "https://dev.kcerp.com"
	testValidToken  = "a1b2c3d4e5f678901234567890abcdef1234567890abcdef1234567890abcdef" // 64 chars
)

func newTestBridgeServer(t *testing.T) (*server.Server, *httptest.Server) {
	cfg := &config.Config{
		ListenAddr:        "127.0.0.1:0",
		AllowedOrigins:    []string{testValidOrigin, "https://*.kcerp.com"},
		BridgeToken:       testValidToken,
		HandshakeTimeout:  2 * time.Second,
		SDCTimeout:        2 * time.Second,
		RateLimitMaxFails: 5,
		RateLimitWindow:   60 * time.Second,
		Version:           "1.0.0",
	}

	srv := server.NewServer(cfg)
	ts := httptest.NewServer(srv.Handler())
	t.Cleanup(func() {
		ts.Close()
		srv.Close()
	})
	return srv, ts
}

// 1. Wrong origin rejected
func TestOrigin_WrongOriginRejected(t *testing.T) {
	_, ts := newTestBridgeServer(t)
	wsURL := "ws" + strings.TrimPrefix(ts.URL, "http") + "/ws"

	header := http.Header{}
	header.Set("Origin", "https://malicious-attacker.com")

	dialer := websocket.Dialer{}
	_, resp, err := dialer.Dial(wsURL, header)
	if err == nil {
		t.Fatalf("expected dial to fail for evil origin, but succeeded")
	}

	if resp == nil || resp.StatusCode != http.StatusForbidden {
		status := 0
		if resp != nil {
			status = resp.StatusCode
		}
		t.Fatalf("expected status 403 Forbidden for wrong origin, got %d", status)
	}
}

// 2. http://localhost and http://127.0.0.1 strictly rejected
func TestOrigin_HttpLocalhostRejected(t *testing.T) {
	_, ts := newTestBridgeServer(t)
	wsURL := "ws" + strings.TrimPrefix(ts.URL, "http") + "/ws"

	forbiddenOrigins := []string{
		"http://localhost",
		"http://localhost:8080",
		"http://127.0.0.1",
		"http://127.0.0.1:9191",
		"http://dev.kcerp.com", // Plain http even if domain matches!
	}

	dialer := websocket.Dialer{}
	for _, origin := range forbiddenOrigins {
		header := http.Header{}
		header.Set("Origin", origin)

		_, resp, err := dialer.Dial(wsURL, header)
		if err == nil {
			t.Errorf("expected dial to fail for origin %s, but succeeded", origin)
			continue
		}

		if resp == nil || resp.StatusCode != http.StatusForbidden {
			status := 0
			if resp != nil {
				status = resp.StatusCode
			}
			t.Errorf("expected 403 Forbidden for origin %s, got %d", origin, status)
		}
	}
}

// 3. Missing or wrong token rejected; rate-limit failed handshakes
func TestAuth_MissingOrWrongTokenRejected(t *testing.T) {
	_, ts := newTestBridgeServer(t)
	wsURL := "ws" + strings.TrimPrefix(ts.URL, "http") + "/ws"

	header := http.Header{}
	header.Set("Origin", testValidOrigin)

	dialer := websocket.Dialer{}

	// Case A: Missing token
	conn, resp, err := dialer.Dial(wsURL, header)
	if err != nil {
		t.Fatalf("handshake upgrade failed: %v", err)
	}
	defer conn.Close()

	// Sending invalid auth message
	badAuth := map[string]string{"action": "auth", "token": "invalid_short_token"}
	if err := conn.WriteJSON(badAuth); err != nil {
		t.Fatalf("failed to write bad auth: %v", err)
	}

	// Read response or close
	var authResp map[string]interface{}
	err = conn.ReadJSON(&authResp)
	if err == nil && authResp["status"] == "authenticated" {
		t.Fatalf("expected auth to be refused for invalid token, but got authenticated")
	}

	// Case B: Rate limiting 5 consecutive failed handshakes
	for i := 0; i < 6; i++ {
		c, _, err := dialer.Dial(wsURL, header)
		if err != nil {
			// Rate limited at HTTP layer
			if i >= 5 {
				return // Expected rate limit triggered!
			}
			t.Fatalf("unexpected dial failure on attempt %d: %v", i, err)
		}
		_ = c.WriteJSON(map[string]string{"action": "auth", "token": "wrong_64_character_token_that_does_not_match_configured_bridge_tok"})
		var r map[string]interface{}
		_ = c.ReadJSON(&r)
		c.Close()
	}

	// 7th attempt must be rejected immediately by rate limiter
	_, dialResp, dialErr := dialer.Dial(wsURL, header)
	if dialErr == nil {
		t.Fatalf("expected rate-limited connection to be rejected, but succeeded")
	}
	if dialResp != nil && dialResp.StatusCode != http.StatusTooManyRequests && dialResp.StatusCode != http.StatusForbidden {
		t.Logf("connection refused after rate limit: %v (status %d)", dialErr, dialResp.StatusCode)
	}
}

// 4. /health leaks nothing without the token and sends no wildcard CORS
func TestHealth_NoLeakageWithoutToken(t *testing.T) {
	srv, ts := newTestBridgeServer(t)
	srv.SetDiagnostics(map[string]interface{}{
		"printers": []map[string]string{{"name": "POS-80", "status": "online"}},
		"sdc":      map[string]string{"port": "COM3", "serial": "SDC-998877"},
	})

	client := ts.Client()

	// A: Request /health without token
	req, _ := http.NewRequest(http.MethodGet, ts.URL+"/health", nil)
	req.Header.Set("Origin", testValidOrigin)

	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("GET /health failed: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}

	// Header checks: NO Access-Control-Allow-Origin: *
	corsOrigin := resp.Header.Get("Access-Control-Allow-Origin")
	if corsOrigin == "*" {
		t.Fatalf("CRITICAL SECURITY FLAW: /health returned Access-Control-Allow-Origin: *")
	}
	if corsOrigin != testValidOrigin {
		t.Errorf("expected CORS origin %s, got %s", testValidOrigin, corsOrigin)
	}

	body, _ := io.ReadAll(resp.Body)
	var healthData map[string]interface{}
	if err := json.Unmarshal(body, &healthData); err != nil {
		t.Fatalf("failed to parse /health json: %v", err)
	}

	// Must contain status and version
	if healthData["status"] != "ok" || healthData["version"] != "1.0.0" {
		t.Errorf("expected status 'ok' and version '1.0.0', got %v", healthData)
	}

	// MUST NOT leak printers, ports, or SDC details
	if _, ok := healthData["printers"]; ok {
		t.Fatalf("SECURITY LEAK: /health leaked printer details without token: %v", healthData["printers"])
	}
	if _, ok := healthData["sdc"]; ok {
		t.Fatalf("SECURITY LEAK: /health leaked SDC details without token: %v", healthData["sdc"])
	}

	// B: Request /health WITH valid Bridge-Token
	reqAuth, _ := http.NewRequest(http.MethodGet, ts.URL+"/health", nil)
	reqAuth.Header.Set("Origin", testValidOrigin)
	reqAuth.Header.Set("X-Bridge-Token", testValidToken)

	respAuth, err := client.Do(reqAuth)
	if err != nil {
		t.Fatalf("GET /health with token failed: %v", err)
	}
	defer respAuth.Body.Close()

	bodyAuth, _ := io.ReadAll(respAuth.Body)
	var authHealthData map[string]interface{}
	if err := json.Unmarshal(bodyAuth, &authHealthData); err != nil {
		t.Fatalf("failed to parse /health json with auth: %v", err)
	}

	// Now printers and SDC should be present
	if _, ok := authHealthData["printers"]; !ok {
		t.Errorf("expected printers diagnostics with valid token, but missing")
	}
	if _, ok := authHealthData["sdc"]; !ok {
		t.Errorf("expected SDC diagnostics with valid token, but missing")
	}
}
