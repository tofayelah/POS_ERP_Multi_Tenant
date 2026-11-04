package updater

import (
	"crypto/ed25519"
)

// VerifyDetachedSignature verifies that the detached signature is authentic
// for the given data payload using the provided Ed25519 public key.
func VerifyDetachedSignature(pub ed25519.PublicKey, data, sig []byte) bool {
	if len(pub) != ed25519.PublicKeySize || len(sig) != ed25519.SignatureSize {
		return false
	}
	return ed25519.Verify(pub, data, sig)
}
