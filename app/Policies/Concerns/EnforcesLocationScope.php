<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait EnforcesLocationScope
{
    /**
     * Determine whether the user can access the location of a record.
     */
    protected function canAccessLocation(User $user, ?string $companyId, ?string $branchId = null): bool
    {
        return $user->canAccessLocation($companyId, $branchId);
    }
}
