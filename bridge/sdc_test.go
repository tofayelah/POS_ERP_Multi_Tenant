package main

import (
	"context"
	"errors"
	"strings"
	"testing"
	"time"

	"bridge/pkg/sdc"
)

// 1. Successful SDC fiscalization
func TestSDC_SuccessfulFiscalization(t *testing.T) {
	fakeDevice := sdc.NewFakeSDC(0*time.Millisecond, false)
	client := sdc.NewClient(fakeDevice, 2000*time.Millisecond)

	req := sdc.FiscalRequest{
		InvoiceNumber: "INV-DHA01-T01-000101",
		TotalPayable:  "860.00",
		VATAmount:     "30.29",
		SDAmount:      "0.00",
		CustomerBIN:   "123456789-0101",
		Timestamp:     time.Now().UTC().Format(time.RFC3339),
	}

	resp, err := client.Fiscalize(context.Background(), req)
	if err != nil {
		t.Fatalf("expected successful fiscalization, got error: %v", err)
	}

	if resp.IsProvisional {
		t.Fatalf("expected finalized fiscal invoice, but got provisional")
	}

	if !strings.HasPrefix(resp.SDCInvoiceNumber, "SDC-") {
		t.Errorf("expected SDC invoice number prefix 'SDC-', got %s", resp.SDCInvoiceNumber)
	}

	if resp.FiscalSignature == "" {
		t.Errorf("expected non-empty fiscal signature")
	}

	if resp.QRCodeData == "" {
		t.Errorf("expected QR code verification data")
	}
}

// 2. Strict 2.0s SDC Timeout yields provisional receipt disclaimer (TAX-01, NEW-18)
func TestSDC_TimeoutYieldsProvisional(t *testing.T) {
	// Fake device delayed by 2500ms (exceeds 2000ms statutory limit)
	delayedDevice := sdc.NewFakeSDC(2500*time.Millisecond, false)
	client := sdc.NewClient(delayedDevice, 2000*time.Millisecond)

	req := sdc.FiscalRequest{
		InvoiceNumber: "INV-DHA01-T01-000102",
		TotalPayable:  "450.00",
		VATAmount:     "0.00",
		SDAmount:      "0.00",
		Timestamp:     time.Now().UTC().Format(time.RFC3339),
	}

	start := time.Now()
	resp, err := client.Fiscalize(context.Background(), req)
	duration := time.Since(start)

	if duration > 2200*time.Millisecond {
		t.Fatalf("SDC call took %v; must strictly enforce 2.0s timeout", duration)
	}

	// Must fail with timeout error and trigger provisional fallback
	if err == nil {
		t.Fatalf("expected timeout error for unresponsive SDC device, but succeeded")
	}

	if !errors.Is(err, sdc.ErrSDCTimeout) && !errors.Is(err, context.DeadlineExceeded) {
		t.Errorf("expected ErrSDCTimeout or DeadlineExceeded, got %v", err)
	}

	// Verify provisional receipt disclaimer helper
	provisionalReceipt := sdc.GenerateProvisionalNotice(req.InvoiceNumber)
	if !strings.Contains(provisionalReceipt, "PROVISIONAL TAX INVOICE - PENDING FISCALIZATION") {
		t.Fatalf("provisional receipt missing statutory disclaimer: %s", provisionalReceipt)
	}
	_ = resp
}

// 3. Vendor Emulator round-trip simulation
func TestSDC_VendorEmulatorRoundTrip(t *testing.T) {
	emulator := sdc.NewVendorEmulator("NBR-EFD-0042")
	client := sdc.NewClient(emulator, 2000*time.Millisecond)

	// Check status
	status, err := client.Status(context.Background())
	if err != nil {
		t.Fatalf("SDC Status check failed: %v", err)
	}
	if !status.Connected || status.DeviceSerial != "NBR-EFD-0042" {
		t.Errorf("unexpected status: %+v", status)
	}

	// Fiscalize transaction
	req := sdc.FiscalRequest{
		InvoiceNumber: "INV-DHA01-T01-000103",
		TotalPayable:  "280.00",
		VATAmount:     "13.33",
		SDAmount:      "0.00",
		Timestamp:     time.Now().UTC().Format(time.RFC3339),
	}

	resp, err := client.Fiscalize(context.Background(), req)
	if err != nil {
		t.Fatalf("vendor emulator fiscalize failed: %v", err)
	}

	if resp.SDCDeviceSerial != "NBR-EFD-0042" {
		t.Errorf("expected device serial NBR-EFD-0042, got %s", resp.SDCDeviceSerial)
	}
	if len(resp.FiscalSignature) < 16 {
		t.Errorf("expected valid signature, got %s", resp.FiscalSignature)
	}
}

// 4. Milestone M4: SDC Emulator round-trip over WebSocket
func TestSDC_WebSocketRoundTrip(t *testing.T) {
	_, ts := newTestBridgeServer(t)
	wsURL := "ws" + strings.TrimPrefix(ts.URL, "http") + "/ws"

	header := http.Header{}
	header.Set("Origin", testValidOrigin)

	dialer := websocket.Dialer{}
	conn, _, err := dialer.Dial(wsURL, header)
	if err != nil {
		t.Fatalf("failed to dial websocket: %v", err)
	}
	defer conn.Close()

	// 1. Authenticate
	auth := map[string]string{"action": "auth", "token": testValidToken}
	if err := conn.WriteJSON(auth); err != nil {
		t.Fatalf("auth write failed: %v", err)
	}
	var authResp map[string]interface{}
	if err := conn.ReadJSON(&authResp); err != nil || authResp["status"] != "authenticated" {
		t.Fatalf("auth failed: %v", authResp)
	}

	// 2. Query SDC status over WebSocket
	if err := conn.WriteJSON(map[string]string{"action": "sdc_status"}); err != nil {
		t.Fatalf("failed to send sdc_status: %v", err)
	}
	var statusResp map[string]interface{}
	if err := conn.ReadJSON(&statusResp); err != nil {
		t.Fatalf("failed to read sdc_status response: %v", err)
	}
	if statusResp["status"] != "ok" || statusResp["connected"] != true {
		t.Fatalf("unexpected sdc_status response: %v", statusResp)
	}

	// 3. Fiscalize basket transaction over WebSocket
	fiscalizeCmd := map[string]interface{}{
		"action": "fiscalize",
		"data": map[string]string{
			"invoice_number": "INV-DHA01-T01-000104",
			"total_payable":  "860.00",
			"vat_amount":     "30.29",
			"sd_amount":      "0.00",
		},
	}
	if err := conn.WriteJSON(fiscalizeCmd); err != nil {
		t.Fatalf("failed to send fiscalize command: %v", err)
	}

	var fiscResp map[string]interface{}
	if err := conn.ReadJSON(&fiscResp); err != nil {
		t.Fatalf("failed to read fiscalize response: %v", err)
	}

	if fiscResp["status"] != "ok" {
		t.Fatalf("fiscalization over websocket returned error: %v", fiscResp)
	}
	if fiscResp["is_provisional"] != false {
		t.Errorf("expected is_provisional=false, got %v", fiscResp["is_provisional"])
	}
	sdcInvoice, _ := fiscResp["sdc_invoice_number"].(string)
	if !strings.HasPrefix(sdcInvoice, "SDC-2027-") {
		t.Errorf("expected SDC invoice prefix SDC-2027-, got %s", sdcInvoice)
	}
	sig, _ := fiscResp["fiscal_signature"].(string)
	if len(sig) < 16 {
		t.Errorf("expected valid fiscal signature, got %s", sig)
	}
}
