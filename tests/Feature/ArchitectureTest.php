<?php

test('no debugging statements are left in application')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();

$modules = [
    'Accounting',
    'Tax',
    'Catalog',
    'Inventory',
    'Procurement',
    'Sales',
    'Pos',
    'Reporting',
];

foreach ($modules as $module) {
    $otherModules = array_diff($modules, [$module]);
    foreach ($otherModules as $other) {
        test("module {$module} may not access {$other} internals (MOD-09)")
            ->expect("App\\Modules\\{$module}")
            ->not->toUse([
                "App\\Modules\\{$other}\\Providers",
                "App\\Modules\\{$other}\\Models",
                "App\\Modules\\{$other}\\Services",
                "App\\Modules\\{$other}\\Actions",
                "App\\Modules\\{$other}\\Http",
                "App\\Modules\\{$other}\\Database",
            ]);
    }
}

test('application modules may not use pgsql_owner connection')
    ->expect('App\Modules')
    ->not->toUse('pgsql_owner');

test('no float appears in money or accounting code (ACC-05)', function () {
    $dirs = [
        app_path('Core/ValueObjects'),
        app_path('Modules/Accounting'),
    ];

    $files = [];
    foreach ($dirs as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    foreach ($files as $file) {
        $tokens = token_get_all(file_get_contents($file));
        foreach ($tokens as $token) {
            if (is_array($token)) {
                $tokenType = $token[0];
                $tokenText = $token[1];
                $tokenLine = $token[2];

                // 1. No float casts: (float), (double)
                if ($tokenType === T_DOUBLE_CAST) {
                    $this->fail("Forbidden float cast found in {$file}:{$tokenLine} (ACC-05)");
                }

                // 2. No floating point numeric literals (e.g. 10.50)
                if ($tokenType === T_DNUMBER) {
                    $this->fail("Forbidden float literal '{$tokenText}' found in {$file}:{$tokenLine} (ACC-05)");
                }

                // 3. No float typehint in signatures
                if ($tokenType === T_STRING && strtolower($tokenText) === 'float') {
                    $this->fail("Forbidden 'float' type keyword found in {$file}:{$tokenLine} (ACC-05)");
                }
            }
        }
    }

    expect(count($files))->toBeGreaterThan(0);
});
