<?php

namespace App\Modules\Accounting\Providers;

use App\Core\Providers\AbstractModuleServiceProvider;

class AccountingServiceProvider extends AbstractModuleServiceProvider
{
    public function getIdentifier(): string
    {
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
