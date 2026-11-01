package escpos

import (
	"errors"
)

var (
	ErrInvalidDimensions = errors.New("invalid raster dimensions: width and height must be positive")
	ErrInvalidDataLength = errors.New("pixel data length does not match width and height")
)

// FormatRasterImage converts a 1-bit monochrome pixel array into an ESC/POS GS v 0 raster bit image command.
// Format: GS v 0 m xL xH yL yH d1...dk
// m = 0 (normal density)
// xL, xH = width in bytes (widthDots / 8)
// yL, yH = height in dots
func FormatRasterImage(widthDots, heightDots int, pixelData []byte) ([]byte, error) {
	if widthDots <= 0 || heightDots <= 0 {
		return nil, ErrInvalidDimensions
	}

	widthBytes := (widthDots + 7) / 8
	expectedLen := widthBytes * heightDots
	if len(pixelData) != expectedLen {
		return nil, ErrInvalidDataLength
	}

	xL := byte(widthBytes & 0xFF)
	xH := byte((widthBytes >> 8) & 0xFF)
	yL := byte(heightDots & 0xFF)
	yH := byte((heightDots >> 8) & 0xFF)

	// Header: GS v 0 0 xL xH yL yH
	cmd := make([]byte, 8+len(pixelData))
	cmd[0] = 0x1D
	cmd[1] = 0x76
	cmd[2] = 0x30
	cmd[3] = 0x00
	cmd[4] = xL
	cmd[5] = xH
	cmd[6] = yL
	cmd[7] = yH

	copy(cmd[8:], pixelData)
	return cmd, nil
}
