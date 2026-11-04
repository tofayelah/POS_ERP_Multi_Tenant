<?php

namespace App\Models;

use App\Core\Scoping\HasLocationScope;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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
 * @property string $quantity_on_hand
 * @property string $quantity_reserved
 * @property string $weighted_avg_cost
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string $quantity_available
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read Warehouse $warehouse
 * @property-read StockLocation|null $stockLocation
 * @property-read ProductVariant $productVariant
 */
class StockBalance extends Model
{
    use HasLocationScope, HasUuids;

    protected $table = 'stock_balances';

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
        'quantity_on_hand',
        'quantity_reserved',
        'weighted_avg_cost',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'string',
            'quantity_reserved' => 'string',
            'weighted_avg_cost' => 'string',
            'expiry_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Calculate net available inventory (on_hand - reserved).
     */
    public function getQuantityAvailableAttribute(): string
    {
        $onHand = BigDecimal::of($this->quantity_on_hand ?? '0.0000');
        $reserved = BigDecimal::of($this->quantity_reserved ?? '0.0000');

        return (string) $onHand->minus($reserved)->toScale(4, RoundingMode::HalfUp);
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
