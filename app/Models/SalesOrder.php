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
 * @property string $customer_id
 * @property string|null $quotation_id
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
 * @property-read Customer $customer
 * @property-read Quotation|null $quotation
 * @property-read User $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SalesOrderLine> $lines
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DeliveryChallan> $deliveryChallans
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SalesInvoice> $invoices
 */
class SalesOrder extends Model
{
    use HasUuids;

    protected $table = 'sales_orders';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'branch_id',
        'customer_id',
        'quotation_id',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<SalesOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class, 'sales_order_id');
    }

    /**
     * @return HasMany<DeliveryChallan, $this>
     */
    public function deliveryChallans(): HasMany
    {
        return $this->hasMany(DeliveryChallan::class, 'sales_order_id');
    }

    /**
     * @return HasMany<SalesInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class, 'sales_order_id');
    }
}
