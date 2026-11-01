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
 * @property string $credit_note_id
 * @property string|null $sales_invoice_line_id
 * @property string $product_variant_id
 * @property string $item_description
 * @property string $uom
 * @property string $quantity
 * @property string $unit_price
 * @property string $subtotal
 * @property string $vat_rate
 * @property string $tax_amount
 * @property string $supplementary_duty_rate
 * @property string $supplementary_duty_amount
 * @property string $line_total
 * @property Carbon $created_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read CreditNote $creditNote
 * @property-read ProductVariant $productVariant
 */
class CreditNoteLine extends Model
{
    use HasUuids;

    protected $table = 'credit_note_lines';

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'credit_note_id',
        'sales_invoice_line_id',
        'product_variant_id',
        'item_description',
        'uom',
        'quantity',
        'unit_price',
        'subtotal',
        'vat_rate',
        'tax_amount',
        'supplementary_duty_rate',
        'supplementary_duty_amount',
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
            'subtotal' => 'decimal:4',
            'vat_rate' => 'decimal:2',
            'tax_amount' => 'decimal:4',
            'supplementary_duty_rate' => 'decimal:2',
            'supplementary_duty_amount' => 'decimal:4',
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
     * @return BelongsTo<CreditNote, $this>
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
