# ADR-009: Strict No-Negative-Stock Inventory Valuation Policy

- **Status:** Amended by ADR-016
- **Context:** Divergent inventory valuation policies create reconciliation gaps between physical warehouses and General Ledger accounts.
- **Alternatives Considered:** (1) Permissive negative stock with retroactive recalculation (rejected due to severe distortion of Weighted Average Cost and tax reporting).
- **Decision:** Enforce a strict no-negative-stock invariant at the database engine level via `quantity_on_hand >= 0` check constraints for all online transactions, transfers, write-offs, and manual adjustments.
- **Governing Exception (ADR-016):** As decided in ADR-016, authenticated offline POS terminal sales sync is never rejected for lack of central stock. When physical goods have been sold offline, the deficit commits via a flagged `OFFLINE_OVERSELL` stock movement that temporarily allows `quantity_on_hand < 0` at the physical warehouse and atomically opens a blocking item in the Supervisor Exception Queue (`stock_oversell_exceptions`). Moving average cost is frozen during negative balances until reconciled by incoming replenishment GRN/transfer.
- **Consequences:** Guarantees 100% mathematical integrity of inventory assets for standard operations while ensuring statutory VAT and fiscal sync compliance under Bangladesh retail conditions.
- **Review IDs Closed:** INV-01, INV-02, INV-03, NEW-26

