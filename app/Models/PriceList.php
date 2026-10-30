<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $company_id
 * @property string $code
 * @property string $name_en
 * @property string|null $name_bn
 * @property string $currency
 * @property bool $is_tax_inclusive
 * @property Carbon|string|null $effective_from
 * @property Carbon|string|null $effective_to
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PriceList extends Model
{
    use HasUuids;

    protected $table = 'price_lists';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'code',
        'name_en',
        'name_bn',
        'currency',
        'is_tax_inclusive',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_tax_inclusive' => 'boolean',
            'is_active' => 'boolean',
            'effective_from' => 'date:Y-m-d',
            'effective_to' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<PriceListItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class, 'price_list_id');
    }
}
