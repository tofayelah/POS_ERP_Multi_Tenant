<?php

namespace App\Modules\Pos\Providers;

use App\Core\Providers\AbstractModuleServiceProvider;
use App\Modules\Pos\Contracts\PosSyncServiceContract;
use App\Modules\Pos\Services\PosSyncService;

class PosServiceProvider extends AbstractModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->singleton(PosSyncServiceContract::class, PosSyncService::class);
    }

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
