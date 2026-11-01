<?php

namespace App\Models;

use App\Core\Scoping\HasLocationScope;
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
 * @property string|null $terminal_id
 * @property string|null $client_txn_id
 * @property string $invoice_number
 * @property string|null $sdc_invoice_number
 * @property string $invoice_date
 * @property string $business_date
 * @property string|null $customer_id
 * @property string|null $sales_order_id
 * @property string|null $delivery_challan_id
 * @property string $status
 * @property string $subtotal
 * @property string $discount_amount
 * @property string $supplementary_duty_amount
 * @property string $tax_amount
 * @property string $total_payable
 * @property string $payment_status
 * @property string|null $fiscal_signature
 * @property bool $is_synced_from_offline
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read Branch $branch
 * @property-read PosTerminal|null $terminal
 * @property-read Customer|null $customer
 * @property-read Collection<int, SalesInvoiceLine> $lines
 */
class SalesInvoice extends Model
{
    use HasLocationScope, HasUuids;

    protected $table = 'sales_invoices';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'branch_id',
        'terminal_id',
        'client_txn_id',
        'invoice_number',
        'sdc_invoice_number',
        'invoice_date',
        'business_date',
        'customer_id',
        'sales_order_id',
        'delivery_challan_id',
        'status',
        'subtotal',
        'discount_amount',
        'supplementary_duty_amount',
        'tax_amount',
        'total_payable',
        'payment_status',
        'fiscal_signature',
        'is_synced_from_offline',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_date' => 'date:Y-m-d',
            'business_date' => 'date:Y-m-d',
            'subtotal' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'supplementary_duty_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total_payable' => 'decimal:4',
            'is_synced_from_offline' => 'boolean',
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
     * @return BelongsTo<PosTerminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'terminal_id');
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
     * @return BelongsTo<DeliveryChallan, $this>
     */
    public function deliveryChallan(): BelongsTo
    {
        return $this->belongsTo(DeliveryChallan::class, 'delivery_challan_id');
    }

    /**
     * @return HasMany<SalesInvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class, 'sales_invoice_id');
    }
}
