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
 * @property string $branch_id
 * @property string $certificate_number
 * @property string $direction
 * @property string|null $customer_id
 * @property string|null $supplier_id
 * @property string $withholding_entity_name
 * @property string $withholding_entity_bin
 * @property Carbon $issue_date
 * @property string $tax_period
 * @property string $total_invoice_amount
 * @property string $total_vat_amount
 * @property string $vds_amount
 * @property string|null $sales_invoice_id
 * @property string|null $supplier_bill_id
 * @property string|null $payment_id
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read Branch $branch
 * @property-read Customer|null $customer
 * @property-read Supplier|null $supplier
 * @property-read SalesInvoice|null $salesInvoice
 * @property-read SupplierBill|null $supplierBill
 * @property-read Payment|null $payment
 */
class VdsCertificate extends Model
{
    use HasUuids;

    protected $table = 'vds_certificates';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'branch_id',
        'certificate_number',
        'direction',
        'customer_id',
        'supplier_id',
        'withholding_entity_name',
        'withholding_entity_bin',
        'issue_date',
        'tax_period',
        'total_invoice_amount',
        'total_vat_amount',
        'vds_amount',
        'sales_invoice_id',
        'supplier_bill_id',
        'payment_id',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'total_invoice_amount' => 'decimal:4',
            'total_vat_amount' => 'decimal:4',
            'vds_amount' => 'decimal:4',
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
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<SalesInvoice, $this>
     */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    /**
     * @return BelongsTo<SupplierBill, $this>
     */
    public function supplierBill(): BelongsTo
    {
        return $this->belongsTo(SupplierBill::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
