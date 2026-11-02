<?php

namespace App\Modules\DataMigration\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\VariantBarcode;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

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

    /**
     * Imports or validates supplier master data.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{valid: bool, errors: array<int, array{row: int, field: string, message: string}>, processed: int}
     */
    public function importSuppliers(array $rows, string $companyId, bool $dryRun = false): array
    {
        $tenantId = TenantContext::id() ?? throw new RuntimeException('Tenant context must be set');
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;

            if (empty($row['name']) || ! is_string($row['name'])) {
                $errors[] = ['row' => $rowNum, 'field' => 'name', 'message' => 'Supplier name is required'];
            }

            if (empty($row['code']) || ! is_string($row['code'])) {
                $errors[] = ['row' => $rowNum, 'field' => 'code', 'message' => 'Supplier code is required'];
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
                Supplier::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'company_id' => $companyId,
                        'code' => (string) $row['code'],
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'name' => (string) $row['name'],
                        'phone' => isset($row['phone']) ? (string) $row['phone'] : null,
                        'email' => isset($row['email']) ? (string) $row['email'] : null,
                        'supplier_bin' => isset($row['supplier_bin']) ? (string) $row['supplier_bin'] : null,
                        'credit_period_days' => isset($row['credit_period_days']) ? (int) $row['credit_period_days'] : 30,
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
     * Parses a CSV file into an array of associative row records.
     *
     * @return array<int, array<string, string>>
     */
    public function parseCsv(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new RuntimeException("Cannot open CSV file: {$filePath}");
        }

        $rows = [];
        $headers = null;

        while (($data = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $data);

                continue;
            }

            if (empty(array_filter($data, fn ($val) => $val !== null && trim((string) $val) !== ''))) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = isset($data[$index]) ? trim((string) $data[$index]) : '';
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Parses an OpenXML Excel (.xlsx) file into an array of associative row records.
     *
     * @return array<int, array<string, string>>
     */
    public function parseXlsx(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException("Failed to open XLSX archive: {$filePath}");
        }

        // 1. Read shared strings table
        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml !== false) {
            $xml = simplexml_load_string($sharedStringsXml);
            if ($xml) {
                foreach ($xml->si as $si) {
                    $sharedStrings[] = (string) ($si->t ?? (string) $si);
                }
            }
        }

        // 2. Read first worksheet
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException("Worksheet sheet1.xml not found in XLSX archive: {$filePath}");
        }

        $xml = simplexml_load_string($sheetXml);
        if (! $xml || ! isset($xml->sheetData->row)) {
            return [];
        }

        $rows = [];
        $headers = null;

        foreach ($xml->sheetData->row as $rowNode) {
            $rowData = [];
            foreach ($rowNode->c as $cell) {
                $val = (string) $cell->v;
                $type = (string) $cell['t'];

                if ($type === 's' && isset($sharedStrings[(int) $val])) {
                    $val = $sharedStrings[(int) $val];
                }
                $rowData[] = trim($val);
            }

            if (empty(array_filter($rowData, fn ($val) => trim($val) !== ''))) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $rowData);

                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $rowData[$index] ?? '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Parses an input file based on its file extension (.csv, .xlsx, .json).
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseFile(string $filePath): array
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($ext) {
            'xlsx' => $this->parseXlsx($filePath),
            'csv' => $this->parseCsv($filePath),
            'json' => json_decode((string) file_get_contents($filePath), true, 512, JSON_THROW_ON_ERROR),
            default => throw new RuntimeException("Unsupported file extension: .{$ext}. Supported formats: .csv, .xlsx, .json"),
        };
    }

    /**
     * Imports products from a file (CSV or XLSX) with dry-run support.
     *
     * @return array{valid: bool, errors: array<int, array{row: int, field: string, message: string}>, processed: int}
     */
    public function importProductsFromFile(string $filePath, string $companyId, bool $dryRun = false): array
    {
        $rows = $this->parseFile($filePath);

        return $this->importProducts($rows, $companyId, $dryRun);
    }

    /**
     * Imports customers from a file (CSV or XLSX) with dry-run support.
     *
     * @return array{valid: bool, errors: array<int, array{row: int, field: string, message: string}>, processed: int}
     */
    public function importCustomersFromFile(string $filePath, string $companyId, bool $dryRun = false): array
    {
        $rows = $this->parseFile($filePath);

        return $this->importCustomers($rows, $companyId, $dryRun);
    }

    /**
     * Imports suppliers from a file (CSV or XLSX) with dry-run support.
     *
     * @return array{valid: bool, errors: array<int, array{row: int, field: string, message: string}>, processed: int}
     */
    public function importSuppliersFromFile(string $filePath, string $companyId, bool $dryRun = false): array
    {
        $rows = $this->parseFile($filePath);

        return $this->importSuppliers($rows, $companyId, $dryRun);
    }
}
