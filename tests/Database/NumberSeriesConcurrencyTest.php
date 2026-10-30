<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Tenant;
use App\Modules\Accounting\Contracts\NumberSeriesContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NumberSeriesConcurrencyTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected NumberSeriesContract $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Series Test Tenant',
            'subdomain' => 'series-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Series Company',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->service = app(NumberSeriesContract::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_sequential_drawing_formats_and_increments_correctly(): void
    {
        DB::beginTransaction();
        $num1 = $this->service->getNextNumber($this->company->id, null, 'journal', '2026-2027');
        $num2 = $this->service->getNextNumber($this->company->id, null, 'journal', '2026-2027');
        $num3 = $this->service->getNextNumber($this->company->id, null, 'journal', '2026-2027');
        DB::commit();

        $this->assertSame('JV-2026-2027-000001', $num1);
        $this->assertSame('JV-2026-2027-000002', $num2);
        $this->assertSame('JV-2026-2027-000003', $num3);
    }

    public function test_fiscal_year_rollover_resets_sequence(): void
    {
        DB::beginTransaction();
        $fy26 = $this->service->getNextNumber($this->company->id, null, 'journal', '2026-2027');
        $fy27 = $this->service->getNextNumber($this->company->id, null, 'journal', '2027-2028');
        DB::commit();

        $this->assertSame('JV-2026-2027-000001', $fy26);
        $this->assertSame('JV-2027-2028-000001', $fy27);
    }

    public function test_two_parallel_processes_drawing_500_numbers_each_produce_1000_distinct_gapless_numbers_acc_07(): void
    {
        $file1 = tempnam(sys_get_temp_dir(), 'series1_');
        $file2 = tempnam(sys_get_temp_dir(), 'series2_');

        $phpBinary = PHP_BINARY;
        $scriptPath = base_path('tests/Fixtures/draw_numbers.php');

        $cmd1 = escapeshellcmd("{$phpBinary} {$scriptPath} {$this->tenant->id} {$this->company->id} {$file1} 500");
        $cmd2 = escapeshellcmd("{$phpBinary} {$scriptPath} {$this->tenant->id} {$this->company->id} {$file2} 500");

        // Spawn both parallel processes
        $p1 = proc_open($cmd1, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes1);
        $p2 = proc_open($cmd2, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes2);

        $this->assertIsResource($p1);
        $this->assertIsResource($p2);

        $out1 = stream_get_contents($pipes1[1]);
        $err1 = stream_get_contents($pipes1[2]);
        fclose($pipes1[1]);
        fclose($pipes1[2]);
        $exit1 = proc_close($p1);

        $out2 = stream_get_contents($pipes2[1]);
        $err2 = stream_get_contents($pipes2[2]);
        fclose($pipes2[1]);
        fclose($pipes2[2]);
        $exit2 = proc_close($p2);

        $this->assertSame(0, $exit1, "Process 1 failed with error: {$err1} output: {$out1}");
        $this->assertSame(0, $exit2, "Process 2 failed with error: {$err2} output: {$out2}");

        $numbers1 = json_decode((string) file_get_contents((string) $file1), true) ?: [];
        $numbers2 = json_decode((string) file_get_contents((string) $file2), true) ?: [];

        @unlink((string) $file1);
        @unlink((string) $file2);

        $this->assertCount(500, $numbers1);
        $this->assertCount(500, $numbers2);

        $allNumbers = array_merge($numbers1, $numbers2);
        $this->assertCount(1000, $allNumbers);

        // 1. Assert exactly 1,000 distinct numbers (zero duplicates)
        $uniqueNumbers = array_unique($allNumbers);
        $this->assertCount(1000, $uniqueNumbers, 'Concurrency collision: duplicate numbers were generated!');

        // 2. Extract numeric sequences and assert contiguous range 1 to 1000 with zero gaps
        $sequences = array_map(function ($num) {
            preg_match('/(\d+)$/', (string) $num, $matches);

            return isset($matches[1]) ? (int) $matches[1] : 0;
        }, $allNumbers);

        sort($sequences);

        $expectedRange = range(1, 1000);
        $this->assertSame($expectedRange, $sequences, 'Number series contains gaps or missing sequences!');
    }
}
