package server

import (
	"context"
	"net"
	"net/http"
	"sync"

	"bridge/pkg/config"
)

// ErrServerClosed is returned when Close is called.
var ErrServerClosed = http.ErrServerClosed

// Server wraps the local HTTP and WebSocket bridge daemon.
type Server struct {
	cfg         *config.Config
	httpServer  *http.Server
	listener    net.Listener
	mu          sync.Mutex
	shiftOpen   bool
	diagnostics map[string]interface{}
	rateLimiter *RateLimiter
}

// Handler returns the underlying http.Handler for testing.
func (s *Server) Handler() http.Handler {
	return s.httpServer.Handler
}

// SetDiagnostics stores hardware diagnostic details.
func (s *Server) SetDiagnostics(diag map[string]interface{}) {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.diagnostics = diag
}

// NewServer creates a new bridge server instance.
func NewServer(cfg *config.Config) *Server {
	if cfg == nil {
		cfg = config.DefaultConfig()
	}

	s := &Server{
		cfg:         cfg,
		shiftOpen:   false,
		rateLimiter: NewRateLimiter(cfg.RateLimitMaxFails, cfg.RateLimitWindow),
	}

	mux := http.NewServeMux()
	mux.HandleFunc("/health", s.handleHealth)
	mux.HandleFunc("/ws", s.handleWebSocket)

	s.httpServer = &http.Server{
		Handler: mux,
	}

	return s
}

// Start binds strictly to 127.0.0.1:9191 and starts serving.
func (s *Server) Start() error {
	ln, err := net.Listen("tcp", s.cfg.ListenAddr)
	if err != nil {
		return err
	}
	s.listener = ln
	return s.httpServer.Serve(ln)
}

// Close gracefully shuts down the server.
func (s *Server) Close() error {
	if s.httpServer != nil {
		return s.httpServer.Shutdown(context.Background())
	}
	return nil
}

// SetShiftState updates shift status for update gating.
func (s *Server) SetShiftState(open bool) {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.shiftOpen = open
}

// IsShiftOpen returns whether a POS shift is currently open.
func (s *Server) IsShiftOpen() bool {
	s.mu.Lock()
	defer s.mu.Unlock()
	return s.shiftOpen
}
