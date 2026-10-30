<?php

namespace App\Core\Security;

use App\Core\Security\Events\SupportAccessGranted;
use App\Core\Security\Events\SupportAccessRevoked;
use App\Models\PlatformUser;
use App\Models\Tenant;
use App\Models\TenantSupportAccess;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

class SupportAccessService
{
    /**
     * Grant time-limited support access to a platform user with Tenant Owner consent.
     */
    public function grant(
        Tenant $tenant,
        PlatformUser $platformUser,
        User $grantedBy,
        string $ticketReference,
        int $durationMinutes,
        bool $isReadOnly = true
    ): TenantSupportAccess {
        if (! $grantedBy->hasRole('Tenant Owner')) {
            throw new \RuntimeException('Only a Tenant Owner can grant support access.');
        }

        if ($durationMinutes < 1 || $durationMinutes > 240) {
            throw new \InvalidArgumentException('Support access duration must not exceed 4 hours (240 minutes).');
        }

        $now = now();
        $expiresAt = (clone $now)->addMinutes($durationMinutes);

        $access = TenantSupportAccess::create([
            'id' => (string) Uuid::uuid7(),
            'tenant_id' => $tenant->id,
            'platform_user_id' => $platformUser->id,
            'granted_by_user_id' => $grantedBy->id,
            'ticket_reference' => $ticketReference,
            'granted_at' => $now,
            'expires_at' => $expiresAt,
            'is_read_only' => $isReadOnly,
            'revoked_at' => null,
        ]);

        Log::info('AUDIT: Support access granted', [
            'access_id' => $access->id,
            'tenant_id' => $tenant->id,
            'platform_user_id' => $platformUser->id,
            'granted_by_user_id' => $grantedBy->id,
            'ticket_reference' => $ticketReference,
            'granted_at' => $now->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
            'is_read_only' => $isReadOnly,
        ]);

        SupportAccessGranted::dispatch($access);

        return $access;
    }

    /**
     * Revoke active support access.
     */
    public function revoke(TenantSupportAccess $access): void
    {
        $access->update([
            'revoked_at' => now(),
        ]);

        Log::info('AUDIT: Support access revoked', [
            'access_id' => $access->id,
            'tenant_id' => $access->tenant_id,
            'revoked_at' => now()->toIso8601String(),
        ]);

        SupportAccessRevoked::dispatch($access);
    }
}
