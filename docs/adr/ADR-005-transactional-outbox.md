# ADR-005: Transactional Outbox Pattern for Webhook & Integration Dispatch

- **Status:** Accepted
- **Context:** Third-party webhooks (e.g., eCommerce sync, SMS gateways) must never fail silently or break internal database transactions.
- **Alternatives Considered:** (1) Synchronous HTTP dispatch within database transactions (rejected due to risk of hanging transactions and third-party latency); (2) Standard Laravel queue dispatch without outbox (rejected due to risk of message loss if the queue worker crashes before queue commit).
- **Decision:** Implement the Transactional Outbox pattern writing integration events into `transactional_outbox` within the same database transaction, polled by an asynchronous worker with `FOR UPDATE SKIP LOCKED`. Webhooks enforce timestamped HMAC-SHA256 signatures (`X-Signature`, `X-Timestamp`), a 300-second replay window, and post-DNS resolution SSRF checks against private, loopback, and link-local IP ranges.
- **Consequences:** Guarantees at-least-once webhook delivery, prevents internal transaction stalls from third-party latency, protects internal infrastructure from SSRF attacks, and decouples internal events from external network failures.
- **Review IDs Closed:** MOD-06, MOD-07, NEW-30, NEW-31
