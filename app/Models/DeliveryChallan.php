<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
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
 * @property string $warehouse_id
 * @property string $customer_id
 * @property string|null $sales_order_id
 * @property string $challan_number
 * @property Carbon $challan_date
 * @property string $status
 * @property string|null $vehicle_number
 * @property string|null $driver_name
 * @property string|null $driver_phone
 * @property string $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read Branch $branch
 * @property-read Warehouse $warehouse
 * @property-read Customer $customer
 * @property-read SalesOrder|null $salesOrder
 * @property-read User $creator
 * @property-read Collection<int, DeliveryChallanLine> $lines
 * @property-read Collection<int, SalesInvoice> $invoices
 */
class DeliveryChallan extends Model
{
    use HasUuids;

    protected $table = 'delivery_challans';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'branch_id',
        'warehouse_id',
        'customer_id',
        'sales_order_id',
        'challan_number',
        'challan_date',
        'status',
        'vehicle_number',
        'driver_name',
        'driver_phone',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'challan_date' => 'date',
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
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<DeliveryChallanLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryChallanLine::class, 'delivery_challan_id');
    }

    /**
     * @return HasMany<SalesInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class, 'delivery_challan_id');
    }
}
