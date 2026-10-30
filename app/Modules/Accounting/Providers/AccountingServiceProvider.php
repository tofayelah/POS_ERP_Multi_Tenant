<?php

namespace App\Modules\Accounting\Providers;

use App\Core\Providers\AbstractModuleServiceProvider;
use App\Modules\Accounting\Contracts\NumberSeriesContract;
use App\Modules\Accounting\Services\NumberSeriesService;

class AccountingServiceProvider extends AbstractModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->singleton(NumberSeriesContract::class, NumberSeriesService::class);
    }

    public function getIdentifier(): string
    {
        return 'accounting';
    }

    public function getName(): string
    {
        return 'General Ledger & Financial Accounting';
    }

    public function getDependencies(): array
    {
        return [];
    }
}
