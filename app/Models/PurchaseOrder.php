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
 * @property string $branch_id
 * @property string $supplier_id
 * @property string $order_number
 * @property Carbon $order_date
 * @property Carbon|null $expected_delivery_date
 * @property string $subtotal
 * @property string $tax_amount
 * @property string $grand_total
 * @property string $status
 * @property string $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read Branch $branch
 * @property-read Supplier $supplier
 * @property-read User $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PurchaseOrderLine> $lines
 * @property-read \Illuminate\Database\Eloquent\Collection<int, GoodsReceipt> $goodsReceipts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SupplierBill> $bills
 */
class PurchaseOrder extends Model
{
    use HasUuids;

    protected $table = 'purchase_orders';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'branch_id',
        'supplier_id',
        'order_number',
        'order_date',
        'expected_delivery_date',
        'subtotal',
        'tax_amount',
        'grand_total',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'subtotal' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'grand_total' => 'decimal:4',
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
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'purchase_order_id');
    }

    /**
     * @return HasMany<GoodsReceipt, $this>
     */
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'purchase_order_id');
    }

    /**
     * @return HasMany<SupplierBill, $this>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(SupplierBill::class, 'purchase_order_id');
    }
}
