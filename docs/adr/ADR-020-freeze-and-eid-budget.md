# ADR-020: Production Release Freeze Protocol and Peak Eid Scaling Budget Exception

- **Status:** Accepted
- **Date:** 2026-11-02
- **Author:** Systems Architecture & Operations Taskforce
- **Review IDs Closed:** NEW-28, OPS-01

---

## 1. Context

During Step 14 (Pilot and Go-Live preparation) and design reviews (v1.4.0, v1.5.0), two interrelated operational constraints required authoritative resolution prior to production rollout:

1. **Go-Live Date vs. Eid-ul-Adha Freeze Collision (`NEW-28`):**
   - In Bangladesh retail, Eid-ul-Fitr and Eid-ul-Adha generate 5x–10x normal transaction volume.
   - Design §19 and ADR-013 mandate an absolute 14-day pre-Eid code freeze to safeguard retailer revenue.
   - Eid-ul-Adha is projected for ~17–19 May 2027. A 14-day freeze commences on ~3 May 2027, which directly collides with the original Week 27 go-live target (9 May 2027). Launching during or on the eve of a nationwide retail freeze exposes retailers to unmitigated operational risk.

2. **Eid Database Infrastructure Budget Exception (`OPS-01`):**
   - The agreed operating budget is capped at **$200/month** across development, staging, and production hosting.
   - Load testing (M6 Eid-peak gate: 15 checkouts/sec, 50 checkouts/min per store) demonstrates that a 2 GB PostgreSQL instance (47 connection limit) risks connection starvation under surge concurrency.
   - Scaling managed PostgreSQL vertically to 4 GB (97 connections) alongside dedicated Redis, PgBouncer pooling, and multi-cloud object storage (Cloudflare R2 + AWS S3) elevates hosting expenditure to **$255–$270/month** during the holiday period.

---

## 2. Alternatives Considered

1. **Alternative 1: Launch Production Directly Inside the Freeze (Exemption):**
   - *Evaluation:* Grant an exemption to go live on 9 May 2027, pushing the freeze start to post-launch.
   - *Reason for Rejection:* Extreme risk. Retailers face peak seasonal turnover and cannot tolerate teething issues, schema lock contention, or emergency rollbacks during Eid shopping week.

2. **Alternative 2: Postpone Go-Live Until After Eid-ul-Adha (June 2027):**
   - *Evaluation:* Extend the pilot by 4 additional weeks and go live after Eid-ul-Adha concludes.
   - *Reason for Rejection:* Misses the prime commercial revenue window for the business and delays contract conversion for pilot retailers.

3. **Alternative 3: Enforce Rigid $200 Cap Year-Round (Refuse Scaling):**
   - *Evaluation:* Stay on 2 GB PostgreSQL with aggressive client drop-off and query queueing.
   - *Reason for Rejection:* Causes connection timeouts and queue degradation during load spikes, violating SLA commitments.

---

## 3. Decision

1. **Go-Live vs Freeze Alignment:**
   - **Target Go-Live Date:** Moved forward to **26 April 2027 (End of Week 26)**.
   - **Buffer Period:** 26 April to 2 May provides 7 calendar days of live production trading under normal traffic to ensure system stability.
   - **Freeze Institute Date:** The mandatory 14-day Pre-Eid Freeze commences at **00:00 Asia/Dhaka on 3 May 2027** and continues through **20 May 2027**.
   - **Freeze Rules:**
     - Zero feature releases, zero UI modifications, and zero database schema migrations (`deploy.yml` disabled for production pushes).
     - Only Sev-1/P0 hotfixes (e.g., statutory VAT non-compliance or data corruption) may be deployed, requiring dual sign-off from the Project Lead and Technical Lead.

2. **Approved Seasonal Infrastructure Scaling Budget:**
   - A **temporary seasonal budget ceiling of $270/month** is formally approved for a 60-day window covering Ramadan and the twin Eid festivals (1 March 2027 – 31 May 2027).
   - **Allocation Breakdown:**
     - Managed PostgreSQL 17 (4 GB RAM, 2 vCPU, 97 max connections, continuous WAL archiving): ~$160/mo.
     - Web App Nodes & Horizon Queues (2x 2 GB Container/VPS): ~$60/mo.
     - Managed Redis 7 (AOF + replication): ~$25/mo.
     - Off-provider Object Storage & Egress (S3 + Cloudflare R2 Mushak archive): ~$20/mo.
   - **Downscaling Policy:** On 1 June 2027, following Eid reconciliation, PostgreSQL scales back to the standard 2 GB tier ($100/mo), returning monthly expenditure to under the baseline $200/mo threshold.

---

## 4. Consequences

### Positive
- Retailers operate with zero software churn during their most critical trading window of the year.
- Database connection headroom guarantees zero `too many clients already` errors during surge checkouts.
- Financial transparency is maintained with pre-approved operational budget boundaries.

### Operational Responsibilities
- Operations team sets calendar alert for 15 February 2027 to execute database vertical scaling.
- Operations team sets calendar alert for 1 June 2027 to execute database downscaling.
- Deployment pipeline enforces the code freeze automatically via GitHub branch protection rules during the designated window.
