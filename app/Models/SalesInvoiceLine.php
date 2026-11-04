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
 * @property string $sales_invoice_id
 * @property string|null $sales_order_line_id
 * @property string|null $delivery_challan_line_id
 * @property string $product_variant_id
 * @property string $item_description
 * @property string $uom
 * @property string|null $batch_number
 * @property string $quantity
 * @property string $unit_price
 * @property string $pricing_basis
 * @property string $line_discount_amount
 * @property string $tax_code_id
 * @property string $vat_rate
 * @property string $supplementary_duty_rate
 * @property string $supplementary_duty_amount
 * @property string $tax_amount
 * @property string $line_total
 * @property Carbon $created_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read SalesInvoice $salesInvoice
 * @property-read ProductVariant $productVariant
 * @property-read TaxCode $taxCode
 */
class SalesInvoiceLine extends Model
{
    use HasUuids;

    protected $table = 'sales_invoice_lines';

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'sales_invoice_id',
        'sales_order_line_id',
        'delivery_challan_line_id',
        'product_variant_id',
        'item_description',
        'uom',
        'batch_number',
        'quantity',
        'unit_price',
        'pricing_basis',
        'line_discount_amount',
        'tax_code_id',
        'vat_rate',
        'supplementary_duty_rate',
        'supplementary_duty_amount',
        'tax_amount',
        'line_total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'line_discount_amount' => 'decimal:4',
            'vat_rate' => 'decimal:2',
            'supplementary_duty_rate' => 'decimal:2',
            'supplementary_duty_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
            'created_at' => 'datetime',
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
     * @return BelongsTo<SalesInvoice, $this>
     */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<SalesOrderLine, $this>
     */
    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class, 'sales_order_line_id');
    }

    /**
     * @return BelongsTo<DeliveryChallanLine, $this>
     */
    public function deliveryChallanLine(): BelongsTo
    {
        return $this->belongsTo(DeliveryChallanLine::class, 'delivery_challan_line_id');
    }

    /**
     * @return BelongsTo<TaxCode, $this>
     */
    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'tax_code_id');
    }
}
