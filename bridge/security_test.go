package main

import (
	"bytes"
	"crypto/ed25519"
	"crypto/rand"
	"encoding/json"
	"errors"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"bridge/pkg/config"
	"bridge/pkg/server"
	"bridge/pkg/updater"

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

// ============================================================================
// PROMPT 10B ATTACK SUITE: Adversarial Testing Against Local Print Bridge
// ============================================================================

// Attack 1: A malicious web page on another origin tries to connect to the bridge
func TestAttack_PageOnAnotherOrigin(t *testing.T) {
	_, ts := newTestBridgeServer(t)
	wsURL := "ws" + strings.TrimPrefix(ts.URL, "http") + "/ws"

	evilOrigins := []string{
		"https://evil-bank-stealer.com",
		"https://malicious-phishing.org",
		"https://unauthorized-domain.net",
		"https://fake-kcerp.com.attacker.com",
	}

	dialer := websocket.Dialer{}
	for _, origin := range evilOrigins {
		header := http.Header{}
		header.Set("Origin", origin)

		_, resp, err := dialer.Dial(wsURL, header)
		if err == nil {
			t.Fatalf("CRITICAL SECURITY VULNERABILITY: WebSocket connection permitted from unauthorized origin %s", origin)
		}
		if resp == nil || resp.StatusCode != http.StatusForbidden {
			status := 0
			if resp != nil {
				status = resp.StatusCode
			}
			t.Fatalf("expected 403 Forbidden for origin %s, got %d", origin, status)
		}

		// Also verify HTTP /health endpoint refuses CORS for evil origin
		req, _ := http.NewRequest(http.MethodGet, ts.URL+"/health", nil)
		req.Header.Set("Origin", origin)
		hResp, hErr := ts.Client().Do(req)
		if hErr != nil {
			t.Fatalf("HTTP /health request failed: %v", hErr)
		}
		defer hResp.Body.Close()

		cors := hResp.Header.Get("Access-Control-Allow-Origin")
		if cors == "*" || cors == origin {
			t.Fatalf("CRITICAL SECURITY VULNERABILITY: /health granted CORS origin %s", cors)
		}
	}
}

// Attack 2: A malicious web page or local daemon served from http://localhost:8080 tries to reach the bridge
func TestAttack_PageOnLocalhostHttp(t *testing.T) {
	_, ts := newTestBridgeServer(t)
	wsURL := "ws" + strings.TrimPrefix(ts.URL, "http") + "/ws"

	localhostOrigins := []string{
		"http://localhost",
		"http://localhost:8080",
		"http://localhost:3000",
		"http://127.0.0.1",
		"http://127.0.0.1:8080",
		"http://127.0.0.1:9191",
		"http://dev.kcerp.com", // Plain HTTP even if domain matches!
	}

	dialer := websocket.Dialer{}
	for _, origin := range localhostOrigins {
		header := http.Header{}
		header.Set("Origin", origin)

		_, resp, err := dialer.Dial(wsURL, header)
		if err == nil {
			t.Fatalf("CRITICAL SECURITY VULNERABILITY: Connection accepted from insecure origin: %s", origin)
		}

		if resp == nil || resp.StatusCode != http.StatusForbidden {
			status := 0
			if resp != nil {
				status = resp.StatusCode
			}
			t.Fatalf("expected 403 Forbidden for localhost origin %s, got %d", origin, status)
		}
	}
}

// Attack 3: WebSocket connection established from allowed origin, but tries commands without token
func TestAttack_WebSocketWithoutToken(t *testing.T) {
	_, ts := newTestBridgeServer(t)
	wsURL := "ws" + strings.TrimPrefix(ts.URL, "http") + "/ws"

	header := http.Header{}
	header.Set("Origin", testValidOrigin)

	dialer := websocket.Dialer{}
	conn, _, err := dialer.Dial(wsURL, header)
	if err != nil {
		t.Fatalf("handshake dial failed: %v", err)
	}
	defer conn.Close()

	// Attempt 1: Try sending a print command directly without authenticating
	unauthPrintJob := map[string]interface{}{
		"action": "print",
		"data":   "ROGUE ESC/POS PRINT JOB SENT WITHOUT AUTH TOKEN",
	}
	if err := conn.WriteJSON(unauthPrintJob); err != nil {
		t.Fatalf("failed to send print command: %v", err)
	}

	// The bridge must refuse command, reject auth and terminate
	var response map[string]interface{}
	err = conn.ReadJSON(&response)
	if err == nil && response["status"] == "ok" {
		t.Fatalf("CRITICAL SECURITY VULNERABILITY: Unauthenticated print job succeeded: %v", response)
	}

	// Verify connection is dropped by bridge
	_ = conn.SetReadDeadline(time.Now().Add(500 * time.Millisecond))
	_, _, readErr := conn.ReadMessage()
	if readErr == nil {
		t.Fatalf("bridge kept unauthenticated connection alive")
	}
}

// Attack 4: A stolen/replayed token sent from an unauthorized origin
func TestAttack_ReplayedTokenFromDifferentOrigin(t *testing.T) {
	_, ts := newTestBridgeServer(t)
	wsURL := "ws" + strings.TrimPrefix(ts.URL, "http") + "/ws"

	stolenToken := testValidToken // Valid 64-character token observed by attacker
	attackerOrigin := "https://stolen-token-site.org"

	header := http.Header{}
	header.Set("Origin", attackerOrigin)
	header.Set("X-Bridge-Token", stolenToken)

	dialer := websocket.Dialer{}
	// WebSocket upgrade MUST fail at origin check, regardless of token present
	_, resp, err := dialer.Dial(wsURL, header)
	if err == nil {
		t.Fatalf("CRITICAL SECURITY VULNERABILITY: Replayed token accepted from foreign origin %s", attackerOrigin)
	}
	if resp == nil || resp.StatusCode != http.StatusForbidden {
		status := 0
		if resp != nil {
			status = resp.StatusCode
		}
		t.Fatalf("expected 403 Forbidden on stolen token from foreign origin, got %d", status)
	}

	// Also verify /health rejects CORS header reflection for foreign origin with token
	req, _ := http.NewRequest(http.MethodGet, ts.URL+"/health", nil)
	req.Header.Set("Origin", attackerOrigin)
	req.Header.Set("X-Bridge-Token", stolenToken)

	hResp, err := ts.Client().Do(req)
	if err != nil {
		t.Fatalf("GET /health failed: %v", err)
	}
	defer hResp.Body.Close()

	cors := hResp.Header.Get("Access-Control-Allow-Origin")
	if cors == attackerOrigin || cors == "*" {
		t.Fatalf("CRITICAL SECURITY VULNERABILITY: /health reflected CORS origin for foreign attacker %s", cors)
	}
}

// Attack 5: A flood of invalid handshakes (DoS & Brute-Force Rate Limiting)
func TestAttack_FloodOfHandshakes(t *testing.T) {
	_, ts := newTestBridgeServer(t)
	wsURL := "ws" + strings.TrimPrefix(ts.URL, "http") + "/ws"

	header := http.Header{}
	header.Set("Origin", testValidOrigin)

	dialer := websocket.Dialer{}

	// Perform 5 failed handshakes (exhausting the threshold of 5)
	for i := 1; i <= 5; i++ {
		c, _, err := dialer.Dial(wsURL, header)
		if err != nil {
			t.Fatalf("unexpected failure on initial attempt %d: %v", i, err)
		}
		badPayload := map[string]string{
			"action": "auth",
			"token":  strings.Repeat("0", 64), // Invalid token
		}
		_ = c.WriteJSON(badPayload)
		var r map[string]interface{}
		_ = c.ReadJSON(&r)
		c.Close()
	}

	// Flooding 15 more rapid connection attempts: ALL must be blocked with HTTP 429
	for i := 6; i <= 20; i++ {
		_, resp, dialErr := dialer.Dial(wsURL, header)
		if dialErr == nil {
			t.Fatalf("CRITICAL SECURITY VULNERABILITY: Rate limiter failed to block flooded attempt %d", i)
		}
		if resp == nil || resp.StatusCode != http.StatusTooManyRequests {
			status := 0
			if resp != nil {
				status = resp.StatusCode
			}
			t.Fatalf("expected status 429 Too Many Requests on attempt %d, got %d", i, status)
		}
	}
}

// Attack 6: Forged update manifest with malicious binary or corrupted signature
func TestAttack_ForgedUpdateManifest(t *testing.T) {
	// Generate genuine vendor keypair and attacker keypair
	vendorPub, _ := generateTestKeyPair(t)
	_, attackerPriv := generateTestKeyPair(t)

	trojanBinary := []byte("#!/bin/sh\n# MALICIOUS TROJAN BACKDOOR INJECTED BY ATTACKER\nrm -rf /\n")

	// Attacker signs trojan with attacker private key
	attackerSignature := ed25519.Sign(attackerPriv, trojanBinary)

	installed := false
	up := updater.NewUpdater(vendorPub, "1.0.0", func() bool { return false }, func(data []byte) error {
		installed = true
		return nil
	})

	// Case A: Binary signed by rogue attacker key
	err := up.ApplyUpdate(trojanBinary, attackerSignature)
	if err == nil {
		t.Fatalf("CRITICAL SECURITY VULNERABILITY: Updater accepted forged binary signed with attacker key")
	}
	if !errors.Is(err, updater.ErrInvalidSignature) {
		t.Errorf("expected ErrInvalidSignature, got %v", err)
	}
	if installed {
		t.Fatalf("trojan binary was executed on host")
	}

	// Case B: Arbitrary corrupted signature bytes
	randomSig := make([]byte, ed25519.SignatureSize)
	_, _ = rand.Read(randomSig)
	errCorrupted := up.ApplyUpdate(trojanBinary, randomSig)
	if errCorrupted == nil {
		t.Fatalf("CRITICAL SECURITY VULNERABILITY: Updater accepted random garbage signature")
	}
	if !errors.Is(errCorrupted, updater.ErrInvalidSignature) {
		t.Errorf("expected ErrInvalidSignature, got %v", errCorrupted)
	}
}

// Attack 7: Oversized print job (Memory bomb / DoS attack)
func TestAttack_OversizedPrintJob(t *testing.T) {
	_, ts := newTestBridgeServer(t)
	wsURL := "ws" + strings.TrimPrefix(ts.URL, "http") + "/ws"

	header := http.Header{}
	header.Set("Origin", testValidOrigin)

	dialer := websocket.Dialer{}
	conn, _, err := dialer.Dial(wsURL, header)
	if err != nil {
		t.Fatalf("connection failed: %v", err)
	}
	defer conn.Close()

	// Authenticate legitimately first
	authMsg := map[string]string{"action": "auth", "token": testValidToken}
	if err := conn.WriteJSON(authMsg); err != nil {
		t.Fatalf("auth write failed: %v", err)
	}
	var authResp map[string]interface{}
	if err := conn.ReadJSON(&authResp); err != nil || authResp["status"] != "authenticated" {
		t.Fatalf("auth failed: %v", authResp)
	}

	// Construct an oversized print job (2 MB payload, exceeding the 1 MB safety limit)
	hugeData := bytes.Repeat([]byte("A"), 2*1024*1024)
	oversizedJob := map[string]interface{}{
		"action": "print",
		"data":   string(hugeData),
	}

	writeErr := conn.WriteJSON(oversizedJob)
	if writeErr != nil {
		// Connection closed immediately by client buffer, safe
		return
	}

	// Reading response: bridge must enforce read limit and drop connection
	var printResp map[string]interface{}
	readErr := conn.ReadJSON(&printResp)
	if readErr == nil && printResp["status"] == "ok" {
		t.Fatalf("CRITICAL SECURITY VULNERABILITY: Bridge accepted 2MB memory bomb print job!")
	}

	// Verify that error is CloseMessageTooBig or connection closed
	if readErr != nil {
		t.Logf("oversized print job safely rejected with error: %v", readErr)
	}
}

// ============================================================================
// BASELINE INTEGRATION TESTS (From 10A)
// ============================================================================

func TestOrigin_WrongOriginRejected(t *testing.T) {
	TestAttack_PageOnAnotherOrigin(t)
}

func TestOrigin_HttpLocalhostRejected(t *testing.T) {
	TestAttack_PageOnLocalhostHttp(t)
}

func TestAuth_MissingOrWrongTokenRejected(t *testing.T) {
	TestAttack_FloodOfHandshakes(t)
}

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

	if healthData["status"] != "ok" || healthData["version"] != "1.0.0" {
		t.Errorf("expected status 'ok' and version '1.0.0', got %v", healthData)
	}

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

	if _, ok := authHealthData["printers"]; !ok {
		t.Errorf("expected printers diagnostics with valid token, but missing")
	}
	if _, ok := authHealthData["sdc"]; !ok {
		t.Errorf("expected SDC diagnostics with valid token, but missing")
	}
}
