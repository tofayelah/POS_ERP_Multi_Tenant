<?php

namespace App\Modules\Sales\Providers;

use App\Core\Providers\AbstractModuleServiceProvider;
use App\Modules\Sales\Contracts\SalesDocumentChainContract;
use App\Modules\Sales\Services\SalesDocumentChainService;

class SalesServiceProvider extends AbstractModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->singleton(SalesDocumentChainContract::class, SalesDocumentChainService::class);
    }

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
