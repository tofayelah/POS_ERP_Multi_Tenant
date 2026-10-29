# ADR-005: Transactional Outbox Pattern for Webhook & Integration Dispatch

- **Status:** Accepted
- **Context:** Third-party webhooks (e.g., eCommerce sync, SMS gateways) must never fail silently or break internal database transactions.
- **Alternatives Considered:** (1) Synchronous HTTP dispatch within database transactions (rejected due to risk of hanging transactions and third-party latency); (2) Standard Laravel queue dispatch without outbox (rejected due to risk of message loss if the queue worker crashes before queue commit).
- **Decision:** Implement the Transactional Outbox pattern writing integration events into `outbox_events` within the same database transaction, polled by an asynchronous worker.
- **Consequences:** Guarantees at-least-once webhook delivery and decouples internal transactions from external network failures.
- **Review IDs Closed:** MOD-06, MOD-07, NEW-30, NEW-31
