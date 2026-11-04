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
 * @property string $sales_order_id
 * @property string|null $quotation_line_id
 * @property string $product_id
 * @property string $variant_id
 * @property string $ordered_quantity
 * @property string $delivered_quantity
 * @property string $invoiced_quantity
 * @property string $unit_price
 * @property string $subtotal
 * @property string $tax_amount
 * @property string $total_amount
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read SalesOrder $salesOrder
 * @property-read QuotationLine|null $quotationLine
 * @property-read Product $product
 * @property-read ProductVariant $variant
 */
class SalesOrderLine extends Model
{
    use HasUuids;

    protected $table = 'sales_order_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'sales_order_id',
        'quotation_line_id',
        'product_id',
        'variant_id',
        'ordered_quantity',
        'delivered_quantity',
        'invoiced_quantity',
        'unit_price',
        'subtotal',
        'tax_amount',
        'total_amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'decimal:4',
            'delivered_quantity' => 'decimal:4',
            'invoiced_quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
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
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    /**
     * @return BelongsTo<QuotationLine, $this>
     */
    public function quotationLine(): BelongsTo
    {
        return $this->belongsTo(QuotationLine::class, 'quotation_line_id');
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
