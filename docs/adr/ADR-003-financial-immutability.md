# ADR-003: Double-Entry Financial Immutability & Reversals Pattern

- **Status:** Accepted
- **Context:** Financial compliance and NBR statutory audit readiness require that financial history can never be altered or purged.
- **Alternatives Considered:** (1) Soft deletes with `deleted_at` timestamp (rejected due to legal non-compliance and risk of unauthorized restoration); (2) Editable journal entries with audit triggers (rejected due to forensic fraud vulnerability).
- **Decision:** Enforce absolute database-level immutability for posted journal entries, issued tax invoices, and stock movements. Corrections must occur exclusively through linked reversal entries or credit notes.
- **Consequences:** Guarantees a tamper-evident, mathematically verifiable audit trail and eliminates silent ledger corruption.
- **Review IDs Closed:** ACC-01, ACC-02, ACC-03, NEW-02, NEW-23
