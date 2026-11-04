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
 * @property string $quantity_received
 * @property string $quantity_remaining
 * @property string $unit_cost
 * @property Carbon $receipt_date
 * @property bool $is_provisional
 * @property string $reference_type
 * @property string $reference_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read Warehouse $warehouse
 * @property-read StockLocation|null $stockLocation
 * @property-read ProductVariant $productVariant
 */
class CostLayer extends Model
{
    use HasLocationScope, HasUuids;

    protected $table = 'cost_layers';

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
        'quantity_received',
        'quantity_remaining',
        'unit_cost',
        'receipt_date',
        'is_provisional',
        'reference_type',
        'reference_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_received' => 'string',
            'quantity_remaining' => 'string',
            'unit_cost' => 'string',
            'receipt_date' => 'date',
            'is_provisional' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
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
