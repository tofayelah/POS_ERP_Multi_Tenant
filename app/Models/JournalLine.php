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
 * @property string $journal_entry_id
 * @property string $account_id
 * @property string $debit_amount
 * @property string $credit_amount
 * @property string $currency
 * @property string $exchange_rate
 * @property string $base_debit_amount
 * @property string $base_credit_amount
 * @property string|null $partner_id
 * @property string|null $tax_code_id
 * @property string|null $branch_id
 * @property string|null $line_description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read JournalEntry $entry
 * @property-read ChartOfAccount $account
 * @property-read TaxCode|null $taxCode
 * @property-read Branch|null $branch
 */
class JournalLine extends Model
{
    use HasUuids;

    protected $table = 'journal_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'journal_entry_id',
        'account_id',
        'debit_amount',
        'credit_amount',
        'currency',
        'exchange_rate',
        'base_debit_amount',
        'base_credit_amount',
        'partner_id',
        'tax_code_id',
        'branch_id',
        'line_description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'debit_amount' => 'decimal:4',
            'credit_amount' => 'decimal:4',
            'exchange_rate' => 'decimal:6',
            'base_debit_amount' => 'decimal:4',
            'base_credit_amount' => 'decimal:4',
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
     * @return BelongsTo<JournalEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * @return BelongsTo<ChartOfAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    /**
     * @return BelongsTo<TaxCode, $this>
     */
    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'tax_code_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
