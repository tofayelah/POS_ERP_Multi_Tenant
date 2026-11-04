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
 * @property string $warehouse_id
 * @property string|null $stock_location_id
 * @property string $product_variant_id
 * @property string|null $batch_number
 * @property Carbon|null $expiry_date
 * @property string $movement_type
 * @property string $quantity
 * @property string $unit_cost
 * @property string $total_cost
 * @property string $reference_type
 * @property string $reference_id
 * @property Carbon $movement_date
 * @property Carbon $created_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read Warehouse $warehouse
 * @property-read StockLocation|null $stockLocation
 * @property-read ProductVariant $productVariant
 */
class StockMovement extends Model
{
    use HasLocationScope, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'stock_movements';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'warehouse_id',
        'stock_location_id',
        'product_variant_id',
        'batch_number',
        'expiry_date',
        'movement_type',
        'quantity',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'movement_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'string',
            'unit_cost' => 'string',
            'total_cost' => 'string',
            'expiry_date' => 'date',
            'movement_date' => 'datetime',
            'created_at' => 'datetime',
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
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
