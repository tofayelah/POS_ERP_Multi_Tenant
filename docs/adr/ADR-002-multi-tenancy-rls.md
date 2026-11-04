# ADR-002: Multi-Tenancy via Shared Database / Shared Schema with PostgreSQL RLS

- **Status:** Accepted
- **Context:** Needs cost-effective hosting for hundreds of SMBs while guaranteeing enterprise-grade data isolation.
- **Alternatives Considered:** (1) Database-per-Tenant (rejected for Phase 1 due to massive RAM overhead and complex multi-database schema migration loops; no dedicated database tier exists in Phase 1); (2) Schema-per-Tenant (rejected due to PostgreSQL connection cache bloat and slow migration execution across 1,000 schemas).
- **Decision:** All tenants share a common database and schema in Phase 1, isolated by mandatory `tenant_id` foreign keys and PostgreSQL Row-Level Security (RLS) policies.
- **Consequences:** Operates within a 2GB RAM database footprint, supports seamless global reporting, and eliminates multi-database connection overhead.
- **Review IDs Closed:** TEN-01, TEN-02, TEN-03, TEN-04, TEN-05
