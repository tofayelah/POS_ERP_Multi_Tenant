package server

import (
	"encoding/json"
	"log"
	"net"
	"net/http"
	"time"

	"github.com/gorilla/websocket"
)

type AuthMessage struct {
	Action string `json:"action"`
	Token  string `json:"token"`
}

type AuthResponse struct {
	Status  string `json:"status"`
	Version string `json:"version,omitempty"`
	Error   string `json:"error,omitempty"`
}

func (s *Server) setupWebSocketUpgrader() websocket.Upgrader {
	return websocket.Upgrader{
		ReadBufferSize:  4096,
		WriteBufferSize: 4096,
		CheckOrigin: func(r *http.Request) bool {
			origin := r.Header.Get("Origin")
			return s.cfg.IsOriginAllowed(origin)
		},
	}
}

func (s *Server) handleWebSocket(w http.ResponseWriter, r *http.Request) {
	clientIP, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		clientIP = r.RemoteAddr
	}

	// 1. Rate limiter check before upgrade
	if s.rateLimiter.IsBlocked(clientIP) {
		http.Error(w, "Too Many Requests: Rate limited due to failed authentication attempts", http.StatusTooManyRequests)
		return
	}

	upgrader := s.setupWebSocketUpgrader()
	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		// Gorilla Upgrader automatically sends 403 Forbidden if CheckOrigin returns false
		return
	}
	defer conn.Close()

	// 2. Mandatory Authentication Handshake with Timeout
	_ = conn.SetReadDeadline(time.Now().Add(s.cfg.HandshakeTimeout))

	var authMsg AuthMessage
	err = conn.ReadJSON(&authMsg)
	if err != nil || authMsg.Action != "auth" || authMsg.Token != s.cfg.BridgeToken || len(authMsg.Token) != 64 {
		// Record rate limiter failure
		blocked := s.rateLimiter.RecordFailure(clientIP)
		errMsg := "authentication failed: invalid 64-character token"
		if blocked {
			errMsg = "authentication failed: rate limit exceeded (cooldown active)"
		}

		_ = conn.WriteJSON(AuthResponse{
			Status: "error",
			Error:  errMsg,
		})

		// Drop connection with custom application close code
		closeCode := 4401
		if blocked {
			closeCode = 4429
		}
		_ = conn.WriteMessage(websocket.CloseMessage, websocket.FormatCloseMessage(closeCode, errMsg))
		return
	}

	// Reset rate limiter on successful auth
	s.rateLimiter.Reset(clientIP)
	_ = conn.SetReadDeadline(time.Time{}) // Clear deadline

	_ = conn.WriteJSON(AuthResponse{
		Status:  "authenticated",
		Version: s.cfg.Version,
	})

	// 3. Main Message Loop
	for {
		messageType, p, err := conn.ReadMessage()
		if err != nil {
			break
		}

		// Handle command payload
		s.handleClientMessage(conn, messageType, p)
	}
}

func (s *Server) handleClientMessage(conn *websocket.Conn, messageType int, payload []byte) {
	// Message dispatcher for ESC/POS printing, SDC calls, and updates
	var msg map[string]interface{}
	if err := json.Unmarshal(payload, &msg); err != nil {
		_ = conn.WriteJSON(map[string]string{"status": "error", "error": "invalid json payload"})
		return
	}

	action, _ := msg["action"].(string)
	switch action {
	case "ping":
		_ = conn.WriteJSON(map[string]string{"status": "pong", "time": time.Now().UTC().Format(time.RFC3339)})
	case "shift_status":
		open, _ := msg["open"].(bool)
		s.SetShiftState(open)
		_ = conn.WriteJSON(map[string]interface{}{"status": "ok", "shift_open": open})
	default:
		log.Printf("[BRIDGE] Unknown action received: %s", action)
		_ = conn.WriteJSON(map[string]string{"status": "error", "error": "unsupported action: " + action})
	}
}
