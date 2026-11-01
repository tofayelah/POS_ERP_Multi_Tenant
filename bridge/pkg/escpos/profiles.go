package escpos

// Alignment constants
const (
	AlignLeft   = 0
	AlignCenter = 1
	AlignRight  = 2
)

// Profile holds geometric and font capacity constraints for a receipt printer width.
type Profile struct {
	Name      string
	WidthDots int
	ColsFontA int
}

var (
	// Profile80mm represents standard 80mm thermal receipt printers (72mm printable, 576 dots).
	Profile80mm = Profile{
		Name:      "80mm",
		WidthDots: 576,
		ColsFontA: 48,
	}

	// Profile58mm represents compact 58mm thermal receipt printers (48mm printable, 384 dots).
	Profile58mm = Profile{
		Name:      "58mm",
		WidthDots: 384,
		ColsFontA: 32,
	}
)
