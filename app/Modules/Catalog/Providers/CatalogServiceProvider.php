<?php

namespace App\Modules\Catalog\Providers;

use App\Core\Providers\AbstractModuleServiceProvider;
use App\Modules\Catalog\Contracts\BarcodeResolverContract;
use App\Modules\Catalog\Services\BarcodeResolver;

class CatalogServiceProvider extends AbstractModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->singleton(BarcodeResolverContract::class, BarcodeResolver::class);
    }

    public function getIdentifier(): string
    {
        return 'catalog';
    }

    public function getName(): string
    {
        return 'Product & Service Catalog';
    }

    public function getDependencies(): array
    {
        return [];
    }
}
