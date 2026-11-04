# Build steps

Each step: design sections to read, review IDs to close, and gate items to
settle first. Branch names start with step-NN-.

| Step | Name | Weeks | Design sections | Review IDs | Gate |
|---|---|---|---|---|---|
| 1 | Platform baseline | 1–2 | §4, §5.1–5.3, §15, §17 | MOD-02, MOD-09, QA-03, DOC-01 | PHP version agreed |
| 2 | Tenancy, roles and RLS | 3–5 | §4.2, §6.1–6.4, §9.1 (tenants, companies) | TEN-02, TEN-03, TEN-04, TEN-05, TEN-09, NEW-04 | Connection mode; pooling plan (NEW-28) |
| 3 | Identity and SoD | 6–8 | §3, §6.5, §7.3 | TEN-10, SEC-01, SEC-02, SEC-06, SEC-09 | ADR-015; discount ceilings |
| 4 | Catalog, tax codes, price lists | 6–8 | §7.5, §8.2 (1), §9.1 | NEW-17, NEW-20, TAX-02, TAX-07, DATA-08 | VAT consultant rate list; staffing (NEW-34) |
| 5 | General ledger | 6–9 | §5.4, §9.1, §9.2 | ACC-03, ACC-05, ACC-07, NEW-21 | Chart of accounts template |
| 6 | Billing and onboarding | 9–10 | §1.2, §5.3, §7.3 | TEN-11, MOD-03, MOD-04 | Pilot payment; tenant states |
| 7 | Inventory and costing | 9–15 | §8.2 (5), §9.1, §13.1, ADR-009 | NEW-26, INV-01, INV-03, INV-04, INV-06 | ADR-016 offline oversell |
| 8 | Offline POS shell | 9–12 | §11.1, §11.2, §11.4 | NEW-32, POS-04, POS-07, POS-08, POS-11 | Rounding rules; offline credit (NEW-32) |
| 9 | VAT, invoices, Mushak | 10–13 | §8.2 (8), §9.1, §9.2, §11.4, §12.2, §12.3, §16.3 | NEW-27, NEW-24, NEW-29, NEW-08, TAX-03 | NBR answers; consultant review; 9.1 scope |
| 10 | Print and fiscal bridge | 13–15 | §11.3, §12.2 | NEW-33, NEW-18, POS-03, TAX-01 | SDC spec; signing certificates |
| 11 | AR, AP and documents | 14–15 | §8.2 (4, 6, 7), §3.3 | ACC-06, NEW-16, NEW-34 | Who builds the documents (NEW-34) |
| 12 | Offline sync | 16–18 | §10.2, §11.2 | POS-09, POS-10, NEW-26, API-03 | Sync versioning; clock skew |
| 13 | Outbox, audit, import, QA | 19–20 | §5.5, §7.1, §7.2, §10.3, §12.4, §14, §16 | NEW-30, NEW-31, TEN-06, MOD-07, OBS-01 | Eid-ul-Fitr in week 19 |
| 14 | Pilot and go-live | 21–27 | §13.2, §13.3, §15, §18 | NEW-28, OPS-01, OPS-02, OPS-06 | Freeze vs go-live; Eid budget; hosting ADR |
