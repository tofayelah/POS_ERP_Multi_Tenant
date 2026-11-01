<?php

namespace App\Modules\Accounting\Providers;

use App\Core\Providers\AbstractModuleServiceProvider;
use App\Modules\Accounting\Contracts\AccountingPostingServiceContract;
use App\Modules\Accounting\Contracts\NumberSeriesContract;
use App\Modules\Accounting\Services\AccountingPostingService;
use App\Modules\Accounting\Services\NumberSeriesService;
use App\Modules\Accounting\Subledger\Contracts\CustomerCreditCheckerContract;
use App\Modules\Accounting\Subledger\Contracts\PaymentServiceContract;
use App\Modules\Accounting\Subledger\Services\CustomerCreditChecker;
use App\Modules\Accounting\Subledger\Services\PaymentService;

class AccountingServiceProvider extends AbstractModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->singleton(NumberSeriesContract::class, NumberSeriesService::class);
        $this->app->singleton(AccountingPostingServiceContract::class, AccountingPostingService::class);
        $this->app->singleton(CustomerCreditCheckerContract::class, CustomerCreditChecker::class);
        $this->app->singleton(PaymentServiceContract::class, PaymentService::class);
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
