<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $subdomain
 * @property string|null $custom_domain
 * @property string $subscription_plan
 * @property string $status
 * @property string $data_residency_region
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Tenant extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'subdomain',
        'custom_domain',
        'subscription_plan',
        'status',
        'data_residency_region',
    ];

    /**
     * Get the companies belonging to this tenant.
     *
     * @return HasMany<Company, $this>
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'tenant_id');
    }

    /**
     * @return HasMany<TenantSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'tenant_id');
    }

    /**
     * @return HasOne<TenantSubscription, $this>
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class, 'tenant_id')
            ->where('status', 'active')
            ->latestOfMany();
    }
}
