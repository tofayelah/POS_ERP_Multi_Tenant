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
 * @property string $session_id
 * @property string|null $stock_location_id
 * @property string $product_variant_id
 * @property string|null $last_movement_id
 * @property string|null $batch_number
 * @property string $snapshot_quantity
 * @property string $in_flight_movements
 * @property string|null $counted_quantity
 * @property string $variance_quantity
 * @property string $variance_value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read StockCountSession $session
 * @property-read StockLocation|null $stockLocation
 * @property-read ProductVariant $productVariant
 */
class StockCountLine extends Model
{
    use HasLocationScope, HasUuids;

    protected $table = 'stock_count_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'session_id',
        'stock_location_id',
        'product_variant_id',
        'last_movement_id',
        'batch_number',
        'snapshot_quantity',
        'in_flight_movements',
        'counted_quantity',
        'variance_quantity',
        'variance_value',
    ];

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
     * @return BelongsTo<StockCountSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(StockCountSession::class, 'session_id');
    }

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
