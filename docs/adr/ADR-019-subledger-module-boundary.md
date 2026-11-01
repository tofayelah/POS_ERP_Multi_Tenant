# ADR-019: AR & AP Subledger Architecture, Module Boundaries & Control Account Reconciliation

- **Status:** Accepted
- **Context:**
  Under the retail ERP modular architecture (§8.2 items 4, 6, and 7), Accounts Receivable (AR) and Accounts Payable (AP) management was previously fragmented across multiple domains:
  1. Sales module (§8.2 #4) tracked customers and invoice payment statuses.
  2. Procurement module (§8.2 #6) tracked suppliers and purchase vouchers.
  3. Customer & Supplier Ledgers (§8.2 #7) attempted to maintain independent ledger balances.
  4. Core Accounting (§8.2 #8) posted journals to General Ledger control accounts (1030 Trade Debtors and 2010 Trade Creditors).

  Review finding `ACC-06` explicitly identified this fragmentation: receivables and payables were owned in four places. Storing balances such as `customers.current_due` directly on party tables creates data divergence, race conditions during concurrent checkouts, and breaks reconciliation against the GL.
  Furthermore, review finding `NEW-16` established that sales invoices, payments, and customers must be supported with complete composite keys, while payments must support multiple payment methods (`cash`, `bank`, `cheque`, `bkash`, `nagad`), multi-invoice allocations, partial payments, and withholding VAT (VDS under Mushak 6.6).

- **Alternatives Considered:**
  1. **Separate `Receivables` and `Payables` Top-Level Modules:**
     Creating two independent top-level modules (`app/Modules/Receivables` and `app/Modules/Payables`).
     *Rejected:* AR and AP share 95% of their underlying mechanics: open item tracking, multi-document allocation, aging analysis, bank/cash reconciliation, and double-entry GL journal posting. Splitting them into two modules duplicates allocation logic, complicates bank reconciliation (which matches both inbound customer receipts and outbound supplier disbursements), and creates redundant dependencies with Core Accounting.
  2. **Storing Running Balances on Customer and Supplier Records (`current_due`):**
     *Rejected:* Violates single source of truth and breaks auditability. Balances must be dynamically projected from open items (`subledger_open_items`) or maintained as an append-only subledger. Storing `current_due` on `customers` was previously removed in `NEW-16`.
  3. **Placing Subledger Directly in `app/Modules/Accounting/Subledger`:**
     Establishing Subledger as a core sub-domain within the Accounting module (`app/Modules/Accounting/Subledger`), exposing clean contracts (`SubledgerContract`, `CustomerCreditCheckerContract`, `PaymentServiceContract`) to external modules (Sales, Procurement, POS).
     *Accepted:* Provides unified double-entry subledger semantics, guarantees that open items and GL control accounts (1030 and 2010) are updated in the *exact same database transaction*, and gives Sales and Procurement a single interface for all debtor and creditor operations.

- **Decision:**
  1. **Single Module Ownership (`app/Modules/Accounting/Subledger`):**
     The `Accounting/Subledger` sub-module exclusively owns:
     - `subledger_open_items`: Immutable record of every open receivable and payable item with remaining balances.
     - `payments`: Central payments table supporting inbound (customer receipts) and outbound (supplier disbursement vouchers) with methods (`cash`, `bank`, `cheque`, `bkash`, `nagad`).
     - `payment_allocations`: Many-to-many allocation engine connecting payments and credit notes to open items.
     - `vds_certificates`: Statutory VAT Deducted at Source (Mushak 6.6) records for customer withholdings and supplier deductions.
     - `bank_statements` & `bank_statement_lines`: Bank statement import and automated reconciliation engine against posted payments.
     - Aging Registers: Real-time and historical aging analysis (0-30, 31-60, 61-90, 91-120, 120+ days).
     - Financial Statements: Trial Balance, Profit & Loss (Income Statement), and Balance Sheet.
  2. **Strict Module Boundary & Contract-Only Integration:**
     - Sales and Procurement *never* maintain their own customer/supplier balances or due amounts.
     - When an invoice is issued, Sales calls `SubledgerContract::createReceivable(...)`.
     - When goods or bills are received, Procurement calls `SubledgerContract::createPayable(...)`.
     - All customer dues are queried dynamically via `CustomerCreditCheckerContract::checkCreditLimit(...)`.
  3. **Strict GL Control Account Reconciliation:**
     Every subledger entry (invoice issuance, payment receipt, supplier voucher, VDS deduction, credit note allocation) is posted synchronously in the *exact same database transaction* as the double-entry GL journal via `AccountingPostingServiceContract`.
     $$\sum_{\text{open items}} \text{remaining\_amount (AR)} \equiv \text{GL Balance (Account 1030)}$$
     $$\sum_{\text{open items}} \text{remaining\_amount (AP)} \equiv \text{GL Balance (Account 2010)}$$
  4. **Segregation of Duties (SoD §3.3):**
     Supplier payment vouchers require dual-authorization: the user who created the voucher (or created the purchase order/supplier) cannot approve the disbursement. Dual-signoff is strictly enforced in the service layer before GL posting.
  5. **Immutability & Void Flow:**
     Posted payments and allocations cannot be updated or deleted. Corrections must be executed via reversal payments or credit/debit adjustments, matching ADR-003 and ADR-017.

- **Consequences:**
  - Fully resolves and closes `ACC-06` and `NEW-16`.
  - Guarantees 100% mathematical reconciliation between subledger aging and the General Ledger at all times.
  - Eliminates data corruption risks from concurrent POS sales on customer credit ("Baki").
  - Provides a single, unified workflow for bank statement reconciliation across both receipts and payments.
