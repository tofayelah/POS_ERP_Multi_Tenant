<?php

namespace App\Modules\Catalog\Providers;

use App\Core\Providers\AbstractModuleServiceProvider;

class CatalogServiceProvider extends AbstractModuleServiceProvider
{
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
