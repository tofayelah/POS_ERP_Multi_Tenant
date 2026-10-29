<?php

namespace App\Modules\Inventory\Providers;

use App\Core\Providers\AbstractModuleServiceProvider;

class InventoryServiceProvider extends AbstractModuleServiceProvider
{
    public function getIdentifier(): string
    {
        return 'inventory';
    }

    public function getName(): string
    {
        return 'Multi-Warehouse Inventory & Costing';
    }

    public function getDependencies(): array
    {
        return ['accounting', 'catalog'];
    }
}
