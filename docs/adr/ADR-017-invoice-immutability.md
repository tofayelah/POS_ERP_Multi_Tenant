# ADR-017: Statutory Sales Invoice Immutability, Void Flow & Fiscal Enrichment Hardening

- **Status:** Accepted
- **Context:** Under the Bangladesh Value Added Tax and Supplementary Duty Act 2012 (Mushak 6.3) and National Board of Revenue (NBR) fiscalization rules, tax invoices are statutory legal instruments. Once issued, an invoice cannot be altered, renumbered, backdated, reopened, or deleted. Corrections must take place exclusively through Mushak 6.7 Credit Notes or Mushak 6.8 Debit Notes.
  During the v1.5.0 architectural review, five critical vulnerabilities (`NEW-27`, `NEW-24`) were identified in the design §9.1 tables and §9.2 triggers as originally written:
  1. **T1 (Default Status Flaw):** Design §9.1 set `DEFAULT 'issued'`, causing new invoices to immediately trigger Trigger 5 exceptions when lines were subsequently added.
  2. **T2 (Line Count & Total Validation Flaw):** Transitioning `draft -> issued` lacked line-count assertions and header-to-line sum equality checks, permitting 0-line invoices with arbitrary amounts (e.g. ৳999).
  3. **T4 (Fiscal Enrichment Exploitation):** Trigger 4 permitted updating `fiscal_signature` or `sdc_invoice_number` from NULL, but only checked `subtotal`, `tax_amount`, and `total_payable`. This allowed attackers or sync bugs to reopen an invoice (`status = 'draft'`), renumber it (`invoice_number`), or backdate it (`invoice_date`).
  4. **T5 (Line Moving Flaw):** Trigger 5 evaluated `NEW.sales_invoice_id` on line UPDATE. Changing `sales_invoice_id` on a line from an issued invoice to a draft invoice bypassed the check, moving lines off issued invoices undetected.
  5. **T6 (Cancelled Status Modification & Deletion):** Trigger 4 only blocked DELETE if `OLD.status = 'issued'`, allowing cancelled invoices to be modified or deleted, destroying audit history and violating gapless sequence rules.

- **Alternatives Considered:**
  1. **Application-Layer Validation Only:** Relying on Eloquent model hooks or service layers to validate immutability. Rejected because direct DB connections, background sync workers, or raw queries could bypass application logic. Immutability must be guaranteed by PostgreSQL triggers.
  2. **Soft Deletes (`deleted_at`):** Rejected because NBR audit regulations forbid destroying or hiding invoice sequence numbers.
  3. **Strict Write-Once without Fiscal Fields:** Forbidding any UPDATE after issuance. Rejected because offline POS terminals issue local provisional sequence invoices that require central synchronous issuing and subsequent asynchronous NBR SDC fiscal signing (`fiscal_signature` and `sdc_invoice_number`).

- **Decision:**
  Implement hardened database triggers `trg_sales_invoice_immutability` and `trg_invoice_lines_immutability` in PostgreSQL 17:
  1. **Default Status (`T1`):** `sales_invoices.status` defaults to `'draft'`. Invoices must be populated with lines before issuance.
  2. **Issue-Time Balance & Line Count Validation (`T2`):** On `draft -> issued` transition:
     - Require `COUNT(sales_invoice_lines) >= 1`.
     - Assert `sales_invoices.subtotal = SUM(sales_invoice_lines.line_total)`.
     - Assert `sales_invoices.tax_amount = SUM(sales_invoice_lines.tax_amount)`.
     - Assert `sales_invoices.supplementary_duty_amount = SUM(sales_invoice_lines.supplementary_duty_amount)`.
     - Assert `sales_invoices.total_payable = subtotal - discount_amount + supplementary_duty_amount + tax_amount`.
  3. **Strict Whitelist for Fiscal Enrichment (`T4`):** When `status = 'issued'`:
     - Allow UPDATE strictly if `(OLD.fiscal_signature IS NULL AND NEW.fiscal_signature IS NOT NULL)` OR `(OLD.sdc_invoice_number IS NULL AND NEW.sdc_invoice_number IS NOT NULL)`.
     - All other columns must strictly equal `OLD` (`NEW.col = OLD.col` and `IS NOT DISTINCT FROM` for nullable columns). Any change to `status`, `invoice_number`, `invoice_date`, `business_date`, `customer_id`, discounts, duty, or totals is rejected.
     - Overwriting existing non-null fiscal fields is rejected.
  4. **Strict Line Immutability & Reassignment Block (`T5`):**
     - Updating `sales_invoice_id` on an existing line is unconditionally rejected (`NEW.sales_invoice_id <> OLD.sales_invoice_id`).
     - Line mutations (`INSERT`, `UPDATE`, `DELETE`) acquire a `FOR SHARE` lock on the parent invoice row and verify `status = 'draft'`.
  5. **Cancelled Immutability & Gapless Void Flow (`T6`):**
     - Direct `DELETE` on `sales_invoices` is rejected for all statuses (`issued`, `cancelled`, and `draft`).
     - A draft invoice cannot be deleted; it must be voided by transitioning `draft -> cancelled`, preserving its allocated invoice number.
     - Once cancelled, an invoice is strictly immutable (no `UPDATE` and no `DELETE`).
  6. **Revocation of Truncate:** Revoke `TRUNCATE` privileges on `sales_invoices` and `sales_invoice_lines` from `app_rw` and `reporting_ro`.

- **Consequences:**
  - Guarantees 100% statutory compliance with NBR Mushak 6.3 and VAT Act 2012 audit standards.
  - Guarantees mathematically verifiable consistency between invoice headers and invoice line items.
  - Eliminates all reopening, renumbering, redating, and line-moving vulnerabilities.
  - Enforces non-repudiation and preserves gapless sequencing via the mandatory void flow.

- **Review IDs Closed:** `NEW-27`, `NEW-24`, `NEW-23`.
