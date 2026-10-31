<?php

namespace App\Modules\Inventory\Providers;

use App\Core\Providers\AbstractModuleServiceProvider;
use App\Modules\Inventory\Contracts\CostingServiceContract;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\Services\CostingService;
use App\Modules\Inventory\Services\StockMovementService;

class InventoryServiceProvider extends AbstractModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->singleton(StockMovementServiceContract::class, StockMovementService::class);
        $this->app->singleton(CostingServiceContract::class, CostingService::class);
    }

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
