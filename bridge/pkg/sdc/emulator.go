package sdc

import (
	"context"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"sync"
	"time"
)

// VendorEmulator emulates an official NBR-approved Sales Data Controller hardware appliance.
type VendorEmulator struct {
	serial   string
	sequence int64
	mu       sync.Mutex
}

// NewVendorEmulator constructs a vendor emulator instance.
func NewVendorEmulator(serial string) *VendorEmulator {
	if serial == "" {
		serial = "NBR-EFD-DEFAULT"
	}
	return &VendorEmulator{
		serial:   serial,
		sequence: 1000,
	}
}

func (e *VendorEmulator) Fiscalize(ctx context.Context, req FiscalRequest) (*FiscalResponse, error) {
	e.mu.Lock()
	defer e.mu.Unlock()

	e.sequence++
	sdcInvoiceNumber := fmt.Sprintf("SDC-2027-%08d", e.sequence)

	// Cryptographic fiscal signature simulation: SHA-256(serial || invoice || total || timestamp)
	rawSigInput := fmt.Sprintf("%s|%s|%s|%s|%d", e.serial, req.InvoiceNumber, req.TotalPayable, req.Timestamp, e.sequence)
	hash := sha256.Sum256([]byte(rawSigInput))
	fiscalSig := hex.EncodeToString(hash[:16]) // 32-char hex signature

	qrCodeData := fmt.Sprintf("https://nbr.gov.bd/efd/verify?sdc=%s&inv=%s&tot=%s&sig=%s",
		sdcInvoiceNumber, req.InvoiceNumber, req.TotalPayable, fiscalSig)

	return &FiscalResponse{
		SDCInvoiceNumber: sdcInvoiceNumber,
		FiscalSignature:  fiscalSig,
		SDCDeviceSerial:  e.serial,
		FiscalTimestamp:  time.Now().UTC().Format(time.RFC3339),
		QRCodeData:       qrCodeData,
		IsProvisional:    false,
	}, nil
}

func (e *VendorEmulator) Status(ctx context.Context) (*SDCStatus, error) {
	e.mu.Lock()
	defer e.mu.Unlock()

	return &SDCStatus{
		Connected:    true,
		DeviceSerial: e.serial,
		Model:        "NBR-EFD-VENDOR-EMULATOR",
		Firmware:     "2.4.0-PROD",
	}, nil
}
