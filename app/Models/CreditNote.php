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
 * @property string $sales_invoice_id
 * @property string|null $customer_id
 * @property string $credit_note_number
 * @property string $credit_note_date
 * @property string $reason
 * @property string $status
 * @property string $subtotal
 * @property string $discount_amount
 * @property string $supplementary_duty_amount
 * @property string $tax_amount
 * @property string $total_amount
 * @property string|null $mushak_6_7_number
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read Branch $branch
 * @property-read SalesInvoice $salesInvoice
 * @property-read Customer|null $customer
 * @property-read Collection<int, CreditNoteLine> $lines
 */
class CreditNote extends Model
{
    use HasLocationScope, HasUuids;

    protected $table = 'credit_notes';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'branch_id',
        'sales_invoice_id',
        'customer_id',
        'credit_note_number',
        'credit_note_date',
        'reason',
        'status',
        'subtotal',
        'discount_amount',
        'supplementary_duty_amount',
        'tax_amount',
        'total_amount',
        'mushak_6_7_number',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_note_date' => 'date:Y-m-d',
            'subtotal' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'supplementary_duty_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
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
     * @return BelongsTo<SalesInvoice, $this>
     */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<CreditNoteLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(CreditNoteLine::class);
    }
}
