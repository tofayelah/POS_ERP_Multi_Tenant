<?php

namespace App\Modules\Procurement\Providers;

use App\Core\Providers\AbstractModuleServiceProvider;

class ProcurementServiceProvider extends AbstractModuleServiceProvider
{
    public function getIdentifier(): string
    {
        return 'procurement';
    }

    public function getName(): string
    {
        return 'Purchasing & Supplier Procurement';
    }

    public function getDependencies(): array
    {
        return ['accounting', 'catalog', 'inventory'];
    }
}
