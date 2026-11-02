<?php

declare(strict_types=1);

use App\Console\Commands\CheckMigrationExpandContractCommand;
use Illuminate\Support\Facades\Artisan;

test('all existing repository migrations adhere to Section 15.1 and ADR-010 expand-and-contract rules', function () {
    $exitCode = Artisan::call('db:check-expand-contract', ['--all' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('All migrations adhere to §15.1 and ADR-010 Expand-and-Contract rules');
});

test('check-expand-contract flags dropColumn in up method', function () {
    $command = app(CheckMigrationExpandContractCommand::class);

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;

    return new class extends Migration {
        public function up(): void {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('obsolete_field');
            });
        }
    };
    PHP;

    $violations = $command->inspectMigrationCode($code, '2026_test_migration.php');

    expect($violations)->not->toBeEmpty()
        ->and($violations[0]['type'])->toContain('Dropping column')
        ->and($violations[0]['snippet'])->toBe('->dropColumn()');
});

test('check-expand-contract flags renameColumn in up method', function () {
    $command = app(CheckMigrationExpandContractCommand::class);

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;

    return new class extends Migration {
        public function up(): void {
            Schema::table('customers', function (Blueprint $table) {
                $table->renameColumn('old_name', 'new_name');
            });
        }
    };
    PHP;

    $violations = $command->inspectMigrationCode($code, '2026_rename_migration.php');

    expect($violations)->not->toBeEmpty()
        ->and($violations[0]['type'])->toContain('Renaming column')
        ->and($violations[0]['snippet'])->toBe('->renameColumn()');
});

test('check-expand-contract flags Schema::drop and Schema::dropIfExists in up method', function () {
    $command = app(CheckMigrationExpandContractCommand::class);

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void {
            Schema::dropIfExists('old_table');
            Schema::drop('deprecated_table');
        }
    };
    PHP;

    $violations = $command->inspectMigrationCode($code, '2026_drop_table_migration.php');

    expect($violations)->toHaveCount(2)
        ->and($violations[0]['snippet'])->toBe('Schema::dropIfExists()')
        ->and($violations[1]['snippet'])->toBe('Schema::drop()');
});

test('check-expand-contract flags raw SQL DROP TABLE or DROP COLUMN in up method', function () {
    $command = app(CheckMigrationExpandContractCommand::class);

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Support\Facades\DB;

    return new class extends Migration {
        public function up(): void {
            DB::statement('ALTER TABLE sales_invoices DROP COLUMN legacy_discount');
        }
    };
    PHP;

    $violations = $command->inspectMigrationCode($code, '2026_raw_sql_migration.php');

    expect($violations)->not->toBeEmpty()
        ->and($violations[0]['type'])->toContain('Raw SQL');
});

test('check-expand-contract permits dropColumn and dropIfExists in down method for rollbacks', function () {
    $command = app(CheckMigrationExpandContractCommand::class);

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('new_feature')->nullable();
            });
        }

        public function down(): void {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('new_feature');
            });
            Schema::dropIfExists('customers_temp');
        }
    };
    PHP;

    $violations = $command->inspectMigrationCode($code, '2026_safe_migration.php');

    expect($violations)->toBeEmpty();
});
