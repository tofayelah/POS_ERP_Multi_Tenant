<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int|string $id
 * @property string|null $tenant_id
 * @property string $name
 * @property string $guard_name
 * @property-read Tenant|null $tenant
 */
class Role extends SpatieRole
{
    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
