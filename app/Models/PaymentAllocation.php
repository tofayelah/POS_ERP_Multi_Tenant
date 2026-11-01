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
 * @property string|null $payment_id
 * @property string|null $credit_note_id
 * @property string $open_item_id
 * @property string $allocated_amount
 * @property Carbon $allocation_date
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read Payment|null $payment
 * @property-read CreditNote|null $creditNote
 * @property-read SubledgerOpenItem $openItem
 */
class PaymentAllocation extends Model
{
    use HasUuids;

    protected $table = 'payment_allocations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'payment_id',
        'credit_note_id',
        'open_item_id',
        'allocated_amount',
        'allocation_date',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:4',
            'allocation_date' => 'date',
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
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<CreditNote, $this>
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /**
     * @return BelongsTo<SubledgerOpenItem, $this>
     */
    public function openItem(): BelongsTo
    {
        return $this->belongsTo(SubledgerOpenItem::class, 'open_item_id');
    }
}
