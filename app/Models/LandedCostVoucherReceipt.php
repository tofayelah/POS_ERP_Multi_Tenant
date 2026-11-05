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
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read LandedCostVoucher $voucher
 * @property-read GoodsReceipt $goodsReceipt
 */
class LandedCostVoucherReceipt extends Model
{
    use HasLocationScope, HasUuids;

    protected $table = 'landed_cost_voucher_receipts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'landed_cost_voucher_id',
        'goods_receipt_id',
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
}
