<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $company_id
 * @property string $product_id
 * @property string $tax_code_id
 * @property string $sku
 * @property string $name_en
 * @property string|null $name_bn
 * @property string $base_uom
 * @property string $mrp
 * @property string $cost_price
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ProductVariant extends Model
{
    use HasUuids;

    protected $table = 'product_variants';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'product_id',
        'tax_code_id',
        'sku',
        'name_en',
        'name_bn',
        'base_uom',
        'mrp',
        'cost_price',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mrp' => 'decimal:4',
            'cost_price' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ProductVariant $variant) {
            if ($variant->product) {
                $variant->tenant_id ??= $variant->product->tenant_id;
                $variant->company_id ??= $variant->product->company_id;
            } elseif ($variant->product_id) {
                $product = Product::query()->find($variant->product_id);
                if ($product) {
                    $variant->tenant_id ??= $product->tenant_id;
                    $variant->company_id ??= $product->company_id;
                }
            }
        });
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * @return BelongsTo<TaxCode, $this>
     */
    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'tax_code_id');
    }

    /**
     * @return HasMany<VariantBarcode, $this>
     */
    public function barcodes(): HasMany
    {
        return $this->hasMany(VariantBarcode::class, 'variant_id');
    }
}
