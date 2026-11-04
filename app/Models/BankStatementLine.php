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
 * @property string $bank_statement_id
 * @property Carbon $line_date
 * @property string $description
 * @property string|null $reference_number
 * @property string $deposit_amount
 * @property string $withdrawal_amount
 * @property string $balance
 * @property bool $is_reconciled
 * @property string|null $matched_payment_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Company $company
 * @property-read BankStatement $statement
 * @property-read Payment|null $payment
 */
class BankStatementLine extends Model
{
    use HasUuids;

    protected $table = 'bank_statement_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'bank_statement_id',
        'line_date',
        'description',
        'reference_number',
        'deposit_amount',
        'withdrawal_amount',
        'balance',
        'is_reconciled',
        'matched_payment_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line_date' => 'date',
            'deposit_amount' => 'decimal:4',
            'withdrawal_amount' => 'decimal:4',
            'balance' => 'decimal:4',
            'is_reconciled' => 'boolean',
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
     * @return BelongsTo<BankStatement, $this>
     */
    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'matched_payment_id');
    }
}
