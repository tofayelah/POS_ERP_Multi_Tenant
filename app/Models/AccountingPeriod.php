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
 * @property string $fiscal_year_id
 * @property int $period_number
 * @property string $period_name
 * @property string $start_date
 * @property string $end_date
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read FiscalYear $fiscalYear
 * @property-read Company $company
 */
class AccountingPeriod extends Model
{
    use HasUuids;

    protected $table = 'accounting_periods';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'fiscal_year_id',
        'period_number',
        'period_name',
        'start_date',
        'end_date',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_number' => 'integer',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
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
     * @return BelongsTo<FiscalYear, $this>
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isSoftClosed(): bool
    {
        return $this->status === 'soft-closed';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
