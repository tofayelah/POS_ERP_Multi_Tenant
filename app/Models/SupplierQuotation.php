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
 * @property string $supplier_id
 * @property string $rfq_number
 * @property string|null $supplier_quote_reference
 * @property Carbon $quote_date
 * @property Carbon $valid_until
 * @property string $currency
 * @property string $exchange_rate
 * @property string $subtotal
 * @property string $tax_amount
 * @property string $grand_total
 * @property string $status
 * @property Carbon|null $awarded_at
 * @property string|null $converted_po_id
 * @property string $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read Branch $branch
 * @property-read Supplier $supplier
 * @property-read PurchaseOrder|null $convertedPurchaseOrder
 * @property-read User $creator
 * @property-read Collection<int, SupplierQuotationLine> $lines
 */
class SupplierQuotation extends Model
{
    use HasUuids;

    protected $table = 'supplier_quotations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'branch_id',
        'supplier_id',
        'rfq_number',
        'supplier_quote_reference',
        'quote_date',
        'valid_until',
        'currency',
        'exchange_rate',
        'subtotal',
        'tax_amount',
        'grand_total',
        'status',
        'awarded_at',
        'converted_po_id',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quote_date' => 'date',
            'valid_until' => 'date',
            'awarded_at' => 'datetime',
            'exchange_rate' => 'decimal:6',
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
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function convertedPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'converted_po_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<SupplierQuotationLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SupplierQuotationLine::class, 'supplier_quotation_id');
    }
}
