<?php

namespace App\Modules\Accounting\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\NumberSeries;
use App\Modules\Accounting\Contracts\NumberSeriesContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class NumberSeriesService implements NumberSeriesContract
{
    /**
     * Map of default prefixes per document type.
     *
     * @var array<string, string>
     */
    protected array $defaultPrefixes = [
        'journal' => 'JV',
        'sales_invoice' => 'SINV',
        'purchase_bill' => 'BILL',
        'payment' => 'PAY',
        'receipt' => 'REC',
        'credit_note' => 'CN',
        'debit_note' => 'DN',
    ];

    public function getNextNumber(string $companyId, ?string $branchId, string $documentType, string $fiscalYear): string
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new InvalidArgumentException('Cannot draw number series without active TenantContext.');
        }

        // Pessimistically lock matching series row
        /** @var NumberSeries|null $series */
        $series = NumberSeries::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId), fn ($q) => $q->whereNull('branch_id'))
            ->where('document_type', $documentType)
            ->where('fiscal_year', $fiscalYear)
            ->lockForUpdate()
            ->first();

        if (! $series) {
            $prefix = $this->defaultPrefixes[$documentType] ?? strtoupper(substr($documentType, 0, 4));

            try {
                $series = NumberSeries::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'document_type' => $documentType,
                    'prefix' => $prefix,
                    'fiscal_year' => $fiscalYear,
                    'current_number' => 0,
                    'padding' => 6,
                ]);
            } catch (Throwable) {
                // If a concurrent process inserted the series row simultaneously, query it with lock
                /** @var NumberSeries $series */
                $series = NumberSeries::query()
                    ->where('tenant_id', $tenantId)
                    ->where('company_id', $companyId)
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId), fn ($q) => $q->whereNull('branch_id'))
                    ->where('document_type', $documentType)
                    ->where('fiscal_year', $fiscalYear)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }

        // Re-lock and increment inside caller's transaction
        DB::table('number_series')
            ->where('id', $series->id)
            ->increment('current_number');

        /** @var NumberSeries $freshSeries */
        $freshSeries = NumberSeries::query()
            ->where('id', $series->id)
            ->lockForUpdate()
            ->firstOrFail();

        $formattedSeq = str_pad((string) $freshSeries->current_number, $freshSeries->padding, '0', STR_PAD_LEFT);

        return "{$freshSeries->prefix}-{$freshSeries->fiscal_year}-{$formattedSeq}";
    }
}
