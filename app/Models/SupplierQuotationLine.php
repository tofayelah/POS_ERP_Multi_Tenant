<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $company_id
 * @property string $supplier_quotation_id
 * @property string $product_id
 * @property string $variant_id
 * @property string $requested_quantity
 * @property string $quoted_unit_price
 * @property string $discount_amount
 * @property string $tax_rate
 * @property string $tax_amount
 * @property string $line_total
 * @property int $lead_time_days
 * @property bool $is_selected
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read SupplierQuotation $quotation
 * @property-read Product $product
 * @property-read ProductVariant $variant
 */
class SupplierQuotationLine extends Model
{
    use HasUuids;

    protected $table = 'supplier_quotation_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'supplier_quotation_id',
        'product_id',
        'variant_id',
        'requested_quantity',
        'quoted_unit_price',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'line_total',
        'lead_time_days',
        'is_selected',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:4',
            'quoted_unit_price' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
            'lead_time_days' => 'integer',
            'is_selected' => 'boolean',
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
     * @return BelongsTo<SupplierQuotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(SupplierQuotation::class, 'supplier_quotation_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
