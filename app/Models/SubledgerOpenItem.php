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
 * @property string $party_type
 * @property string $party_id
 * @property string $source_type
 * @property string $source_id
 * @property string $document_number
 * @property Carbon $document_date
 * @property Carbon $due_date
 * @property string $direction
 * @property string $gl_control_account_code
 * @property string $original_amount
 * @property string $allocated_amount
 * @property string $remaining_amount
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 */
class SubledgerOpenItem extends Model
{
    use HasUuids;

    protected $table = 'subledger_open_items';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'party_type',
        'party_id',
        'source_type',
        'source_id',
        'document_number',
        'document_date',
        'due_date',
        'direction',
        'gl_control_account_code',
        'original_amount',
        'allocated_amount',
        'remaining_amount',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'due_date' => 'date',
            'original_amount' => 'decimal:4',
            'allocated_amount' => 'decimal:4',
            'remaining_amount' => 'decimal:4',
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
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'open_item_id');
    }
}
