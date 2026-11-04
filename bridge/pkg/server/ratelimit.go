package server

import (
	"sync"
	"time"
)

// RateLimiter enforces sliding-window rate limiting on failed authentication attempts.
type RateLimiter struct {
	mu       sync.Mutex
	failures map[string][]time.Time
	maxFails int
	window   time.Duration
}

// NewRateLimiter creates a new rate limiter instance.
func NewRateLimiter(maxFails int, window time.Duration) *RateLimiter {
	return &RateLimiter{
		failures: make(map[string][]time.Time),
		maxFails: maxFails,
		window:   window,
	}
}

// RecordFailure records a failed handshake attempt for the given key (e.g. client IP).
// Returns true if the client is now blocked.
func (rl *RateLimiter) RecordFailure(key string) bool {
	rl.mu.Lock()
	defer rl.mu.Unlock()

	now := time.Now()
	cutoff := now.Add(-rl.window)

	// Prune old failures
	var valid []time.Time
	for _, t := range rl.failures[key] {
		if t.After(cutoff) {
			valid = append(valid, t)
		}
	}

	valid = append(valid, now)
	rl.failures[key] = valid

	return len(valid) >= rl.maxFails
}

// IsBlocked checks if the key has exceeded the failure limit in the active window.
func (rl *RateLimiter) IsBlocked(key string) bool {
	rl.mu.Lock()
	defer rl.mu.Unlock()

	cutoff := time.Now().Add(-rl.window)
	count := 0
	for _, t := range rl.failures[key] {
		if t.After(cutoff) {
			count++
		}
	}
	return count >= rl.maxFails
}

// Reset clears failure records for a key after successful authentication.
func (rl *RateLimiter) Reset(key string) {
	rl.mu.Lock()
	defer rl.mu.Unlock()
	delete(rl.failures, key)
}
