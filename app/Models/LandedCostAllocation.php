<?php

namespace App\Models;

use App\Core\Scoping\HasLocationScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $company_id
 * @property string $landed_cost_id
 * @property string $product_variant_id
 * @property string $warehouse_id
 * @property string|null $stock_location_id
 * @property string|null $batch_number
 * @property string $allocated_amount
 * @property string $adjustment_to_inventory
 * @property string $adjustment_to_cogs
 * @property bool $is_posted
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read LandedCost $landedCost
 * @property-read ProductVariant $productVariant
 * @property-read Warehouse $warehouse
 * @property-read StockLocation|null $stockLocation
 */
class LandedCostAllocation extends Model
{
    use HasLocationScope, HasUuids;

    protected $table = 'landed_cost_allocations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'landed_cost_id',
        'product_variant_id',
        'warehouse_id',
        'stock_location_id',
        'batch_number',
        'allocated_amount',
        'adjustment_to_inventory',
        'adjustment_to_cogs',
        'is_posted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_posted' => 'boolean',
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
     * @return BelongsTo<LandedCost, $this>
     */
    public function landedCost(): BelongsTo
    {
        return $this->belongsTo(LandedCost::class, 'landed_cost_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }
}
