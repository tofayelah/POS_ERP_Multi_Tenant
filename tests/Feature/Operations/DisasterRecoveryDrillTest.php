<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

test('dr:drill command runs automated restoration and verification drill with zero drift', function () {
    $exitCode = Artisan::call('dr:drill', ['--json' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0);

    $json = json_decode($output, true);
    expect($json)->toBeArray()
        ->and($json)->toHaveKey('status', 'passed')
        ->and($json)->toHaveKey('recovery_time_seconds')
        ->and($json['recovery_time_seconds'])->toBeGreaterThan(0)
        ->and($json['recovery_time_seconds'])->toBeLessThan(1800) // Within 30min RTO target
        ->and($json['watchdog']['total_breaches'])->toBe(0)
        ->and($json['reconciliation']['discrepancies_count'])->toBe(0)
        ->and($json['reconciliation']['discrepancies'])->toBeEmpty();
});
