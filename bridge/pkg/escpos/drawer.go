package escpos

// GenerateDrawerKick produces standard ESC/POS pulse command to open the cash drawer.
// Command: ESC p m t1 t2
// m = 0 (connector pin 2)
// t1 = 25 (pulse ON time: 25 * 2ms = 50ms)
// t2 = 250 (pulse OFF time: 250 * 2ms = 500ms)
func GenerateDrawerKick() []byte {
	return []byte{0x1B, 0x70, 0x00, 0x19, 0xFA}
}
