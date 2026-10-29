# ADR-009: Strict No-Negative-Stock Inventory Valuation Policy

- **Status:** Accepted
- **Context:** Divergent inventory valuation policies create reconciliation gaps between physical warehouses and General Ledger accounts.
- **Alternatives Considered:** (1) Permissive negative stock with retroactive recalculation (rejected due to severe distortion of Weighted Average Cost and tax reporting).
- **Decision:** Enforce a strict no-negative-stock invariant at the database engine level via `quantity_on_hand >= 0` check constraints.
- **Consequences:** Guarantees 100% mathematical integrity of inventory assets and prevents phantom stock write-offs.
- **Review IDs Closed:** INV-01, INV-02, INV-03, NEW-26
