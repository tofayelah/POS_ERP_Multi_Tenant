# Architecture Decision Records (ADRs)

This directory contains the Architecture Decision Records (ADRs) for the Retail POS & ERP platform.

## Format

Each record uses the following standardized structure:

- **Title (`# ADR-NNN: Title`):** The identifier and short, descriptive title of the architectural decision.
- **Status:** The state of the decision (e.g., `Accepted`, `Proposed`, `Deprecated`, `Superseded`).
- **Context:** The technical, business, regulatory, or operational constraints and requirements prompting the decision (such as team size, budget limits, delivery timelines, or statutory requirements).
- **Alternatives Considered:** The alternative technical options evaluated and the explicit reasons for their rejection.
- **Decision:** The chosen design pattern, library, technology baseline, or architectural rule adopted.
- **Consequences:** The architectural outcomes, trade-offs, benefits, and operational implications resulting from the decision.
- **Review IDs Closed:** Cross-references to review findings from design reviews addressed or closed by this decision.

## Index of Decisions

- [ADR-001: Selection of Modular Monolith over Distributed Microservices](ADR-001-modular-monolith.md)
- [ADR-002: Multi-Tenancy via Shared Database / Shared Schema with PostgreSQL RLS](ADR-002-multi-tenancy-rls.md)
- [ADR-003: Double-Entry Financial Immutability & Reversals Pattern](ADR-003-financial-immutability.md)
- [ADR-004: Client-Side PWA POS (Alpine.js + IndexedDB) over Livewire Server-Side Rendering](ADR-004-client-side-pwa-pos.md)
- [ADR-005: Transactional Outbox Pattern for Webhook & Integration Dispatch](ADR-005-transactional-outbox.md)
- [ADR-006: Hardware Abstraction Layer & Local Print Bridge for Raw ESC/POS](ADR-006-hardware-print-bridge.md)
- [ADR-007: Framework Baseline: Laravel 13, Filament v5, PHP 8.4+](ADR-007-framework-baseline.md)
- [ADR-008: Local Native Print Bridge Security via Origin Checking & Pairing Handshake](ADR-008-print-bridge-security.md)
- [ADR-009: Strict No-Negative-Stock Inventory Valuation Policy](ADR-009-no-negative-stock.md)
- [ADR-010: Zero-Downtime Expand/Contract Database Migrations](ADR-010-zero-downtime-migrations.md)
- [ADR-011: UUIDv7 Primary Keys for Time-Ordered High-Throughput Ingestion](ADR-011-uuidv7-primary-keys.md)
- [ADR-012: OpenTelemetry and Sentry Unified Telemetry Fabric](ADR-012-telemetry-fabric.md)
- [ADR-013: Pre-Scaling and Transaction Pooling for Eid Surge Workloads](ADR-013-eid-surge-pooling.md)
- [ADR-014: Staged Stream Gates and Ramadan-Aware Data Migration Protocol](ADR-014-staged-stream-gates.md)
- [ADR-015: In-House Proxmox Private Cloud Containerization for Development and Staging](ADR-015-proxmox-staging-containerization.md)
