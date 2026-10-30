<?php

namespace App\Modules\Accounting\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\NumberSeries;
use App\Modules\Accounting\Contracts\NumberSeriesContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

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

            // Use insertOrIgnore (INSERT ... ON CONFLICT DO NOTHING) so PostgreSQL transaction is never aborted
            DB::table('number_series')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'document_type' => $documentType,
                'prefix' => $prefix,
                'fiscal_year' => $fiscalYear,
                'current_number' => 0,
                'padding' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

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

        // Increment inside caller's transaction while row is locked
        DB::table('number_series')
            ->where('id', $series->id)
            ->increment('current_number');

        $freshNumber = DB::table('number_series')
            ->where('id', $series->id)
            ->value('current_number');

        $formattedSeq = str_pad((string) $freshNumber, $series->padding, '0', STR_PAD_LEFT);

        return "{$series->prefix}-{$series->fiscal_year}-{$formattedSeq}";
    }
}
