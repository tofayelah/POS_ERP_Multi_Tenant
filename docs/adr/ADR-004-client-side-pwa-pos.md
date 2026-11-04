# ADR-004: Client-Side PWA POS (Alpine.js + IndexedDB) over Livewire Server-Side Rendering

- **Status:** Accepted
- **Context:** Retail checkouts require sub-50ms barcode scanning latency and 100% operational continuity during internet outages.
- **Alternatives Considered:** (1) Livewire 4 Server-Side Rendering (rejected because every scan/click requires network round-trips, failing completely during outages); (2) Heavy React/Electron Native App (rejected due to high maintenance overhead and complex cross-platform distribution).
- **Decision:** Build POS checkout as a client-side Alpine.js SPA with Dexie.js IndexedDB persistence packaged as an offline-first PWA.
- **Consequences:** Zero scanning latency, 100% offline checkout resilience, and seamless background synchronization.
- **Review IDs Closed:** POS-01, POS-02, POS-04, POS-07, NEW-32
