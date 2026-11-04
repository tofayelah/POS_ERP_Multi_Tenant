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
 * @property string $transfer_id
 * @property string $product_variant_id
 * @property string|null $stock_location_id
 * @property string|null $dest_stock_location_id
 * @property string|null $batch_number
 * @property string $quantity_shipped
 * @property string $quantity_received
 * @property string $discrepancy_quantity
 * @property string $unit_cost
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read StockTransfer $transfer
 * @property-read ProductVariant $productVariant
 * @property-read StockLocation|null $stockLocation
 * @property-read StockLocation|null $destStockLocation
 */
class StockTransferLine extends Model
{
    use HasLocationScope, HasUuids;

    protected $table = 'stock_transfer_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'transfer_id',
        'product_variant_id',
        'stock_location_id',
        'dest_stock_location_id',
        'batch_number',
        'quantity_shipped',
        'quantity_received',
        'discrepancy_quantity',
        'unit_cost',
        'notes',
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
     * @return BelongsTo<StockTransfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'transfer_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function destStockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'dest_stock_location_id');
    }
}
