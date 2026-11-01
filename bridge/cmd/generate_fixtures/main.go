package main

import (
	"os"
	"path/filepath"

	"bridge/pkg/escpos"
)

func main() {
	sampleBengaliBitmap := []byte{
		0xFF, 0x00, 0xAA, 0x55, 0xFF, 0x00, 0xAA, 0x55,
		0xFF, 0x00, 0xAA, 0x55, 0xFF, 0x00, 0xAA, 0x55,
	}

	// 80mm
	r80 := escpos.NewReceiptBuilder(escpos.Profile80mm)
	r80.Init()
	r80.SetAlign(escpos.AlignCenter)
	r80.SetBold(true)
	r80.AddText("SONAR BANGLA MART\n")
	r80.SetBold(false)
	r80.AddText("Dhanmondi Outlet #1\n")
	r80.AddText("Mushak 6.3 Tax Invoice\n")
	r80.AddDivider()
	r80.SetAlign(escpos.AlignLeft)
	r80.AddText("Mustard Oil 1L                          280.00\n")
	r80.AddText("Arogya Neem Soap 2x                     130.00\n")
	r80.AddDivider()
	_ = r80.AddRasterImage(16, 8, sampleBengaliBitmap)
	r80.SetAlign(escpos.AlignRight)
	r80.SetBold(true)
	r80.AddText("TOTAL PAYABLE: 410.00 BDT\n")
	r80.SetBold(false)
	r80.AddDrawerKick()
	r80.Cut()
	_ = os.WriteFile(filepath.Join("testdata", "receipt_80mm_golden.bin"), r80.Bytes(), 0644)

	// 58mm
	r58 := escpos.NewReceiptBuilder(escpos.Profile58mm)
	r58.Init()
	r58.SetAlign(escpos.AlignCenter)
	r58.SetBold(true)
	r58.AddText("SONAR BANGLA MART\n")
	r58.SetBold(false)
	r58.AddText("Dhanmondi Outlet #1\n")
	r58.AddText("Mushak 6.3 Tax Invoice\n")
	r58.AddDivider()
	r58.SetAlign(escpos.AlignLeft)
	r58.AddText("Mustard Oil 1L            280.00\n")
	r58.AddText("Neem Soap 2x              130.00\n")
	r58.AddDivider()
	_ = r58.AddRasterImage(16, 8, sampleBengaliBitmap)
	r58.SetAlign(escpos.AlignRight)
	r58.SetBold(true)
	r58.AddText("TOTAL: 410.00 BDT\n")
	r58.SetBold(false)
	r58.AddDrawerKick()
	r58.Cut()
	_ = os.WriteFile(filepath.Join("testdata", "receipt_58mm_golden.bin"), r58.Bytes(), 0644)
}
