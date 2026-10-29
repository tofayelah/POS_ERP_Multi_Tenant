# ADR-013: Pre-Scaling and Transaction Pooling for Eid Surge Workloads

- **Status:** Accepted
- **Context:** Bangladesh retail experiences a 10x transaction surge during Ramadan and the weeks preceding Eid-ul-Fitr and Eid-ul-Adha.
- **Alternatives Considered:** (1) Uncapped container autoscaling (rejected because it quickly exhausts the 47-connection limit of managed PostgreSQL); (2) Static year-round over-provisioning (rejected as cost-inefficient under the $200/mo budget).
- **Decision:** Implement a scheduled vertical scale-up of the managed database (from 2GB to 4GB / 97 connections) 14 days before Ramadan, transition to PgBouncer transaction-mode pooling (port 25060) if scaling web containers beyond 2 instances, and enforce a strict 14-day pre-Eid code freeze.
- **Consequences:** Guarantees platform availability under extreme retail peak loads while maintaining strict connection governance and budget discipline.
- **Review IDs Closed:** NEW-28, OPS-06
