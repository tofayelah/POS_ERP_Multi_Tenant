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
 * @property string $expense_type
 * @property string $description
 * @property string $amount
 * @property string $tax_amount
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read LandedCostVoucher $voucher
 */
class LandedCostVoucherLine extends Model
{
    use HasLocationScope, HasUuids;

    protected $table = 'landed_cost_voucher_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'landed_cost_voucher_id',
        'expense_type',
        'description',
        'amount',
        'tax_amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
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
}
