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
 * @property string $voucher_number
 * @property Carbon $voucher_date
 * @property string $currency
 * @property string $exchange_rate
 * @property string $total_cost
 * @property string $allocation_method
 * @property string $status
 * @property string|null $created_by
 * @property string|null $posted_by
 * @property Carbon|null $posted_at
 * @property string|null $gl_journal_entry_id
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read Branch $branch
 * @property-read User|null $creator
 * @property-read User|null $poster
 * @property-read JournalEntry|null $journalEntry
 * @property-read Collection<int, LandedCostVoucherReceipt> $receipts
 * @property-read Collection<int, LandedCostVoucherLine> $lines
 * @property-read Collection<int, LandedCostVoucherAllocation> $allocations
 */
class LandedCostVoucher extends Model
{
    use HasLocationScope, HasUuids;

    protected $table = 'landed_cost_vouchers';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'branch_id',
        'voucher_number',
        'voucher_date',
        'currency',
        'exchange_rate',
        'total_cost',
        'allocation_method',
        'status',
        'created_by',
        'posted_by',
        'posted_at',
        'gl_journal_entry_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'voucher_date' => 'date',
            'posted_at' => 'datetime',
            'exchange_rate' => 'decimal:6',
            'total_cost' => 'decimal:4',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'gl_journal_entry_id');
    }

    /**
     * @return HasMany<LandedCostVoucherReceipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(LandedCostVoucherReceipt::class, 'landed_cost_voucher_id');
    }

    /**
     * @return HasMany<LandedCostVoucherLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(LandedCostVoucherLine::class, 'landed_cost_voucher_id');
    }

    /**
     * @return HasMany<LandedCostVoucherAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(LandedCostVoucherAllocation::class, 'landed_cost_voucher_id');
    }
}
