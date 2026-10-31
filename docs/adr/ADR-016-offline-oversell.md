# ADR-016: Offline POS Oversell Reconciliation and Supervised Exception Handling

- **Status:** Accepted
- **Context:** In physical retail environments across Bangladesh, network disruptions (due to fiber cuts, mobile cellular outages, and power instability) are frequent. In accordance with ADR-004, POS terminals execute offline via an Alpine.js + Dexie.js (IndexedDB) client-side PWA architecture. While offline, cashiers continue scanning goods, collecting cash or digital payments, and issuing printed, legally binding tax receipts/invoices (NBR Mushak 6.3 or provisional tax invoices). When a customer completes checkout, the physical items leave the store. When the terminal subsequently reconnects and synchronizes its pending offline sales to the central server, the sale represents an immutable event that has already occurred in the physical world. However, design decision ADR-009 established a strict database-level invariant (`quantity_on_hand >= 0`) to safeguard inventory valuation. If the central inventory ledger has insufficient stock for an offline sale (due to delayed supplier GRN entry, unrecorded inter-branch transfers, physical shrinkage, or concurrent offline sales across multiple disconnected terminals), enforcing ADR-009's rigid constraint causes the sync transaction to fail and roll back. This sync rejection breaks the system: (1) legally issued fiscal receipts cannot be posted to the server; (2) physical cash in the drawer fails to reconcile with General Ledger sales revenue; (3) statutory VAT liabilities are omitted, constituting a severe statutory violation under the Bangladesh VAT and Supplementary Duty Act 2012; and (4) the terminal's offline sync queue is halted indefinitely. Review finding `NEW-26` mandates that an offline sale must never be rejected for lack of stock.
- **Alternatives Considered:**
  1. *Option (a): Flagged `OFFLINE_OVERSELL` Movement with Negative Balance and Supervisor Exception Queue (Recommended):*
     - Permit `stock_balances.quantity_on_hand` at the physical warehouse/branch to drop below zero *strictly and exclusively* when driven by an immutable stock movement tagged with `movement_type = 'OFFLINE_OVERSELL'`.
     - The sync worker automatically splits the sale if necessary (or posts the deficit) as an `OFFLINE_OVERSELL` movement linked to the sales invoice.
     - Atomically inserts a record into an urgent `stock_oversell_exceptions` table with status `OPEN`.
     - *Advantages:*
       - **Physical Truth:** The balance on the physical branch ledger directly reflects operational reality: physical goods were present on the shelf and sold, but procurement or transfer paperwork is lagging.
       - **Single-Location Simplicity:** Eliminates artificial "phantom" or virtual locations. All movements and balances remain anchored to the actual branch warehouse where the transaction took place.
       - **Frictionless Inbound Reconciliation:** When the late supplier delivery (Goods Received Note - GRN) or inter-branch transfer is subsequently posted, the inbound positive stock naturally and immediately neutralizes the negative balance (e.g., balance of `-2` plus receipt of `+10` directly yields `+8`), requiring no secondary clearing transfers.
       - **Immediate Operational Transparency:** Warehouse managers and branch supervisors see `-X` directly on their branch inventory dashboard, immediately pinpointing unrecorded inventory or shrinkage.
     - *Disadvantages:*
       - Requires qualifying the unconditional `CHECK (quantity_on_hand >= 0)` constraint in PostgreSQL (e.g., via trigger enforcement or conditional check).
       - Requires freezing moving average cost formulas while balances remain negative to prevent mathematical inversion.
  2. *Option (b): Shortfall Routed to an "Oversold" Virtual Stock Location:*
     - Maintain an absolute `CHECK (quantity_on_hand >= 0)` on physical locations, and automatically create a paired system virtual location (e.g., `LOC-OVERSELL-{warehouse_id}` where `is_virtual = TRUE`).
     - When offline sales exceed physical on-hand stock, the available quantity is depleted to zero at the physical location, and the shortfall is posted against the virtual oversell location, allowing the virtual location's balance to go negative (`CHECK (quantity_on_hand >= 0 OR is_virtual = TRUE)`).
     - *Advantages:*
       - Physical shelf locations never show negative balances, preserving database constraint purity for physical bins.
     - *Disadvantages (Why Rejected):*
       - **Operational Obfuscation:** Store managers checking physical inventory see `0` on hand instead of the actual deficit (`-2`), hiding the fact that physical merchandise was taken and sold without system documentation.
       - **High Overhead & Double-Handling:** Splits a single sale line item across two distinct locations and movements. When the missing supplier GRN arrives at the physical warehouse, the virtual oversell location remains negative until a secondary, synthetic clearing transfer is executed between physical and virtual locations.
       - **Pseudo-Purity:** The database engine still has to permit negative balances on virtual locations; the complexity is doubled without eliminating negative balances from the system.
- **Decision:**
  Adopt **Option (a)**. The server must never reject an offline sale for lack of stock. When an offline POS sync encounters insufficient stock, the transaction commits in full: an immutable `OFFLINE_OVERSELL` movement is recorded, the warehouse stock balance is allowed to become negative, and a blocking item is atomically opened in the Supervisor Exception Queue (`stock_oversell_exceptions`).
- **Detailed Mechanics:**
  1. **Accounting and Inventory Costing Mechanics:**
     - **Weighted-Average Cost (WAC):**
       - When an `OFFLINE_OVERSELL` movement drives the quantity below zero, the unit Cost of Goods Sold (COGS) is recognized using the **Last Known Moving Average Cost** (or standard catalog cost if the item has no prior receipt history).
       - The double-entry GL journal is posted synchronously in the same transaction: `DR 5010 (Cost of Goods Sold)` / `CR 1040 (Inventory Asset)`.
       - While `quantity_on_hand <= 0`, the stored `weighted_avg_cost` on `stock_balances` is **frozen** at the last known unit cost. The formula $WAC = \frac{\text{Total Valuation}}{\text{Quantity}}$ is suspended to prevent mathematical errors or negative unit costs.
       - *True-Up Upon Stock Arrival:* When the delayed GRN / Purchase Invoice arrives with actual unit cost $C_{\text{actual}}$:
         - The cost variance is computed: $\Delta = (C_{\text{actual}} - C_{\text{estimated}}) \times Q_{\text{oversold}}$.
         - If $\Delta \ne 0$, the system automatically posts an immutable `COST_ADJUSTMENT` movement and balancing GL entry:
           - If $C_{\text{actual}} > C_{\text{estimated}}$: `DR 5010 COGS` / `CR 1040 Inventory Asset` (under-accrued cost recognized).
           - If $C_{\text{actual}} < C_{\text{estimated}}$: `DR 1040 Inventory Asset` / `CR 5010 COGS` (over-accrued cost credited).
         - The new positive moving average unit cost is then re-established using $C_{\text{actual}}$ for the net positive units remaining.
     - **FIFO Cost Layers:**
       - If the tenant uses FIFO costing and all existing positive cost layers are depleted, the system opens a **Provisional Negative Cost Layer** (`cost_layers` with `quantity_remaining < 0` and `is_provisional = TRUE`), valued at the latest known layer cost.
       - When the replenishment batch arrives, incoming positive layers are consumed against the provisional negative layers on a strict FIFO basis (earliest oversell matched first).
       - The provisional layer is closed, and any difference between provisional valuation and actual invoice valuation triggers an automated COGS true-up journal.
  2. **Supervisor Exception Queue and Clearing Workflow:**
     - Every `OFFLINE_OVERSELL` movement atomically inserts a record into `stock_oversell_exceptions` (`tenant_id`, `branch_id`, `warehouse_id`, `product_variant_id`, `quantity_oversold`, `sales_invoice_id`, `cost_basis_used`, `status='OPEN'`).
     - An immediate alert (Filament notification, email, and SMS/Slack webhook) is dispatched to the Branch Manager and Inventory Controller.
     - The exception can be cleared in the Tenant Admin panel via three explicit, auditable operational paths:
       - **Path 1: Match Inbound Purchase Receipt (GRN):** The supervisor links the exception to an unposted or recently received Goods Received Note delivered by the supplier. Once verified, the exception status transitions to `RESOLVED_BY_GRN` and triggers the cost true-up.
       - **Path 2: Inbound Branch Transfer:** The supervisor links an unrecorded physical transfer received from another branch/central warehouse. The status transitions to `RESOLVED_BY_TRANSFER`.
       - **Path 3: Physical Count Adjustment:** If no supplier delivery or transfer explains the physical stock, the supervisor conducts an impromptu cycle count and posts a positive stock adjustment (`STOCK_COUNT_ADJUSTMENT`) with a mandatory audit reason. The status transitions to `RESOLVED_BY_ADJUSTMENT`.
     - *Escalation SLA:* Any exception remaining `OPEN` for more than 24 hours escalates to `ESCALATED` and alerts the Regional Operations Director and Financial Controller.
  3. **Online Checkout vs. Offline Sync (Tenant Setting):**
     - **Offline POS Terminals:** Unconditionally permitted to sync oversold transactions without server rejection.
     - **Online Sales Channels (Connected POS, Web E-Commerce, B2B Portal, Open API):**
       - Governed by a hierarchical tenant setting: `inventory.allow_online_oversell` (boolean, default: `FALSE`).
       - When `allow_online_oversell = FALSE` (default): Real-time checkout acquires pessimistic locks on `stock_balances`. If `quantity_on_hand - quantity_reserved < cart_quantity`, the checkout is **immediately rejected** with HTTP 422 `InsufficientStockException`, locks are released, and no transaction is posted.
       - When `allow_online_oversell = TRUE` (for made-to-order, custom fabrication, or drop-shipping tenants): Online orders are accepted into a `BACKORDER` state, but standard POS counter checkouts continue enforcing available stock checks.
- **Design Modifications Required:**
  1. **ADR-009 (Strict No-Negative-Stock Inventory Valuation Policy):**
     - Amend ADR-009's unconditional `CHECK (quantity_on_hand >= 0)` constraint. Replace with a trigger-based or conditional constraint: `quantity_on_hand >= 0` is enforced for all online transactions, transfers, write-offs, and manual adjustments, but permits `quantity_on_hand < 0` exclusively when the transaction is an authenticated offline POS sync with `movement_type = 'OFFLINE_OVERSELL'`.
     - Formally note ADR-016 as the governing exception for offline resilience and Bangladesh VAT compliance.
  2. **Open Question OQ-02 (Offline Stock Collisions):**
     - Update the resolution text in §2.3: Replace the informal "supervised negative variance" with an explicit cross-reference to ADR-016: offline sync is never rejected; deficits post as `OFFLINE_OVERSELL` movements; balances go negative on the physical warehouse; an exception is logged in `stock_oversell_exceptions`; WAC is frozen with automated true-up upon GRN; supervisor resolution is enforced under a 24-hour SLA.
  3. **Risk Analysis RSK-05 (Offline Inventory Overselling):**
     - Update RSK-05 mitigation in §19: Change mitigation from "Enforce strict single stock policy: no-negative-stock invariant. Overselling is blocked at database commit; backorders recorded separately" to: "Permit offline POS sync to commit without failure via OFFLINE_OVERSELL movements; capture deficits in the Supervisor Exception Queue with automated escalation; freeze WAC and true-up on GRN; online checkouts remain strictly blocked from overselling via row locks."
  4. **Section 11.2 (Offline Operational Policy Matrix):**
     - In Table 11.2, update the row **Stock Availability Assertion**:
       - *Old Behavior:* "Offline sales queued; reconciled sequentially via FIFO during batch sync."
       - *New Behavior:* "Offline sales sync unconditionally. If physical stock is insufficient, transaction commits, records an OFFLINE_OVERSELL movement, drives warehouse balance negative, posts COGS at last known cost, and creates an urgent item in the Supervisor Exception Queue per ADR-016."
  5. **Section 14 Watchdog (`php artisan pilot:daily-check`):**
     - In §14.1 item 4, update the financial invariant monitor:
       - The watchdog's stock balance assertion must not fail simply because a SKU balance is negative.
       - Instead, the watchdog asserts:
         1. Every negative stock balance on `stock_balances` must have an associated `OPEN` or `ESCALATED` entry in `stock_oversell_exceptions` where $\sum \text{quantity\_oversold} = |\text{negative balance}|$.
         2. Every negative balance must be traceable exclusively to `OFFLINE_OVERSELL` movements (no online sales, waste, or transfers permitted to cause negative balances).
         3. A critical alert is raised if any open oversell exception exceeds the 24-hour SLA without resolution.
  6. **Section 16.2 Concurrency Verification Test:**
     - Clarify that `test_true_parallel_pos_checkouts_prevent_overselling` in §16.2 tests **online** POS checkout contention, verifying that two concurrent online processes lock rows and reject the second checkout with `InsufficientStockException`.
     - Add a companion test to the specification: `test_offline_synced_checkouts_never_fail_and_open_supervisor_exception()`. This test simulates two disconnected offline terminals both selling the last available unit, verifies that both sync successfully, asserts that the final stock balance is `-1.0000`, verifies the creation of an `OFFLINE_OVERSELL` movement, asserts that COGS was posted at the last known cost, and confirms that an `OPEN` exception exists in `stock_oversell_exceptions`.
- **Consequences:**
  - Guarantees 100% sync reliability for offline POS terminals; zero sales data or cash transactions are lost due to stock constraints.
  - Ensures continuous compliance with NBR VAT laws by ensuring every printed Mushak 6.3 invoice is recorded on the central server.
  - Protects General Ledger integrity by recognizing COGS at last known cost and automatically truing up variances when delayed supplier receipts are entered.
  - Places an operational requirement on store managers to monitor and clear the Supervisor Exception Queue within a 24-hour SLA.
- **Review IDs Closed:** NEW-26, INV-04, OQ-02, RSK-05
