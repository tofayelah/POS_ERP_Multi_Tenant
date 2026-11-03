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

    protected static function booted(): void
    {
        static::saving(function (Tenant $tenant): void {
            if (isset($tenant->subdomain) && is_string($tenant->subdomain)) {
                $tenant->subdomain = strtolower(trim($tenant->subdomain));
            }
            if (isset($tenant->name) && is_string($tenant->name)) {
                $tenant->name = trim($tenant->name);
            }
        });
    }

    /**
     * Retrieve the model for a bound value.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        if ($field === 'subdomain') {
            return $query->whereRaw('LOWER(subdomain) = ?', [strtolower((string) $value)]);
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }

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
            ->latest('created_at');
    }
}
