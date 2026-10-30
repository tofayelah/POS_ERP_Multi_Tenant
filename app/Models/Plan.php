<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasUuids;

    protected $table = 'plans';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'name',
        'description',
        'price_bdt',
        'billing_interval',
        'included_modules',
        'max_branches',
        'max_terminals',
        'max_users',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_bdt' => 'string',
            'included_modules' => 'array',
            'max_branches' => 'integer',
            'max_terminals' => 'integer',
            'max_users' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<TenantSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'plan_id');
    }
}
