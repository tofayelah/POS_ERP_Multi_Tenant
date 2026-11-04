# ADR-008: Local Native Print Bridge Security via Origin Checking & Pairing Handshake

- **Status:** Accepted
- **Context:** A local daemon listening on `127.0.0.1:9191` could be vulnerable to cross-site WebSocket hijacking (CSWSH) from malicious browser tabs.
- **Alternatives Considered:** (1) Unauthenticated open WebSocket (rejected as an unacceptable security vulnerability).
- **Decision:** Enforce strict `Origin` header validation against registered tenant domains and require an initial 64-character cryptographic pairing token handshake.
- **Consequences:** Complete immunity to cross-site print hijacking and unauthorized fiscal device access.
- **Review IDs Closed:** SEC-01, SEC-02, POS-03
