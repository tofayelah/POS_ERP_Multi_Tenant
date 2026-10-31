<?php

namespace App\Models;

use App\Core\Scoping\HasLocationScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $company_id
 * @property string $cost_type
 * @property string $amount
 * @property string $allocation_method
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property string $status
 * @property Carbon|null $posted_at
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read Collection<int, LandedCostAllocation> $allocations
 */
class LandedCost extends Model
{
    use HasLocationScope, HasUuids;

    protected $table = 'landed_costs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'cost_type',
        'amount',
        'allocation_method',
        'reference_type',
        'reference_id',
        'status',
        'posted_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * @return HasMany<LandedCostAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(LandedCostAllocation::class, 'landed_cost_id');
    }
}
