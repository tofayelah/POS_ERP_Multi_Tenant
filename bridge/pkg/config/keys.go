package config

import (
	"crypto/ed25519"
	"encoding/hex"
)

// DefaultUpdatePublicKeyHex is the official Ed25519 public key compiled into the bridge binary.
// All downloaded updates must possess a detached signature verified by this exact key.
const DefaultUpdatePublicKeyHex = "e4d7f5a11c8340d828efb9f071485a3a29013c7bb61d85579b7b9e836b8a8b12"

// GetCompiledUpdatePublicKey parses and returns the embedded Ed25519 public key.
func GetCompiledUpdatePublicKey() ed25519.PublicKey {
	b, err := hex.DecodeString(DefaultUpdatePublicKeyHex)
	if err != nil || len(b) != ed25519.PublicKeySize {
		// Fallback empty key if decoding fails
		return make(ed25519.PublicKey, ed25519.PublicKeySize)
	}
	return ed25519.PublicKey(b)
}
