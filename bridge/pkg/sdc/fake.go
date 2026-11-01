package sdc

import (
	"context"
	"fmt"
	"time"
)

// FakeSDC provides a deterministic mock device for testing timeouts and error states.
type FakeSDC struct {
	delay      time.Duration
	failOnCall bool
	counter    int
}

// NewFakeSDC creates a fake device configured with specific delay and failure mode.
func NewFakeSDC(delay time.Duration, failOnCall bool) *FakeSDC {
	return &FakeSDC{
		delay:      delay,
		failOnCall: failOnCall,
		counter:    100,
	}
}

func (f *FakeSDC) Fiscalize(ctx context.Context, req FiscalRequest) (*FiscalResponse, error) {
	if f.delay > 0 {
		select {
		case <-time.After(f.delay):
		case <-ctx.Done():
			return nil, ctx.Err()
		}
	}

	if f.failOnCall {
		return nil, ErrDeviceDisconnected
	}

	f.counter++
	sdcInvoice := fmt.Sprintf("SDC-2027-%08d", f.counter)
	signature := fmt.Sprintf("SIG-%s-%04d", req.InvoiceNumber, f.counter)
	qrData := fmt.Sprintf("https://nbr.gov.bd/efd/verify?inv=%s&sig=%s", sdcInvoice, signature)

	return &FiscalResponse{
		SDCInvoiceNumber: sdcInvoice,
		FiscalSignature:  signature,
		SDCDeviceSerial:  "FAKE-SDC-001",
		FiscalTimestamp:  time.Now().UTC().Format(time.RFC3339),
		QRCodeData:       qrData,
		IsProvisional:    false,
	}, nil
}

func (f *FakeSDC) Status(ctx context.Context) (*SDCStatus, error) {
	if f.failOnCall {
		return &SDCStatus{Connected: false}, nil
	}
	return &SDCStatus{
		Connected:    true,
		DeviceSerial: "FAKE-SDC-001",
		Model:        "Test-Fake-v1",
		Firmware:     "1.0.0",
	}, nil
}
