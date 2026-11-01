package sdc

import (
	"context"
	"errors"
	"fmt"
	"time"
)

var (
	// ErrSDCTimeout is returned when SDC device fails to respond within statutory 2.0s limit.
	ErrSDCTimeout = errors.New("SDC device unresponsive after timeout")

	// ErrDeviceDisconnected is returned when physical SDC device is not connected.
	ErrDeviceDisconnected = errors.New("SDC device is disconnected or offline")
)

type FiscalRequest struct {
	InvoiceNumber string `json:"invoice_number"`
	TotalPayable  string `json:"total_payable"`
	VATAmount     string `json:"vat_amount"`
	SDAmount      string `json:"sd_amount"`
	CustomerBIN   string `json:"customer_bin,omitempty"`
	Timestamp     string `json:"timestamp"`
}

type FiscalResponse struct {
	SDCInvoiceNumber string `json:"sdc_invoice_number"`
	FiscalSignature  string `json:"fiscal_signature"`
	SDCDeviceSerial  string `json:"sdc_device_serial"`
	FiscalTimestamp  string `json:"fiscal_timestamp"`
	QRCodeData       string `json:"qr_code_data"`
	IsProvisional    bool   `json:"is_provisional"`
}

type SDCStatus struct {
	Connected    bool   `json:"connected"`
	DeviceSerial string `json:"device_serial"`
	Model        string `json:"model"`
	Firmware     string `json:"firmware"`
}

// DeviceTransport defines the low-level communication interface with SDC hardware.
type DeviceTransport interface {
	Fiscalize(ctx context.Context, req FiscalRequest) (*FiscalResponse, error)
	Status(ctx context.Context) (*SDCStatus, error)
}

// Client wraps SDC transport with strict statutory timeout enforcement.
type Client struct {
	transport DeviceTransport
	timeout   time.Duration
}

// NewClient constructs an SDC client instance.
func NewClient(transport DeviceTransport, timeout time.Duration) *Client {
	if timeout <= 0 {
		timeout = 2000 * time.Millisecond // Statutory 2.0s default
	}
	return &Client{
		transport: transport,
		timeout:   timeout,
	}
}

// Fiscalize wraps communication in statutory 2.0-second context timeout.
func (c *Client) Fiscalize(ctx context.Context, req FiscalRequest) (*FiscalResponse, error) {
	ctxTimeout, cancel := context.WithTimeout(ctx, c.timeout)
	defer cancel()

	type result struct {
		resp *FiscalResponse
		err  error
	}

	resChan := make(chan result, 1)

	go func() {
		resp, err := c.transport.Fiscalize(ctxTimeout, req)
		resChan <- result{resp: resp, err: err}
	}()

	select {
	case <-ctxTimeout.Done():
		return nil, ErrSDCTimeout
	case res := <-resChan:
		return res.resp, res.err
	}
}

// Status checks hardware health of the SDC device.
func (c *Client) Status(ctx context.Context) (*SDCStatus, error) {
	ctxTimeout, cancel := context.WithTimeout(ctx, c.timeout)
	defer cancel()
	return c.transport.Status(ctxTimeout)
}

// GenerateProvisionalNotice formats the mandatory NBR statutory disclaimer for offline/timed-out sales.
func GenerateProvisionalNotice(invoiceNumber string) string {
	return fmt.Sprintf(`*** PROVISIONAL TAX INVOICE - PENDING FISCALIZATION ***
Internal Reference: %s
Status: Pending SDC device reconnection.
Statutory Mushak 6.3 legal document of record will be issued upon fiscal sync.`, invoiceNumber)
}
