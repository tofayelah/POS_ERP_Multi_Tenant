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
 * @property string $landed_cost_voucher_id
 * @property string $goods_receipt_id
 * @property string $goods_receipt_line_id
 * @property string $product_id
 * @property string $product_variant_id
 * @property string $warehouse_id
 * @property string|null $batch_number
 * @property string $allocated_amount
 * @property string $additional_unit_cost
 * @property string $adjustment_to_inventory
 * @property string $adjustment_to_cogs
 * @property bool $is_posted
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read LandedCostVoucher $voucher
 * @property-read GoodsReceipt $goodsReceipt
 * @property-read GoodsReceiptLine $goodsReceiptLine
 * @property-read Product $product
 * @property-read ProductVariant $productVariant
 * @property-read Warehouse $warehouse
 */
class LandedCostVoucherAllocation extends Model
{
    use HasLocationScope, HasUuids;

    protected $table = 'landed_cost_voucher_allocations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'landed_cost_voucher_id',
        'goods_receipt_id',
        'goods_receipt_line_id',
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'batch_number',
        'allocated_amount',
        'additional_unit_cost',
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
            'allocated_amount' => 'decimal:4',
            'additional_unit_cost' => 'decimal:4',
            'adjustment_to_inventory' => 'decimal:4',
            'adjustment_to_cogs' => 'decimal:4',
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
     * @return BelongsTo<LandedCostVoucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(LandedCostVoucher::class, 'landed_cost_voucher_id');
    }

    /**
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    /**
     * @return BelongsTo<GoodsReceiptLine, $this>
     */
    public function goodsReceiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class, 'goods_receipt_line_id');
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
}
