package updater

import (
	"crypto/ed25519"
	"errors"
)

var (
	// ErrInvalidSignature is returned when detached signature verification fails.
	ErrInvalidSignature = errors.New("update refused: detached cryptographic signature verification failed")

	// ErrShiftOpen is returned when an update is attempted while a POS shift is currently open.
	ErrShiftOpen = errors.New("update deferred: POS shift is open; installation permitted only when shift is closed")
)

// Manifest represents the update metadata downloaded from the central tenant server.
type Manifest struct {
	Version      string `json:"version"`
	BinaryURL    string `json:"binary_url"`
	SignatureURL string `json:"signature_url"`
}

// Updater manages cryptographic validation and shift-gated application of binary updates.
type Updater struct {
	publicKey      ed25519.PublicKey
	currentVersion string
	isShiftOpen    func() bool
	install        func([]byte) error
}

// NewUpdater constructs a new Updater instance.
func NewUpdater(pub ed25519.PublicKey, currentVersion string, isShiftOpen func() bool, install func([]byte) error) *Updater {
	return &Updater{
		publicKey:      pub,
		currentVersion: currentVersion,
		isShiftOpen:    isShiftOpen,
		install:        install,
	}
}

// ApplyUpdate validates detached cryptographic signatures against the compiled-in public key,
// verifies that the POS shift is closed, and applies the binary.
func (u *Updater) ApplyUpdate(binaryBytes, signatureBytes []byte) error {
	// Step 1: Verify detached signature (NEVER trust API checksums)
	if !VerifyDetachedSignature(u.publicKey, binaryBytes, signatureBytes) {
		return ErrInvalidSignature
	}

	// Step 2: Gated on POS shift state (Review NEW-33)
	if u.isShiftOpen != nil && u.isShiftOpen() {
		return ErrShiftOpen
	}

	// Step 3: Install verified binary
	if u.install != nil {
		return u.install(binaryBytes)
	}

	return nil
}
