# ADR-015: In-House Proxmox Private Cloud Containerization for Development and Staging (`dev.kcerp.com`)

- **Status:** Accepted
- **Context:** Staging and concurrency testing require realistic database connection pools, memory-intensive stress tests (simulating parallel checkouts and RLS isolation), and high-throughput background queues, but public cloud staging instances are constrained by a strict $25/month budget ceiling (1GB RAM, 1 vCPU).
- **Alternatives Considered:**
  1. *Public Cloud Basic Droplets ($25/mo):* Severely RAM-constrained (1GB RAM), prone to OOM errors during concurrent Pest test runs, and incapable of hosting co-located PostgreSQL 17 + Redis + PHP 8.4-FPM + Horizon.
  2. *Shared Multi-Tenant Staging on Production Cluster:* Rejected due to unacceptable risk of staging load degrading production SLAs and potential cross-environment data contamination.
  3. *Dedicated Private Cloud Proxmox Container:* High-spec LXC container (4 vCPU, 40GB RAM, 80GB NVMe storage) on host `tofa-prox.hprbd.com` with internal IP `192.168.100.104` and reverse-proxy ingress on port 8080.
- **Decision:** Adopt the dedicated Proxmox LXC Container 104 (`dev.kcerp.com`) for all continuous integration, development, and staging workloads. Configure Nginx inside container 104 to bind to `0.0.0.0:8080`, receiving traffic forwarded from the upstream reverse proxy. Co-locate dedicated PostgreSQL 17+, Redis 7+, and Horizon workers tuned for 40GB RAM.
- **Consequences:** Provides enterprise-grade testing capacity (40GB RAM vs 1GB RAM) at zero marginal monthly cloud cost, saves $25/mo in operational expenditure, enables running comprehensive multi-process concurrency tests (QA-01) with hundreds of parallel checkouts, and maintains strict isolation from production.
- **Review IDs Closed:** OPS-01, OPS-03, OQ-04, QA-01
