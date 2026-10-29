<?php

namespace App\Modules\Pos\Providers;

use App\Core\Providers\AbstractModuleServiceProvider;

class PosServiceProvider extends AbstractModuleServiceProvider
{
    public function getIdentifier(): string
    {
        return 'pos';
    }

    public function getName(): string
    {
        return 'Offline-First Point of Sale';
    }

    public function getDependencies(): array
    {
        return ['sales', 'catalog', 'inventory', 'tax'];
    }
}
