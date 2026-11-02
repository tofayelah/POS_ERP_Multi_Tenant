<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use SplFileInfo;

class CheckMigrationExpandContractCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:check-expand-contract
                            {--path=database/migrations : Path to the migrations directory}
                            {--base= : Git base branch/ref to compare against (e.g. origin/main)}
                            {--diff : Inspect only git-modified or added migrations}
                            {--all : Force inspecting all migration files in the directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify that migrations adhere to §15.1 and ADR-010 zero-downtime expand-and-contract rules (no drop/rename in up())';

    /**
     * Forbidden method calls on Blueprint or Schema in up().
     *
     * @var array<string, string>
     */
    protected array $forbiddenBlueprintMethods = [
        'dropcolumn' => 'Dropping column ($table->dropColumn())',
        'dropcolumns' => 'Dropping columns ($table->dropColumns())',
        'renamecolumn' => 'Renaming column ($table->renameColumn())',
    ];

    /**
     * Forbidden static method calls on Schema in up().
     *
     * @var array<string, string>
     */
    protected array $forbiddenSchemaMethods = [
        'drop' => 'Dropping table (Schema::drop())',
        'dropifexists' => 'Dropping table if exists (Schema::dropIfExists())',
        'droptable' => 'Dropping table (Schema::dropTable())',
        'rename' => 'Renaming table (Schema::rename())',
    ];

    /**
     * Forbidden raw SQL regex patterns in up().
     *
     * @var array<string, string>
     */
    protected array $forbiddenSqlPatterns = [
        '/\bDROP\s+COLUMN\b/i' => 'Raw SQL: DROP COLUMN',
        '/\bDROP\s+TABLE\b/i' => 'Raw SQL: DROP TABLE',
        '/\bRENAME\s+COLUMN\b/i' => 'Raw SQL: RENAME COLUMN',
        '/\bRENAME\s+TO\b/i' => 'Raw SQL: RENAME TO',
        '/\bALTER\s+TABLE\s+[^;]+\bDROP\b/i' => 'Raw SQL: ALTER TABLE ... DROP',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = (string) $this->option('path');
        $base = (string) $this->option('base');
        $diffOnly = (bool) $this->option('diff') || ! empty($base);
        $forceAll = (bool) $this->option('all');

        $this->info('Evaluating migrations for §15.1 Expand-and-Contract compliance...');

        $files = $this->resolveMigrationFiles($path, $diffOnly && ! $forceAll, $base);

        if (empty($files)) {
            $this->info('No migration files found to inspect. Clean.');

            return self::SUCCESS;
        }

        $this->line(sprintf('Inspecting <comment>%d</comment> migration file(s)...', count($files)));

        $allViolations = [];

        foreach ($files as $file) {
            $violations = $this->inspectMigrationFile($file);
            foreach ($violations as $v) {
                $allViolations[] = $v;
            }
        }

        if (empty($allViolations)) {
            $this->info('✓ All migrations adhere to §15.1 and ADR-010 Expand-and-Contract rules. No destructive up() statements found.');

            return self::SUCCESS;
        }

        $this->error(sprintf(
            'FAIL: Found %d destructive migration operation(s) violating Section 15.1 (ADR-010):',
            count($allViolations)
        ));

        $this->table(
            ['File', 'Line', 'Violation Type', 'Snippet / Detail'],
            array_map(fn (array $v): array => [
                $v['file'],
                $v['line'],
                $v['type'],
                $v['snippet'],
            ], $allViolations)
        );

        $this->line('');
        $this->error('RULE (§15.1 & ADR-010): Destructive migrations (DROP COLUMN, RENAME COLUMN, DROP TABLE) are strictly forbidden in standard pull requests.');
        $this->line('To maintain zero-downtime rolling deploys:');
        $this->line('  1. Expand: Add new nullable columns or new tables in Step 1.');
        $this->line('  2. Transition: Dual-write to both old and new schema in Step 2.');
        $this->line('  3. Contract: Remove obsolete columns/tables in a dedicated post-verification maintenance PR.');

        return self::FAILURE;
    }

    /**
     * Resolve migration files to inspect based on diff mode or full directory scan.
     *
     * @return array<int, string>
     */
    protected function resolveMigrationFiles(string $dir, bool $diffOnly, string $baseRef): array
    {
        $fullPath = base_path($dir);

        if (! is_dir($fullPath)) {
            return [];
        }

        if ($diffOnly) {
            $diffFiles = $this->getGitChangedMigrations($dir, $baseRef);
            if ($diffFiles !== null) {
                return $diffFiles;
            }
        }

        /** @var array<int, string> $files */
        $files = glob($fullPath.'/*.php') ?: [];
        sort($files);

        return $files;
    }

    /**
     * Retrieve migration files changed in git diff if git is available.
     *
     * @return array<int, string>|null
     */
    protected function getGitChangedMigrations(string $dir, string $baseRef): ?array
    {
        $base = ! empty($baseRef) ? escapeshellarg($baseRef) : 'HEAD~1';
        $cmd = "git diff --name-only {$base}...HEAD -- {$dir} 2>/dev/null";

        $output = [];
        $exitCode = 0;
        @exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            return null;
        }

        $changed = [];
        foreach ($output as $relPath) {
            $trimmed = trim($relPath);
            if (! empty($trimmed) && str_ends_with($trimmed, '.php')) {
                $abs = base_path($trimmed);
                if (file_exists($abs)) {
                    $changed[] = $abs;
                }
            }
        }

        return $changed;
    }

    /**
     * Inspect a single migration PHP file for destructive operations inside up().
     *
     * @return array<int, array{file: string, line: int, type: string, snippet: string}>
     */
    public function inspectMigrationFile(string $filePath): array
    {
        $content = (string) file_get_contents($filePath);

        return $this->inspectMigrationCode($content, basename($filePath));
    }

    /**
     * Inspect PHP migration source code for destructive operations inside up().
     *
     * @return array<int, array{file: string, line: int, type: string, snippet: string}>
     */
    public function inspectMigrationCode(string $code, string $fileName = 'migration.php'): array
    {
        $tokens = token_get_all($code);
        $count = count($tokens);

        $inUpFunction = false;
        $braceLevel = 0;
        $upBraceLevel = null;
        $violations = [];

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                [$id, $text, $line] = $token;

                // Detect entry to function up(...)
                if ($id === T_FUNCTION) {
                    $j = $i + 1;
                    while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        $j++;
                    }
                    if ($j < $count && is_array($tokens[$j]) && strtolower($tokens[$j][1]) === 'up') {
                        $inUpFunction = true;
                    }
                }

                // Check operations only within up() body
                if ($upBraceLevel !== null) {
                    // Check method calls: ->dropColumn, ->renameColumn, etc.
                    if ($id === T_OBJECT_OPERATOR || (defined('T_NULLSAFE_OBJECT_OPERATOR') && $id === T_NULLSAFE_OBJECT_OPERATOR)) {
                        $next = $i + 1;
                        while ($next < $count && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
                            $next++;
                        }
                        if ($next < $count && is_array($tokens[$next]) && $tokens[$next][0] === T_STRING) {
                            $methodName = strtolower($tokens[$next][1]);
                            if (array_key_exists($methodName, $this->forbiddenBlueprintMethods)) {
                                $violations[] = [
                                    'file' => $fileName,
                                    'line' => $line,
                                    'type' => $this->forbiddenBlueprintMethods[$methodName],
                                    'snippet' => "->{$tokens[$next][1]}()",
                                ];
                            }
                        }
                    }

                    // Check static calls: Schema::drop, Schema::dropIfExists, Schema::rename
                    if ($id === T_DOUBLE_COLON) {
                        $prev = $i - 1;
                        while ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_WHITESPACE) {
                            $prev--;
                        }
                        $next = $i + 1;
                        while ($next < $count && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
                            $next++;
                        }
                        if ($prev >= 0 && is_array($tokens[$prev]) && strtolower($tokens[$prev][1]) === 'schema'
                            && $next < $count && is_array($tokens[$next]) && $tokens[$next][0] === T_STRING) {
                            $methodName = strtolower($tokens[$next][1]);
                            if (array_key_exists($methodName, $this->forbiddenSchemaMethods)) {
                                $violations[] = [
                                    'file' => $fileName,
                                    'line' => $line,
                                    'type' => $this->forbiddenSchemaMethods[$methodName],
                                    'snippet' => "Schema::{$tokens[$next][1]}()",
                                ];
                            }
                        }
                    }

                    // Check raw SQL queries in string literals: DROP COLUMN, DROP TABLE, etc.
                    if ($id === T_CONSTANT_ENCAPSED_STRING) {
                        $rawString = trim($text, "'\"");
                        foreach ($this->forbiddenSqlPatterns as $pattern => $description) {
                            if (preg_match($pattern, $rawString)) {
                                $violations[] = [
                                    'file' => $fileName,
                                    'line' => $line,
                                    'type' => $description,
                                    'snippet' => mb_substr($rawString, 0, 50).'...',
                                ];
                            }
                        }
                    }
                }
            } else {
                // Single character token '{', '}'
                if ($token === '{') {
                    $braceLevel++;
                    if ($inUpFunction && $upBraceLevel === null) {
                        $upBraceLevel = $braceLevel;
                        $inUpFunction = false;
                    }
                } elseif ($token === '}') {
                    if ($upBraceLevel !== null && $braceLevel === $upBraceLevel) {
                        $upBraceLevel = null;
                    }
                    $braceLevel--;
                }
            }
        }

        return $violations;
    }
}
