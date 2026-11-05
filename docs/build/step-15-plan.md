# Step 15 Plan: Procurement Sprint 2 (Remediation, Supplier Quotations, Landed Costs & RTV)

Branch: `step-15-procurement-sprint2`  
Baseline: `step-14-procurement-ui` (`f8b9aeb`)  
Full Specification: [`docs/implementation-plans/wave-1-sprint-1/acceptance-audit/SPRINT_2_RECOMMENDATION_AND_DEPENDENCY_PLAN.md`](file:///d:/POS_ERP_Multi_Tenant/docs/implementation-plans/wave-1-sprint-1/acceptance-audit/SPRINT_2_RECOMMENDATION_AND_DEPENDENCY_PLAN.md)

## 1. Objectives & Phased Execution

### Phase 1: Sprint 1 Audit Remediation (Pre-requisite Gate)
1. **AuditLog Status Fix**: Fix `'status' => 'pending'` to `'status' => 'pending_hash'` in `ViewSupplierBill.php` and `SupplierBillResource.php`.
2. **SupplierBillPolicy**: Author and register `app/Policies/SupplierBillPolicy.php` with `EnforcesLocationScope`.
3. **Vendor Invoice Uniqueness**: Migration to add `vendor_invoice_number` with unique constraint `(tenant_id, company_id, supplier_id, vendor_invoice_number)`.
4. **Sales Quotation Navigation**: Explicitly label `QuotationResource` as Sales Quotation under the Sales navigation group.

### Phase 2: Supplier Quotations & RFQs
1. Migrations for `supplier_quotations` and `supplier_quotation_lines` with forced RLS and composite foreign keys.
2. Models, DTOs, and `SupplierQuotationPolicy`.
3. `SupplierQuotationComparisonWidget` for multi-vendor comparative bidding.
4. Atomic PO conversion service.
5. `SupplierQuotationResource` in Filament v5 under Procurement.

### Phase 3: Landed Cost Allocation Engine
1. Landed cost voucher and allocation schemas.
2. `LandedCostAllocationService` with pro-rata methods (by value, weight, quantity) via `brick/math`.
3. Inventory AVCO revaluation and double-entry GL posting.
4. `LandedCostVoucherResource` in Filament.

### Phase 4: Supplier Return & Mushak 6.8 (RTV)
1. Return to Vendor (RTV) intake schema and warehouse workflow.
2. Stock reduction with AVCO recalculation.
3. Statutory Bangladesh Mushak 6.8 Debit Note generator.
4. AP debit note posting.

## 2. Review Items Closed
- AUD-W1S1-001 (Procurement UI Parity Acceptance Conditions 1, 2, 3, 4)
- REQ-PROC-03 (Supplier Quotations & RFQ comparison)
- REQ-PROC-05 (Landed cost allocation)
- REQ-PROC-06 (Supplier return and Mushak 6.8 debit note)

## 3. Invariants & Guardrails
- PostgreSQL 17 forced RLS on all tenant tables via `createTenantTable`.
- Composite foreign keys `(tenant_id, company_id, id)`.
- Application connection strictly `app_rw`.
- Zero float math (`brick/math` with `RoundingMode::HalfUp`).
- Immutable document chains: confirmed/posted records cannot be edited or deleted.
- Larastan level 8, Pint formatting, and 100% test pass.
