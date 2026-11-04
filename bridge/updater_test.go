package main

import (
	"crypto/ed25519"
	"crypto/rand"
	"errors"
	"testing"

	"bridge/pkg/updater"
)

func generateTestKeyPair(t *testing.T) (ed25519.PublicKey, ed25519.PrivateKey) {
	pub, priv, err := ed25519.GenerateKey(rand.Reader)
	if err != nil {
		t.Fatalf("failed to generate ed25519 key: %v", err)
	}
	return pub, priv
}

// 1. Update with bad signature is refused
func TestUpdater_BadSignatureRefused(t *testing.T) {
	pub, priv := generateTestKeyPair(t)

	binaryPayload := []byte("#!/bin/sh\necho 'new bridge binary v1.1.0'\n")
	validSig := ed25519.Sign(priv, binaryPayload)

	installed := false
	up := updater.NewUpdater(pub, "1.0.0", func() bool { return false }, func(data []byte) error {
		installed = true
		return nil
	})

	// Case A: Forged / corrupted signature
	corruptedSig := make([]byte, len(validSig))
	copy(corruptedSig, validSig)
	corruptedSig[0] ^= 0xFF // Flip bits

	err := up.ApplyUpdate(binaryPayload, corruptedSig)
	if err == nil {
		t.Fatalf("CRITICAL SECURITY FLAW: updater accepted corrupted signature")
	}
	if !errors.Is(err, updater.ErrInvalidSignature) {
		t.Errorf("expected ErrInvalidSignature, got %v", err)
	}
	if installed {
		t.Fatalf("updater installed binary despite bad signature")
	}

	// Case B: Tampered binary with valid original signature
	tamperedBinary := []byte("#!/bin/sh\necho 'MALICIOUS TROJAN INJECTED'\n")
	errTampered := up.ApplyUpdate(tamperedBinary, validSig)
	if errTampered == nil {
		t.Fatalf("CRITICAL SECURITY FLAW: updater accepted tampered binary with original signature")
	}
	if !errors.Is(errTampered, updater.ErrInvalidSignature) {
		t.Errorf("expected ErrInvalidSignature for tampered binary, got %v", errTampered)
	}
}

// 2. Update deferred while shift is open
func TestUpdater_ShiftOpenDefersInstall(t *testing.T) {
	pub, priv := generateTestKeyPair(t)

	binaryPayload := []byte("#!/bin/sh\necho 'new bridge binary v1.1.0'\n")
	validSig := ed25519.Sign(priv, binaryPayload)

	shiftOpen := true
	installed := false

	up := updater.NewUpdater(pub, "1.0.0", func() bool { return shiftOpen }, func(data []byte) error {
		installed = true
		return nil
	})

	err := up.ApplyUpdate(binaryPayload, validSig)
	if err == nil {
		t.Fatalf("expected update to be deferred while shift is open, but it proceeded")
	}
	if !errors.Is(err, updater.ErrShiftOpen) {
		t.Errorf("expected ErrShiftOpen, got %v", err)
	}
	if installed {
		t.Fatalf("updater installed binary while shift was active")
	}
}

// 3. Update installs cleanly once shift is closed
func TestUpdater_ShiftClosedInstalls(t *testing.T) {
	pub, priv := generateTestKeyPair(t)

	binaryPayload := []byte("#!/bin/sh\necho 'new bridge binary v1.1.0'\n")
	validSig := ed25519.Sign(priv, binaryPayload)

	shiftOpen := false
	installed := false

	up := updater.NewUpdater(pub, "1.0.0", func() bool { return shiftOpen }, func(data []byte) error {
		installed = true
		return nil
	})

	err := up.ApplyUpdate(binaryPayload, validSig)
	if err != nil {
		t.Fatalf("expected update to install cleanly when shift is closed, got %v", err)
	}
	if !installed {
		t.Fatalf("install callback was not executed")
	}
}
