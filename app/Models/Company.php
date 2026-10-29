<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $legal_name
 * @property string|null $bin_number
 * @property string $base_currency
 * @property int $fiscal_year_start_month
 * @property string $tax_regime
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Company extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'legal_name',
        'bin_number',
        'base_currency',
        'fiscal_year_start_month',
        'tax_regime',
    ];

    /**
     * Get the tenant that owns the company.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Get the branches belonging to this company.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Branch, $this>
     */
    public function branches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Branch::class, 'company_id');
    }
}
