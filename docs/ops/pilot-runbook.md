# Pilot Operations & Release Gate Runbook

This runbook establishes operational protocols, security boundaries, and release gate procedures for the Retail ERP pilot phase (Section 14 / Milestone M7 & M8).

---

## 1. Daily Pilot Integrity Audit & Triage Protocol

Every morning at **08:00 Asia/Dhaka**, the scheduled command runs on the read-only reporting connection:
```bash
php artisan pilot:daily-check --connection=reporting_ro
```
The output is automatically emailed to the project lead (`PROJECT_LEAD_EMAIL`).

### Triage Workflow (Prompt R4)
If `pilot:daily-check` reports any discrepancies and exits with code `1`:
1. **Never investigate or debug directly on staging or production.**
2. Copy the failing output section from the daily report.
3. Open a **new conversation** in Antigravity with the **R4** prompt template:
   ```text
   This fails:
   [Paste failing pilot:daily-check table or error output here]

   Reproduce it test-first on the dev server with anonymised data, find the root cause, fix it, and show me the clean test and daily-check run.
   ```
4. **Anonymisation Rule**: Strip all personally identifiable customer names, phone numbers, BINs, and proprietary pricing before creating test cases or fixtures. All test fixtures and reproducers must run exclusively on the dev server (`dev.kcerp.com` / `erp_test`).

---

## 2. Infrastructure & SSH Boundary Security Rules

> [!CAUTION]
> **Strict Environment Isolation**
> - The **dev server** (`Proxmox CT 104`, `dev.kcerp.com`, reached as `ssh erp-dev`) is the **ONLY** machine permitted in the agent's SSH block.
> - **Never** provide SSH access, private keys, jump host access, or connection parameters for **staging** or **production** to an AI agent.
> - Staging and production credentials, tokens, and secrets exist solely in GitHub Environment Secrets and the host-local `/var/www/*/shared/.env` files.
> - Agents interact with staging solely as unprivileged web users via the browser or public API endpoints.

---

## 3. Pre-Release Verification Gate: Cashier's Day Walkthrough

Before any release pull request (tagging `v*` or merging release candidates into `main`), the **Step 8 Cashier's Day** workflow must be executed end-to-end against the **staging environment in the browser** and recorded.

### Required Sequence to Execute & Record:
1. **Shift Initialization**:
   - Open POS terminal, input cashier credentials.
   - Enter opening float count (e.g., ৳1,000.00 cash).
2. **Standard Sales & Mushak 6.3 Issuance**:
   - Scan / add multiple items with mixed tax codes (Standard 15%, Exempt, Zero-rated).
   - Apply line-item and bill discounts within authorized limits.
   - Complete tender (Cash, Card, MFS - bKash/Nagad).
   - Confirm SDC fiscal signing and Mushak 6.3 receipt rendering.
3. **Offline Resilience & IndexedDB State**:
   - Simulate network loss (disconnect network / offline mode toggle in DevTools).
   - Ring up offline sales; verify UUID generation, client transaction timestamps, and offline receipt printing.
   - Perform a hard page reload (`Ctrl+F5`) mid-cart or mid-shift; verify complete cart and shift recovery from IndexedDB (Dexie).
4. **Network Reconnection & Automatic Synchronization**:
   - Restore network connectivity.
   - Verify queued sales background sync, server acknowledgment, and clock-skew calculation.
5. **Returns & Adjustments**:
   - Process a partial or full return against a previously issued invoice; generate Mushak 6.7 Credit Note.
6. **Shift Close & Reconciliation**:
   - Perform end-of-day denomination cash count.
   - Generate shift summary Z-Report; verify system calculated totals match drawer cash and non-cash tenders.
   - Close shift.

### Pull Request Attachment:
- The full browser session recording (video `.webm`/`.mp4` or screenshot sequence) must be uploaded and attached directly to the release pull request.
- Reviewers will not approve production releases without the attached cashier's day recording proving zero frontend regression on staging.

---

## 4. Operational Invariants & Milestones

| Milestone | Target | Exit Criteria |
| :--- | :--- | :--- |
| **M7** | Pilot Retail Operations | 3 pilot retail stores operating for 6 consecutive weeks with 0 open critical bugs, $\le$ 4h sync turnaround, and clean daily invariant checks. |
| **M8** | Production Launch | Zero-downtime release deployment from tagged release `v1.0.0` following full staging cashier's day verification and passed penetration tests. |

### Pre-Eid Freeze & Scaling Budget Guidelines
- **14-Day Change Freeze**: All non-emergency code deployments to pilot stores are frozen 14 days prior to Eid-ul-Adha (NEW-28).
- **Scaling Budget**: High-volume database provisioning for peak Eid load ($255–$270/mo on 4 GB PostgreSQL) requires explicit operational budget sign-off (OPS-01).
