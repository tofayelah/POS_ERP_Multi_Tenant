package config

import (
	"testing"
)

func TestDefaultConfig(t *testing.T) {
	cfg := DefaultConfig()
	if cfg.ListenAddr != "127.0.0.1:9191" {
		t.Errorf("expected ListenAddr 127.0.0.1:9191, got %s", cfg.ListenAddr)
	}

	if !cfg.IsOriginAllowed("https://dev.kcerp.com") {
		t.Errorf("expected https://dev.kcerp.com to be allowed")
	}

	if cfg.IsOriginAllowed("http://localhost") {
		t.Errorf("expected http://localhost to be rejected")
	}

	if cfg.IsOriginAllowed("http://127.0.0.1:8080") {
		t.Errorf("expected http://127.0.0.1 to be rejected")
	}

	if cfg.IsOriginAllowed("https://evil.com") {
		t.Errorf("expected https://evil.com to be rejected")
	}
}
