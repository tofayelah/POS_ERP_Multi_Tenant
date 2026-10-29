<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $subdomain
 * @property string|null $custom_domain
 * @property string $subscription_plan
 * @property string $status
 * @property string $data_residency_region
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Tenant extends Model
{
    use HasFactory, HasUuids;

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
}
