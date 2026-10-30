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
 * @property string $variant_id
 * @property string $barcode
 * @property string $uom
 * @property string $multiplier
 * @property bool $is_primary
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ProductVariant $variant
 */
class VariantBarcode extends Model
{
    use HasUuids;

    protected $table = 'variant_barcodes';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'variant_id',
        'barcode',
        'uom',
        'multiplier',
        'is_primary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:4',
            'is_primary' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (VariantBarcode $barcode) {
            if ($barcode->variant_id) {
                /** @var ProductVariant|null $variant */
                $variant = $barcode->variant ?? ProductVariant::query()->find($barcode->variant_id);
                if ($variant) {
                    $barcode->tenant_id ??= $variant->tenant_id;
                    $barcode->company_id ??= $variant->company_id;
                }
            }

            if (empty($barcode->tenant_id) && \App\Core\Tenancy\TenantContext::has()) {
                $barcode->tenant_id = \App\Core\Tenancy\TenantContext::id();
            }

            if (empty($barcode->company_id) && !empty($barcode->tenant_id)) {
                $company = Company::where('tenant_id', $barcode->tenant_id)->first();
                if ($company) {
                    $barcode->company_id = $company->id;
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
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
