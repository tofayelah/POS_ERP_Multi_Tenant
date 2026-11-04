package main

import (
	"bytes"
	"os"
	"path/filepath"
	"testing"

	"bridge/pkg/escpos"
)

// Helper to load or assert golden fixture bytes
func assertGoldenFile(t *testing.T, goldenPath string, actualBytes []byte) {
	expectedBytes, err := os.ReadFile(goldenPath)
	if err != nil {
		t.Fatalf("failed to read golden file %s: %v", goldenPath, err)
	}

	if !bytes.Equal(actualBytes, expectedBytes) {
		t.Fatalf("ESC/POS output mismatch for %s:\nExpected %d bytes\nGot %d bytes", goldenPath, len(expectedBytes), len(actualBytes))
	}
}

// 1. Cash drawer kick pulse verification
func TestESCPOS_DrawerKickPulse(t *testing.T) {
	pulse := escpos.GenerateDrawerKick()
	expected := []byte{0x1B, 0x70, 0x00, 0x19, 0xFA} // ESC p 0 25 250
	if !bytes.Equal(pulse, expected) {
		t.Fatalf("expected drawer kick bytes %v, got %v", expected, pulse)
	}
}

// 2. GS v 0 Raster bit image formatting
func TestESCPOS_RasterBitmap_GSv0(t *testing.T) {
	// 16 dots wide, 8 dots high
	// 16 dots = 2 bytes per row * 8 rows = 16 bytes
	pixelData := []byte{
		0xFF, 0xFF,
		0x80, 0x01,
		0x80, 0x01,
		0x80, 0x01,
		0x80, 0x01,
		0x80, 0x01,
		0x80, 0x01,
		0xFF, 0xFF,
	}

	cmd, err := escpos.FormatRasterImage(16, 8, pixelData)
	if err != nil {
		t.Fatalf("FormatRasterImage failed: %v", err)
	}

	// GS v 0 0 xL xH yL yH d1...dk
	// xL=2, xH=0, yL=8, yH=0
	expectedHeader := []byte{0x1D, 0x76, 0x30, 0x00, 0x02, 0x00, 0x08, 0x00}
	if !bytes.HasPrefix(cmd, expectedHeader) {
		t.Fatalf("expected GS v 0 header %v, got %v", expectedHeader, cmd[:8])
	}

	if len(cmd) != len(expectedHeader)+len(pixelData) {
		t.Fatalf("expected total length %d, got %d", len(expectedHeader)+len(pixelData), len(cmd))
	}
}

// 3. Golden Receipt Output for 80mm
func TestESCPOS_GoldenReceiptOutput_80mm(t *testing.T) {
	receipt := escpos.NewReceiptBuilder(escpos.Profile80mm)
	receipt.Init()
	receipt.SetAlign(escpos.AlignCenter)
	receipt.SetBold(true)
	receipt.AddText("SONAR BANGLA MART\n")
	receipt.SetBold(false)
	receipt.AddText("Dhanmondi Outlet #1\n")
	receipt.AddText("Mushak 6.3 Tax Invoice\n")
	receipt.AddDivider()
	receipt.SetAlign(escpos.AlignLeft)
	receipt.AddText("Mustard Oil 1L                          280.00\n")
	receipt.AddText("Arogya Neem Soap 2x                     130.00\n")
	receipt.AddDivider()

	// Add sample Bengali raster line (16x8 dots)
	sampleBengaliBitmap := []byte{
		0xFF, 0x00, 0xAA, 0x55, 0xFF, 0x00, 0xAA, 0x55,
		0xFF, 0x00, 0xAA, 0x55, 0xFF, 0x00, 0xAA, 0x55,
	}
	_ = receipt.AddRasterImage(16, 8, sampleBengaliBitmap)

	receipt.SetAlign(escpos.AlignRight)
	receipt.SetBold(true)
	receipt.AddText("TOTAL PAYABLE: 410.00 BDT\n")
	receipt.SetBold(false)
	receipt.AddDrawerKick()
	receipt.Cut()

	actual := receipt.Bytes()
	goldenPath := filepath.Join("testdata", "receipt_80mm_golden.bin")
	assertGoldenFile(t, goldenPath, actual)
}

// 4. Golden Receipt Output for 58mm
func TestESCPOS_GoldenReceiptOutput_58mm(t *testing.T) {
	receipt := escpos.NewReceiptBuilder(escpos.Profile58mm)
	receipt.Init()
	receipt.SetAlign(escpos.AlignCenter)
	receipt.SetBold(true)
	receipt.AddText("SONAR BANGLA MART\n")
	receipt.SetBold(false)
	receipt.AddText("Dhanmondi Outlet #1\n")
	receipt.AddText("Mushak 6.3 Tax Invoice\n")
	receipt.AddDivider()
	receipt.SetAlign(escpos.AlignLeft)
	receipt.AddText("Mustard Oil 1L            280.00\n")
	receipt.AddText("Neem Soap 2x              130.00\n")
	receipt.AddDivider()

	sampleBengaliBitmap := []byte{
		0xFF, 0x00, 0xAA, 0x55, 0xFF, 0x00, 0xAA, 0x55,
		0xFF, 0x00, 0xAA, 0x55, 0xFF, 0x00, 0xAA, 0x55,
	}
	_ = receipt.AddRasterImage(16, 8, sampleBengaliBitmap)

	receipt.SetAlign(escpos.AlignRight)
	receipt.SetBold(true)
	receipt.AddText("TOTAL: 410.00 BDT\n")
	receipt.SetBold(false)
	receipt.AddDrawerKick()
	receipt.Cut()

	actual := receipt.Bytes()
	goldenPath := filepath.Join("testdata", "receipt_58mm_golden.bin")
	assertGoldenFile(t, goldenPath, actual)
}
