# ADR-018: Bangladesh VAT & Tax Rounding Specification, Cash Tender Adjustment, and Golden Vectors

- **Status:** Accepted (Pending VAT Consultant Sign-Off on Extended Vectors)
- **Context:** Under the Bangladesh Value Added Tax and Supplementary Duty Act 2012 (Act No. 47 of 2012) and National Board of Revenue (NBR) statutory invoice (Mushak 6.3) specifications:
  - Rounding and tax valuation rules must be mathematically identical and synchronized between the backend Laravel tax engine (`app/Modules/Tax/TaxCalculator.php`) and the offline Alpine.js / Dexie POS PWA (`resources/pos/src/tax.js`).
  - Review `NEW-08` required an explicit Architecture Decision Record codifying the rounding rules and an authoritative test vector suite (`tests/golden/vat-vectors.json`).
  - Review `NEW-29` identified that the "2.63% net-effective" mustard oil example in design §11.4 was a non-statutory truncated rate whose arithmetic violated the standard tax-inclusive formula; it must not be used.

- **Alternatives Considered:**
  1. **Floating-Point Arithmetic:** Rejected unconditionally (ACC-05). Floating-point IEEE-754 numbers introduce binary approximation drift (e.g. 0.1 + 0.2 = 0.30000000000000004). All computations use `brick/math` `BigDecimal` in PHP and `BigInt` (paisa) in JavaScript.
  2. **Per-Unit Intermediate Rounding:** Calculating tax per single unit, rounding to 2 decimal places, and multiplying by quantity. Rejected because for items with odd fractions (e.g. 3 units @ ৳33.33 at 15% VAT), per-unit rounding yields ৳13.05 VAT, while per-line rounding yields ৳13.04 VAT. Statutory Mushak 6.3 Column 7 assesses tax on the line total, not intermediate fractions.
  3. **Universal ৳1 Rounding:** Rounding invoice totals to ৳1 for all payment methods. Rejected because electronic payments (bKash, Nagad, Visa, Mastercard, EFT) support exact 2-decimal fractional transactions. Cash rounding applies exclusively to physical currency notes in the register drawer.

- **Decision:**
  Adopt the following standardized rounding and valuation rules across PHP and JavaScript:

  1. **Tax-Inclusive Retail MRP Formulation:**
     $$\text{Value with SD} = \text{roundHalfUp}\left(\frac{\text{Gross MRP}}{1 + \text{VAT Rate}}, 2\right)$$
     $$\text{VAT Amount} = \text{Gross MRP} - \text{Value with SD}$$
     $$\text{Net Price} = \text{roundHalfUp}\left(\frac{\text{Value with SD}}{1 + \text{SD Rate}}, 2\right)$$
     $$\text{Supplementary Duty Amount} = \text{Value with SD} - \text{Net Price}$$
     - **Identity Invariant:** $\text{Net Price} + \text{Supplementary Duty} + \text{VAT Amount} \equiv \text{Gross MRP}$. No paisa is lost or created.

  2. **Tax-Exclusive Wholesale Pricing Formulation:**
     $$\text{SD Amount} = \text{roundHalfUp}(\text{Net Assessable Value} \times \text{SD Rate}, 2)$$
     $$\text{Value for VAT} = \text{Net Assessable Value} + \text{SD Amount}$$
     $$\text{VAT Amount} = \text{roundHalfUp}(\text{Value for VAT} \times \text{VAT Rate}, 2)$$
     $$\text{Total Payable} = \text{Net Assessable Value} + \text{SD Amount} + \text{VAT Amount}$$

  3. **Per-Line Rounding for Multi-Quantity Items:**
     - The line gross amount is computed first: $\text{Line Gross} = \text{Unit Price} \times \text{Quantity}$.
     - VAT and SD are extracted from the line gross total.
     - Documented in golden vector `per-line-vs-per-unit-rounding` (3x ৳33.33 @ 15% gives Net ৳86.95, VAT ৳13.04; not ৳86.94 / ৳13.05).

  4. **Proportional Allocation of Invoice-Level Discounts:**
     - When an invoice-level discount is applied to a multi-line basket, the discount is distributed proportionally based on each line's gross contribution:
       $$\text{Allocated Discount}_i = \text{roundHalfUp}\left(\text{Invoice Discount} \times \frac{\text{Line Gross}_i}{\text{Basket Gross}}, 2\right)$$
     - The final line absorbs the rounding difference so $\sum \text{Allocated Discount} \equiv \text{Invoice Discount}$.
     - Line Net, SD, and VAT are recalculated on each line's discounted gross.

  5. **Cash Drawer Rounding to Integer ৳1:**
     - When tender type is physical Cash, the grand total payable rounds half-up to the nearest integer ৳1:
       - Fractional paisa $\ge 50$ rounds up to +৳1.00.
       - Fractional paisa $< 50$ rounds down to ৳0.00.
     - The rounding difference is booked to `Rounding Adjustment` (`6090 Cash Rounding Gain/Loss`).
     - Card and MFS transactions pay exact 2-decimal amounts without adjustment.

  6. **Shared Golden Test Vectors (`tests/golden/vat-vectors.json`):**
     - Base 4 vectors from design §11.4:
       - `incl-exempt-rice`: Gross ৳450.00 @ 0% -> Net ৳450.00, VAT ৳0.00
       - `incl-5pct-oil`: Gross ৳280.00 @ 5% -> Net ৳266.67, VAT ৳13.33
       - `incl-15pct-soap-x2`: Gross ৳130.00 @ 15% -> Net ৳113.04, VAT ৳16.96
       - `basket-total`: Gross ৳860.00 -> Net ৳829.71, VAT ৳30.29
     - Extended vectors marked `"needs consultant check"`:
       - `incl-sd-and-vat`: Gross ৳230.00 @ 10% SD, 15% VAT -> Net ৳181.82, SD ৳18.18, VAT ৳30.00
       - `excl-wholesale-15pct`: Net ৳1,000.00 @ 15% VAT -> SD ৳0.00, VAT ৳150.00, Total ৳1,150.00
       - `incl-line-discount`: Gross ৳200.00 with ৳20.00 discount @ 15% VAT -> Net ৳156.52, VAT ৳23.48, Total ৳180.00
       - `incl-invoice-discount`: Gross ৳410.00 with ৳41.00 discount -> Discounted Gross ৳369.00, Net ৳341.74, VAT ৳27.26
       - `per-line-vs-per-unit-rounding`: 3x ৳33.33 @ 15% -> Net ৳86.95, VAT ৳13.04
       - `cash-rounding-to-1-taka`: ৳829.49 -> ৳829.00 (-0.49); ৳829.50 -> ৳830.00 (+0.50); ৳829.71 -> ৳830.00 (+0.29)

- **Consequences:**
  - Guarantees 100% mathematical parity between PHP server-side invoice issuing and client-side offline POS.
  - Eliminates reconciliation mismatches and penny discrepancies between register shifts and general ledger journals.
  - Clearly identifies all extended tax scenarios for external VAT consultant audit sign-off.

- **Review IDs Closed:** `NEW-08`, `NEW-29`, `TAX-03`.
