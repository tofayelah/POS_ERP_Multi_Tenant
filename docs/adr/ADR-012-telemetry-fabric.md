# ADR-012: OpenTelemetry and Sentry Unified Telemetry Fabric

- **Status:** Accepted
- **Context:** Operational visibility must span frontend offline PWAs, local hardware bridges, background workers, and PostgreSQL database queries.
- **Alternatives Considered:** (1) Disconnected log files (rejected due to lack of correlation and slow incident triage).
- **Decision:** Implement a unified telemetry fabric using Sentry for exception tracking and OpenTelemetry for distributed APM tracing.
- **Consequences:** Sub-minute mean time to detection (MTTD) for production anomalies and end-to-end visibility across all execution tiers.
- **Review IDs Closed:** OBS-01
