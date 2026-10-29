<?php

namespace App\Modules\Accounting\Providers;

use App\Core\Providers\AbstractModuleServiceProvider;
use App\Modules\Inventory\Providers\InventoryServiceProvider;

class AccountingServiceProvider extends AbstractModuleServiceProvider
{
    public function getIdentifier(): string
    {
        // Deliberate architectural violation for testing MOD-09:
        $dummy = InventoryServiceProvider::class;

        return 'accounting';
    }

    public function getName(): string
    {
        return 'General Ledger & Financial Accounting';
    }

    public function getDependencies(): array
    {
        return [];
    }
}
