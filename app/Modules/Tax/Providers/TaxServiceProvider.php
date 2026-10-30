<?php

namespace App\Modules\Tax\Providers;

use App\Core\Providers\AbstractModuleServiceProvider;
use App\Modules\Tax\Contracts\TaxRateResolverContract;
use App\Modules\Tax\Services\TaxRateResolver;

class TaxServiceProvider extends AbstractModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->singleton(TaxRateResolverContract::class, TaxRateResolver::class);
    }

    public function getIdentifier(): string
    {
        return 'tax';
    }

    public function getName(): string
    {
        return 'Tax & VAT Compliance';
    }

    public function getDependencies(): array
    {
        return ['accounting'];
    }
}
