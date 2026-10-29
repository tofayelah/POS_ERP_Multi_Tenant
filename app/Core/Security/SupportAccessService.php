<?php

namespace App\Core\Security;

use App\Models\PlatformUser;
use App\Models\Tenant;
use App\Models\TenantSupportAccess;
use App\Models\User;
use Illuminate\Validation\ValidationException;
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

        return TenantSupportAccess::create([
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
    }

    /**
     * Revoke active support access.
     */
    public function revoke(TenantSupportAccess $access): void
    {
        $access->update([
            'revoked_at' => now(),
        ]);
    }
}
