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
 * @property string $goods_receipt_id
 * @property string|null $purchase_order_line_id
 * @property string $product_id
 * @property string $variant_id
 * @property string $received_quantity
 * @property string $billed_quantity
 * @property string $unit_cost
 * @property string|null $batch_number
 * @property Carbon|null $expiry_date
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read GoodsReceipt $goodsReceipt
 * @property-read PurchaseOrderLine|null $purchaseOrderLine
 * @property-read Product $product
 * @property-read ProductVariant $variant
 */
class GoodsReceiptLine extends Model
{
    use HasUuids;

    protected $table = 'goods_receipt_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'goods_receipt_id',
        'purchase_order_line_id',
        'product_id',
        'variant_id',
        'received_quantity',
        'billed_quantity',
        'unit_cost',
        'batch_number',
        'expiry_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_quantity' => 'decimal:4',
            'billed_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'expiry_date' => 'date',
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
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    /**
     * @return BelongsTo<PurchaseOrderLine, $this>
     */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
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
