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

