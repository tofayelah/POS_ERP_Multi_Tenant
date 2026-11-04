# ADR-001: Selection of Modular Monolith over Distributed Microservices

- **Status:** Accepted
- **Context:** Delivering an ERP within a 4–6 month timeframe with a 3–5 person engineering team and a $100–$200/month infrastructure budget.
- **Alternatives Considered:** (1) Distributed Microservices (rejected due to high network latency, distributed 2PC saga transaction complexity, and high Kubernetes hosting costs); (2) Unstructured Monolith (rejected due to risk of spaghetti code and unmanageable module coupling).
- **Decision:** Build as an encapsulated modular monolith in Laravel 13 where modules communicate via typed interfaces and domain events.
- **Consequences:** Maximizes development velocity, eliminates network serialization latency, and guarantees atomic database transactions across modules.
- **Review IDs Closed:** MOD-01, MOD-09
