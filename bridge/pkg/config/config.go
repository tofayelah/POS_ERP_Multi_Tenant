package config

import (
	"strings"
	"time"
)

// Config represents runtime settings for the print and fiscal bridge daemon.
type Config struct {
	ListenAddr         string
	AllowedOrigins     []string
	BridgeToken        string
	HandshakeTimeout   time.Duration
	SDCTimeout         time.Duration
	RateLimitMaxFails  int
	RateLimitWindow    time.Duration
	Version            string
}

// DefaultConfig returns hardened production defaults.
func DefaultConfig() *Config {
	return &Config{
		ListenAddr:       "127.0.0.1:9191",
		AllowedOrigins:   []string{"https://dev.kcerp.com"},
		BridgeToken:      "",
		HandshakeTimeout: 5 * time.Second,
		SDCTimeout:       2000 * time.Millisecond,
		RateLimitMaxFails: 5,
		RateLimitWindow:  60 * time.Second,
		Version:          "1.0.0",
	}
}

// IsOriginAllowed strictly verifies that the given origin is an HTTPS origin
// matching one of the configured allowed origins. Plain HTTP and localhost
// are unconditionally rejected.
func (c *Config) IsOriginAllowed(origin string) bool {
	if origin == "" {
		return false
	}

	// Strictly reject plain HTTP, localhost and 127.0.0.1
	lower := strings.ToLower(origin)
	if strings.HasPrefix(lower, "http://") {
		return false
	}
	if strings.Contains(lower, "localhost") || strings.Contains(lower, "127.0.0.1") {
		return false
	}

	if !strings.HasPrefix(lower, "https://") {
		return false
	}

	for _, allowed := range c.AllowedOrigins {
		if strings.EqualFold(origin, allowed) {
			return true
		}
		// Wildcard subdomain matching e.g. https://*.kcerp.com
		if strings.HasPrefix(allowed, "https://*.") {
			suffix := strings.TrimPrefix(allowed, "https://*.")
			domain := strings.TrimPrefix(origin, "https://")
			if idx := strings.Index(domain, ":"); idx != -1 {
				domain = domain[:idx]
			}
			if strings.HasSuffix(domain, "."+suffix) && !strings.Contains(strings.TrimSuffix(domain, "."+suffix), ".") {
				return true
			}
		}
	}
	return false
}
