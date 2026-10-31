<?php

namespace App\Modules\Inventory\Providers;

use App\Core\Providers\AbstractModuleServiceProvider;
use App\Modules\Inventory\Contracts\CostingServiceContract;
use App\Modules\Inventory\Contracts\StockCountServiceContract;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\Contracts\StockReservationServiceContract;
use App\Modules\Inventory\Contracts\StockTransferServiceContract;
use App\Modules\Inventory\Services\CostingService;
use App\Modules\Inventory\Services\StockCountService;
use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Inventory\Services\StockReservationService;
use App\Modules\Inventory\Services\StockTransferService;

class InventoryServiceProvider extends AbstractModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->singleton(StockMovementServiceContract::class, StockMovementService::class);
        $this->app->singleton(CostingServiceContract::class, CostingService::class);
        $this->app->singleton(StockReservationServiceContract::class, StockReservationService::class);
        $this->app->singleton(StockCountServiceContract::class, StockCountService::class);
        $this->app->singleton(StockTransferServiceContract::class, StockTransferService::class);
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
