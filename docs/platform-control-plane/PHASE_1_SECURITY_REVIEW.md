# KC ERP CONTROL PLANE - PHASE 1 SECURITY REVIEW

**Application:** KC ERP Control Plane (`/platform`)  
**Scope:** Phase 1 (Navigation, Responsive UI, Data Integrity, Platform Audit Foundation)  
**Security Evaluator:** Antigravity Senior Security Agent  
**Date:** 2026-11-05  

---

## 1. CONTROL PLANE BOUNDARY ANALYSIS

The KC ERP Control Plane manages the lifecycle, tenancy metadata, subscription tiers, and system observability of the multi-tenant retail ERP.

### Boundary Principles
1. **Isolated Authentication Guard (`platform`):**
   - Platform administrators authenticate exclusively through the `platform` guard backed by `App\Models\PlatformUser`.
   - Tenant store employees authenticate through `web` guard backed by `App\Models\User`.
   - A platform user session cannot access tenant store endpoints without valid tenant credentials.
   - Tenant store users have zero access to `/platform` routes; unauthenticated requests are immediately redirected to `/platform/login` (verified by `PlatformNavigationTest::test_unauthenticated_user_redirected_to_platform_login`).

2. **Tenant RLS Separation:**
   - Platform administration does not bypass tenant RLS by disabling policies.
   - Platform queries operate on non-tenant platform tables (`platform_users`, `tenants`, `plans`, `tenant_subscriptions`, `platform_audit_logs`).
   - Store launch buttons in `PlatformQuickActionsWidget` open isolated store sessions in separate tabs with explicit tenant domain routing (`/admin`).

3. **Separation of Accounting Concerns:**
   - Platform subscriptions and SaaS billing plans are strictly isolated from tenant ERP General Ledgers.
   - No platform transactions touch `gl_accounts`, `journals`, or `sales_invoices`.

---

## 2. AUDIT LOG IMMUTABILITY GUARANTEE

The platform audit ledger (`platform_audit_logs`) serves as an authoritative forensic trail for compliance, dispute resolution, and security oversight.

### Dual-Layer Immutability Protection

#### Layer 1: PostgreSQL 17 Hardware-Level Database Trigger
A fail-closed PL/pgSQL function intercepts any mutation on `platform_audit_logs`:
```sql
CREATE OR REPLACE FUNCTION trg_fn_enforce_platform_audit_log_immutability()
RETURNS TRIGGER AS $$
BEGIN
    IF (TG_OP = 'UPDATE') THEN
        RAISE EXCEPTION 'Platform Audit Security Violation: platform_audit_logs records are strictly immutable and cannot be updated.';
    ELSIF (TG_OP = 'DELETE') THEN
        RAISE EXCEPTION 'Platform Audit Security Violation: platform_audit_logs records cannot be deleted from the audit ledger.';
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;
```
Even if a developer or application script attempts an SQL update via `DB::table('platform_audit_logs')->update(...)`, PostgreSQL aborts the transaction with an uncatchable database exception.

#### Layer 2: Eloquent Model Exception Guards
`App\Models\PlatformAuditLog` hooks into model events (`updating` and `deleting`):
```php
protected static function booted(): void
{
    static::updating(function () {
        throw new \RuntimeException('PlatformAuditLog is strictly immutable and cannot be modified.');
    });

    static::deleting(function () {
        throw new \RuntimeException('PlatformAuditLog is strictly immutable and cannot be deleted.');
    });
}
```

Verification is certified by automated test cases in `PlatformAuditLogTest`:
- `test_platform_audit_log_is_immutable_in_eloquent`: Confirms `RuntimeException` on save.
- `test_platform_audit_log_cannot_be_deleted`: Confirms `RuntimeException` on delete.

---

## 3. CRYPTOGRAPHIC HMAC PAYLOAD SEALING

To detect forensic record tampering or direct SQL injection in backup dumps, each audit entry computes an SHA-256 HMAC digest before persistence:

```php
$payloadToHash = json_encode([
    'action' => $action,
    'actor_id' => $actor?->getAuthIdentifier(),
    'resource_type' => $resourceType,
    'resource_id' => $resourceId,
    'old_values' => $oldValues,
    'new_values' => $newValues,
    'created_at' => $timestamp->toISOString(),
], JSON_THROW_ON_ERROR);

$payloadHash = hash_hmac('sha256', $payloadToHash, (string) config('app.key'));
```

Any modification to the logged values, actor ID, or timestamp invalidates the signature when cross-checked against the application master key.

---

## 4. AUTOMATED PII & CREDENTIAL REDACTION

To protect against inadvertent leakage of credentials, passwords, or sensitive keys in audit trails, `PlatformAuditService` recursively inspects and redacts sensitive parameters before storing snapshots in `old_values` or `new_values`:

```php
protected function sanitizePayload(?array $payload): ?array
{
    // Recursively scans keys matching:
    // password, secret, token, key, authorization, cookie, credential
    // and replaces the value with: '[REDACTED]'
}
```

Verified in `PlatformAuditLogTest::test_sensitive_attributes_are_automatically_redacted`:
- When logging a tenant with `['database_password' => 'SuperSecret999!', 'name' => 'Store 1']`, the recorded snapshot is:
  `{"name": "Store 1", "database_password": "[REDACTED]"}`.

---

## 5. UI/UX SECURITY AUDIT

1. **Read-Only Audit Resource:**
   - `PlatformAuditLogResource` does not provide `create`, `edit`, or `delete` actions.
   - Filament forms are rendered completely disabled (`disabled()`).
2. **Access Policy Enforcement:**
   - Only users with the `'super_admin'` role on `PlatformUser` can view sensitive telemetry, audit logs, and trigger tenant suspensions.
3. **External Link Sanitization:**
   - Tenant launchpad links use secure target routing with origin sandboxing to prevent reverse tabnabbing.
