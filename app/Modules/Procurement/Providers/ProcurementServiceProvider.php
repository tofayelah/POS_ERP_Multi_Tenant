<?php

namespace App\Modules\Procurement\Providers;

use App\Core\Providers\AbstractModuleServiceProvider;
use App\Modules\Procurement\Contracts\ProcurementDocumentChainContract;
use App\Modules\Procurement\Services\ProcurementDocumentChainService;

class ProcurementServiceProvider extends AbstractModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->singleton(ProcurementDocumentChainContract::class, ProcurementDocumentChainService::class);
    }

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
