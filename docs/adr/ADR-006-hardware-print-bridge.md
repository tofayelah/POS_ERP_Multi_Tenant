# ADR-006: Hardware Abstraction Layer & Local Print Bridge for Raw ESC/POS

- **Status:** Accepted
- **Context:** Web browsers cannot directly open USB or serial COM ports to send raw ESC/POS commands or trigger cash drawers without user interaction prompts.
- **Alternatives Considered:** (1) Browser `window.print()` (rejected due to slow rendering, margin defects, lack of cash drawer kick, and inability to print raster Bengali); (2) WebUSB API (rejected due to lack of broad browser support and inability to access RS-232 serial ports).
- **Decision:** Deploy a lightweight compiled Go background daemon listening on `ws://127.0.0.1:9191` that receives raw byte streams and communicates directly with printers and SDC devices.
- **Consequences:** High-speed raw ESC/POS thermal printing, reliable Bengali text rendering via raster bit images, and zero-prompt cash drawer kicks.
- **Review IDs Closed:** POS-03, TAX-01, NEW-18, NEW-33
