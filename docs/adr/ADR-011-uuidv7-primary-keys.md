# ADR-011: UUIDv7 Primary Keys for Time-Ordered High-Throughput Ingestion

- **Status:** Accepted
- **Context:** High-frequency transaction tables (POS sales, stock movements, journal entries) require globally unique identifiers that avoid B-tree index fragmentation.
- **Alternatives Considered:** (1) Auto-incrementing BIGSERIAL (rejected due to enumeration vulnerability across multi-tenant APIs); (2) Random UUIDv4 (rejected due to severe B-tree fragmentation and write degradation).
- **Decision:** Utilize time-ordered UUIDv7 generated application-side via Laravel's native `HasUuids` trait.
- **Consequences:** Optimal B-tree insert performance, natural chronological sorting, and secure non-sequential public identifiers.
- **Review IDs Closed:** DATA-01, DATA-02
