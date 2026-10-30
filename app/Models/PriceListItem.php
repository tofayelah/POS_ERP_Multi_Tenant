<?php

namespace App\Models;

use App\Core\Tenancy\TenantContext;
use App\Modules\Catalog\Exceptions\MrpCeilingExceededException;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $company_id
 * @property string $price_list_id
 * @property string $variant_id
 * @property string $unit_price
 * @property string $min_quantity
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PriceList $priceList
 * @property-read ProductVariant $variant
 */
class PriceListItem extends Model
{
    use HasUuids;

    protected $table = 'price_list_items';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'price_list_id',
        'variant_id',
        'unit_price',
        'min_quantity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'min_quantity' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PriceListItem $item) {
            if ($item->price_list_id) {
                /** @var PriceList|null $list */
                $list = $item->priceList ?? PriceList::query()->find($item->price_list_id);
                if ($list) {
                    $item->tenant_id ??= $list->tenant_id;
                    $item->company_id ??= $list->company_id;
                }
            }

            if (empty($item->tenant_id) && TenantContext::has()) {
                $item->tenant_id = TenantContext::id();
            }

            if (empty($item->company_id) && ! empty($item->tenant_id)) {
                $company = Company::where('tenant_id', $item->tenant_id)->first();
                if ($company) {
                    $item->company_id = $company->id;
                }
            }

            $variant = $item->variant ?? ProductVariant::query()->find($item->variant_id);

            if ($variant) {
                $price = BigDecimal::of((string) $item->unit_price);
                $mrp = BigDecimal::of((string) $variant->mrp);

                if ($price->compareTo($mrp) > 0) {
                    throw new MrpCeilingExceededException((string) $price, (string) $mrp, $variant->sku);
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
     * @return BelongsTo<PriceList, $this>
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class, 'price_list_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
