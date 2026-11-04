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
 * @property string|null $branch_id
 * @property string $warehouse_id
 * @property string|null $stock_location_id
 * @property string $product_variant_id
 * @property string $quantity_oversold
 * @property string|null $sales_invoice_id
 * @property string $cost_basis_used
 * @property string $status
 * @property Carbon|null $resolved_at
 * @property string|null $resolved_by_document_type
 * @property string|null $resolved_by_document_id
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read Warehouse $warehouse
 * @property-read ProductVariant $productVariant
 * @property-read StockLocation|null $stockLocation
 */
class StockOversellException extends Model
{
    use HasLocationScope, HasUuids;

    protected $table = 'stock_oversell_exceptions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'branch_id',
        'warehouse_id',
        'stock_location_id',
        'product_variant_id',
        'quantity_oversold',
        'sales_invoice_id',
        'cost_basis_used',
        'status',
        'resolved_at',
        'resolved_by_document_type',
        'resolved_by_document_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
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

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
