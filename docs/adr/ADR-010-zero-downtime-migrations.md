# ADR-010: Zero-Downtime Expand/Contract Database Migrations

- **Status:** Accepted
- **Context:** Production ERP databases cannot tolerate downtime or table locks during continuous deployment cycles.
- **Alternatives Considered:** (1) Direct schema modification during maintenance windows (rejected due to disruption of 24/7 retail operations).
- **Decision:** Enforce the three-phase Expand/Contract migration pattern for all schema alterations.
- **Consequences:** Seamless deployments with zero user disruption and backward compatibility during multi-node rolling updates.
- **Review IDs Closed:** OPS-01, OPS-02
