package escpos

import (
	"bytes"
	"strings"
)

// ReceiptBuilder assembles a byte stream of ESC/POS commands and text.
type ReceiptBuilder struct {
	profile Profile
	buf     bytes.Buffer
}

// NewReceiptBuilder initializes a new builder for the specified printer profile.
func NewReceiptBuilder(profile Profile) *ReceiptBuilder {
	return &ReceiptBuilder{
		profile: profile,
	}
}

// Init sends ESC @ to initialize printer hardware to defaults.
func (b *ReceiptBuilder) Init() *ReceiptBuilder {
	b.buf.Write([]byte{0x1B, 0x40})
	return b
}

// SetAlign sets paragraph justification (AlignLeft, AlignCenter, AlignRight).
func (b *ReceiptBuilder) SetAlign(align int) *ReceiptBuilder {
	b.buf.Write([]byte{0x1B, 0x61, byte(align)})
	return b
}

// SetBold toggles emphasized text mode.
func (b *ReceiptBuilder) SetBold(bold bool) *ReceiptBuilder {
	val := byte(0)
	if bold {
		val = 1
	}
	b.buf.Write([]byte{0x1B, 0x45, val})
	return b
}

// AddText appends raw ASCII or pre-formatted text.
func (b *ReceiptBuilder) AddText(text string) *ReceiptBuilder {
	b.buf.WriteString(text)
	return b
}

// AddDivider appends a horizontal dashed separator spanning printer line width.
func (b *ReceiptBuilder) AddDivider() *ReceiptBuilder {
	b.buf.WriteString(strings.Repeat("-", b.profile.ColsFontA) + "\n")
	return b
}

// AddRasterImage formats and appends a monochrome bitmap (e.g. Bengali text or logo) using GS v 0.
func (b *ReceiptBuilder) AddRasterImage(widthDots, heightDots int, pixelData []byte) error {
	cmd, err := FormatRasterImage(widthDots, heightDots, pixelData)
	if err != nil {
		return err
	}
	b.buf.Write(cmd)
	return nil
}

// AddDrawerKick appends the cash drawer kick pulse.
func (b *ReceiptBuilder) AddDrawerKick() *ReceiptBuilder {
	b.buf.Write(GenerateDrawerKick())
	return b
}

// Cut executes paper cut (GS V 1 - partial cut).
func (b *ReceiptBuilder) Cut() *ReceiptBuilder {
	b.buf.Write([]byte{0x1D, 0x56, 0x01})
	return b
}

// Bytes returns the assembled receipt byte slice.
func (b *ReceiptBuilder) Bytes() []byte {
	return b.buf.Bytes()
}
