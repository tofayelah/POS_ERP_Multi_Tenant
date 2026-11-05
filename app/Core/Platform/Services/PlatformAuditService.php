<?php

namespace App\Core\Platform\Services;

use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PlatformAuditService
{
    /**
     * Attributes that must be masked in audit logs.
     *
     * @var array<int, string>
     */
    protected array $sensitiveKeys = [
        'password',
        'admin_password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        'secret',
        'token',
        'api_key',
    ];

    /**
     * Record an administrative audit log entry.
     *
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public function record(
        string $action,
        Model|string $resource,
        string $resourceId,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        string $status = 'success',
        ?string $errorMessage = null,
        ?PlatformUser $actor = null
    ): PlatformAuditLog {
        $actor = $actor ?? (auth('platform')->check() ? auth('platform')->user() : null);

        $actorId = $actor instanceof PlatformUser ? $actor->id : null;
        $actorName = $actor instanceof PlatformUser ? $actor->name : 'System / Artisan CLI';
        $actorEmail = $actor instanceof PlatformUser ? $actor->email : 'system@kcerp.local';

        $resourceType = is_string($resource) ? $resource : get_class($resource);

        $maskedOld = $oldValues !== null ? $this->maskSensitiveData($oldValues) : null;
        $maskedNew = $newValues !== null ? $this->maskSensitiveData($newValues) : null;

        $createdAt = Carbon::now();

        // Calculate cryptographic SHA-256 HMAC payload hash
        $serialized = json_encode([
            'action' => $action,
            'actor_id' => $actorId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'old' => $maskedOld,
            'new' => $maskedNew,
            'reason' => $reason,
            'status' => $status,
            'created_at' => $createdAt->toIso8601String(),
        ], JSON_THROW_ON_ERROR);

        $key = (string) (config('app.key') ?? 'kcerp-platform-audit-hmac-key');
        $payloadHash = hash_hmac('sha256', $serialized, $key);

        $clientIp = request()?->ip();
        $userAgent = request()?->userAgent();

        return PlatformAuditLog::create([
            'id' => (string) Str::uuid(),
            'platform_user_id' => $actorId,
            'actor_name' => $actorName,
            'actor_email' => $actorEmail,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'resource_description' => $description,
            'old_values' => $maskedOld,
            'new_values' => $maskedNew,
            'reason' => $reason,
            'client_ip' => $clientIp,
            'user_agent' => $userAgent ? Str::limit($userAgent, 500) : null,
            'status' => $status,
            'error_message' => $errorMessage,
            'payload_hash' => $payloadHash,
            'created_at' => $createdAt,
        ]);
    }

    /**
     * Log tenant suspension.
     */
    public function logTenantSuspension(Tenant $tenant, string $reason, ?PlatformUser $actor = null): PlatformAuditLog
    {
        return $this->record(
            action: 'tenant.suspended',
            resource: $tenant,
            resourceId: $tenant->id,
            description: "Tenant [{$tenant->name}] ({$tenant->subdomain}) suspended",
            oldValues: ['status' => 'active'],
            newValues: ['status' => 'suspended'],
            reason: $reason,
            actor: $actor
        );
    }

    /**
     * Log tenant reinstatement.
     */
    public function logTenantReinstatement(Tenant $tenant, ?PlatformUser $actor = null): PlatformAuditLog
    {
        return $this->record(
            action: 'tenant.reinstated',
            resource: $tenant,
            resourceId: $tenant->id,
            description: "Tenant [{$tenant->name}] ({$tenant->subdomain}) reinstated to active",
            oldValues: ['status' => 'suspended'],
            newValues: ['status' => 'active'],
            actor: $actor
        );
    }

    /**
     * Log tenant provisioning.
     *
     * @param array<string, mixed> $data
     */
    public function logTenantCreation(Tenant $tenant, array $data, ?PlatformUser $actor = null): PlatformAuditLog
    {
        return $this->record(
            action: 'tenant.created',
            resource: $tenant,
            resourceId: $tenant->id,
            description: "New tenant [{$tenant->name}] ({$tenant->subdomain}) provisioned",
            newValues: $data,
            actor: $actor
        );
    }

    /**
     * Log plan creation.
     */
    public function logPlanCreation(Plan $plan, ?PlatformUser $actor = null): PlatformAuditLog
    {
        return $this->record(
            action: 'plan.created',
            resource: $plan,
            resourceId: $plan->id,
            description: "Subscription plan [{$plan->name}] ({$plan->code}) created",
            newValues: $plan->toArray(),
            actor: $actor
        );
    }

    /**
     * Log plan update.
     *
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     */
    public function logPlanUpdate(Plan $plan, array $oldValues, array $newValues, ?PlatformUser $actor = null): PlatformAuditLog
    {
        return $this->record(
            action: 'plan.updated',
            resource: $plan,
            resourceId: $plan->id,
            description: "Subscription plan [{$plan->name}] ({$plan->code}) updated",
            oldValues: $oldValues,
            newValues: $newValues,
            actor: $actor
        );
    }

    /**
     * Recursively mask sensitive keys in data payloads.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function maskSensitiveData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $this->sensitiveKeys, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            }
        }

        return $data;
    }
}
