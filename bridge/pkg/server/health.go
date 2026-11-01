package server

import (
	"encoding/json"
	"net/http"
)

// handleHealth serves GET /health with origin-specific CORS and strict information segregation.
func (s *Server) handleHealth(w http.ResponseWriter, r *http.Request) {
	origin := r.Header.Get("Origin")

	// Set Origin-specific CORS header if origin is whitelisted (never *)
	if origin != "" && s.cfg.IsOriginAllowed(origin) {
		w.Header().Set("Access-Control-Allow-Origin", origin)
		w.Header().Set("Access-Control-Allow-Methods", "GET, OPTIONS")
		w.Header().Set("Access-Control-Allow-Headers", "X-Bridge-Token, Content-Type")
	}

	if r.Method == http.MethodOptions {
		w.WriteHeader(http.StatusNoContent)
		return
	}

	token := r.Header.Get("X-Bridge-Token")
	if token == "" {
		token = r.Header.Get("Bridge-Token")
	}

	response := map[string]interface{}{
		"status":  "ok",
		"version": s.cfg.Version,
	}

	// Only expose hardware diagnostics if valid 64-char bridge token is supplied
	if token != "" && s.cfg.BridgeToken != "" && token == s.cfg.BridgeToken {
		s.mu.Lock()
		diag := s.diagnostics
		s.mu.Unlock()

		if diag != nil {
			for k, v := range diag {
				response[k] = v
			}
		} else {
			// Default empty hardware state
			response["printers"] = []interface{}{}
			response["sdc"] = map[string]interface{}{
				"connected": false,
			}
		}
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	_ = json.NewEncoder(w).Encode(response)
}
