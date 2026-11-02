<?php

namespace App\Modules\DataMigration\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantBarcode;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MasterDataImportService
{
    /**
     * Imports or validates products and variants.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{valid: bool, errors: array<int, array{row: int, field: string, message: string}>, processed: int}
     */
    public function importProducts(array $rows, string $companyId, bool $dryRun = false): array
    {
        $tenantId = TenantContext::id() ?? throw new RuntimeException('Tenant context must be set');
        $errors = [];

        // 1. Validation pass
        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;

            if (empty($row['code']) || ! is_string($row['code'])) {
                $errors[] = ['row' => $rowNum, 'field' => 'code', 'message' => 'Product code is required'];
            }

            if (empty($row['name_en']) || ! is_string($row['name_en'])) {
                $errors[] = ['row' => $rowNum, 'field' => 'name_en', 'message' => 'Product English name is required'];
            }

            if (empty($row['sku']) || ! is_string($row['sku'])) {
                $errors[] = ['row' => $rowNum, 'field' => 'sku', 'message' => 'Variant SKU is required'];
            }

            if (! isset($row['mrp']) || ! is_numeric($row['mrp']) || (float) $row['mrp'] < 0) {
                $errors[] = ['row' => $rowNum, 'field' => 'mrp', 'message' => 'MRP must be a non-negative number'];
            }

            if (isset($row['cost_price']) && (! is_numeric($row['cost_price']) || (float) $row['cost_price'] < 0)) {
                $errors[] = ['row' => $rowNum, 'field' => 'cost_price', 'message' => 'Cost price must be a non-negative number'];
            }
        }

        if ($dryRun || ! empty($errors)) {
            return [
                'valid' => empty($errors),
                'errors' => $errors,
                'processed' => 0,
            ];
        }

        // 2. Execution pass (Idempotent upsert)
        return DB::transaction(function () use ($rows, $tenantId, $companyId) {
            $processed = 0;

            foreach ($rows as $row) {
                $code = (string) $row['code'];
                $sku = (string) $row['sku'];
                $baseUom = (string) ($row['base_uom'] ?? 'Pcs');

                /** @var Product $product */
                $product = Product::firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'company_id' => $companyId,
                        'code' => $code,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'name_en' => (string) $row['name_en'],
                        'name_bn' => isset($row['name_bn']) ? (string) $row['name_bn'] : null,
                        'base_uom' => $baseUom,
                        'description' => isset($row['description']) ? (string) $row['description'] : null,
                        'is_active' => true,
                    ]
                );

                $mrp = number_format((float) $row['mrp'], 2, '.', '');
                $costPrice = isset($row['cost_price']) ? number_format((float) $row['cost_price'], 2, '.', '') : '0.00';

                /** @var ProductVariant|null $variant */
                $variant = ProductVariant::where('tenant_id', $tenantId)
                    ->where('company_id', $companyId)
                    ->where('sku', $sku)
                    ->first();

                if ($variant !== null) {
                    $variant->update([
                        'name_en' => (string) $row['name_en'],
                        'mrp' => $mrp,
                        'cost_price' => $costPrice,
                        'tax_code_id' => $row['tax_code_id'] ?? $variant->tax_code_id,
                    ]);
                } else {
                    $variant = ProductVariant::create([
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'company_id' => $companyId,
                        'product_id' => $product->id,
                        'tax_code_id' => $row['tax_code_id'] ?? null,
                        'sku' => $sku,
                        'name_en' => (string) $row['name_en'],
                        'name_bn' => isset($row['name_bn']) ? (string) $row['name_bn'] : null,
                        'base_uom' => $baseUom,
                        'mrp' => $mrp,
                        'cost_price' => $costPrice,
                        'is_active' => true,
                    ]);
                }

                if (! empty($row['barcode'])) {
                    VariantBarcode::firstOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'company_id' => $companyId,
                            'variant_id' => $variant->id,
                            'barcode' => (string) $row['barcode'],
                        ],
                        [
                            'id' => (string) Str::uuid(),
                            'uom' => $baseUom,
                            'multiplier' => '1.0000',
                            'is_primary' => true,
                        ]
                    );
                }

                $processed++;
            }

            return [
                'valid' => true,
                'errors' => [],
                'processed' => $processed,
            ];
        });
    }

    /**
     * Imports or validates customer master data.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{valid: bool, errors: array<int, array{row: int, field: string, message: string}>, processed: int}
     */
    public function importCustomers(array $rows, string $companyId, bool $dryRun = false): array
    {
        $tenantId = TenantContext::id() ?? throw new RuntimeException('Tenant context must be set');
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;

            if (empty($row['name']) || ! is_string($row['name'])) {
                $errors[] = ['row' => $rowNum, 'field' => 'name', 'message' => 'Customer name is required'];
            }

            if (isset($row['credit_limit']) && (! is_numeric($row['credit_limit']) || (float) $row['credit_limit'] < 0)) {
                $errors[] = ['row' => $rowNum, 'field' => 'credit_limit', 'message' => 'Credit limit must be a non-negative number'];
            }
        }

        if ($dryRun || ! empty($errors)) {
            return [
                'valid' => empty($errors),
                'errors' => $errors,
                'processed' => 0,
            ];
        }

        return DB::transaction(function () use ($rows, $tenantId, $companyId) {
            $processed = 0;

            foreach ($rows as $row) {
                Customer::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'company_id' => $companyId,
                        'name' => (string) $row['name'],
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'phone' => isset($row['phone']) ? (string) $row['phone'] : null,
                        'email' => isset($row['email']) ? (string) $row['email'] : null,
                        'customer_bin' => isset($row['customer_bin']) ? (string) $row['customer_bin'] : null,
                        'credit_limit' => isset($row['credit_limit']) ? number_format((float) $row['credit_limit'], 2, '.', '') : '0.00',
                        'is_active' => true,
                    ]
                );
                $processed++;
            }

            return [
                'valid' => true,
                'errors' => [],
                'processed' => $processed,
            ];
        });
    }

    /**
     * Imports opening balance sheet lines and posts a single balanced journal entry.
     *
     * @param  array<int, array{account_id: string, debit: string, credit: string, line_description?: string}>  $balances
     */
    public function importOpeningBalances(
        array $balances,
        string $companyId,
        string $periodId,
        string $userId
    ): JournalEntry {
        $tenantId = TenantContext::id() ?? throw new RuntimeException('Tenant context must be set');

        if (empty($balances)) {
            throw new RuntimeException('Opening balance sheet requires at least one balancing line.');
        }

        $totalDebits = BigDecimal::of('0.00');
        $totalCredits = BigDecimal::of('0.00');

        foreach ($balances as $line) {
            $debit = BigDecimal::of($line['debit']);
            $credit = BigDecimal::of($line['credit']);

            $totalDebits = $totalDebits->plus($debit);
            $totalCredits = $totalCredits->plus($credit);
        }

        if (! $totalDebits->isEqualTo($totalCredits)) {
            throw new RuntimeException(
                "Opening balances do not balance: debits {$totalDebits} != credits {$totalCredits}"
            );
        }

        return DB::transaction(function () use ($tenantId, $companyId, $periodId, $userId, $balances) {
            $entry = JournalEntry::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'period_id' => $periodId,
                'entry_number' => 'JV-OPEN-'.date('YmdHis'),
                'posting_date' => Carbon::now()->toDateString(),
                'business_date' => Carbon::now()->toDateString(),
                'reference_type' => 'OPENING_BALANCE',
                'narration' => 'Opening Balance Sheet Import',
                'is_posted' => false,
                'posted_by' => $userId,
            ]);

            foreach ($balances as $line) {
                $debit = number_format((float) $line['debit'], 2, '.', '');
                $credit = number_format((float) $line['credit'], 2, '.', '');

                JournalLine::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'company_id' => $companyId,
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'debit_amount' => $debit,
                    'credit_amount' => $credit,
                    'currency' => 'BDT',
                    'exchange_rate' => '1.000000',
                    'base_debit_amount' => $debit,
                    'base_credit_amount' => $credit,
                    'line_description' => $line['line_description'] ?? 'Opening balance import',
                ]);
            }

            // Post entry after lines are recorded to satisfy triggers
            $entry->update(['is_posted' => true]);

            return $entry;
        });
    }
}
