<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $company_id
 * @property string $period_id
 * @property string $entry_number
 * @property Carbon $posting_date
 * @property Carbon $business_date
 * @property string $reference_type
 * @property string|null $reference_id
 * @property string|null $narration
 * @property bool $is_posted
 * @property bool $is_reversal
 * @property string|null $reversed_entry_id
 * @property string $posted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read AccountingPeriod $period
 * @property-read JournalEntry|null $reversedEntry
 * @property-read JournalEntry|null $reversalEntry
 * @property-read User $postedByUser
 * @property-read Collection<int, JournalLine> $lines
 */
class JournalEntry extends Model
{
    use HasUuids;

    protected $table = 'journal_entries';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'period_id',
        'entry_number',
        'posting_date',
        'business_date',
        'reference_type',
        'reference_id',
        'narration',
        'is_posted',
        'is_reversal',
        'reversed_entry_id',
        'posted_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posting_date' => 'date',
            'business_date' => 'date',
            'is_posted' => 'boolean',
            'is_reversal' => 'boolean',
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
     * @return BelongsTo<AccountingPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_entry_id');
    }

    /**
     * @return HasOne<JournalEntry, $this>
     */
    public function reversalEntry(): HasOne
    {
        return $this->hasOne(JournalEntry::class, 'reversed_entry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'journal_entry_id');
    }
}
