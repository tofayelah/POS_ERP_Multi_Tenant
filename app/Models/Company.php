<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $legal_name
 * @property string|null $bin_number
 * @property string $base_currency
 * @property int $fiscal_year_start_month
 * @property string $tax_regime
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Company extends Model
{
    use HasFactory, HasUuids;

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
}
