<?php

namespace App\Modules\Sales\Providers;

use App\Core\Providers\AbstractModuleServiceProvider;

class SalesServiceProvider extends AbstractModuleServiceProvider
{
    public function getIdentifier(): string
    {
        return 'sales';
    }

    public function getName(): string
    {
        return 'Sales & Order Management';
    }

    public function getDependencies(): array
    {
        return ['accounting', 'catalog', 'tax'];
    }
}
