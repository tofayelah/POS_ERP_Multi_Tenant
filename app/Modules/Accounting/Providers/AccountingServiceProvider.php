<?php

namespace App\Modules\Accounting\Providers;

use App\Core\Providers\AbstractModuleServiceProvider;
use App\Modules\Accounting\Contracts\AccountingPostingServiceContract;
use App\Modules\Accounting\Contracts\FinancialReportContract;
use App\Modules\Accounting\Contracts\NumberSeriesContract;
use App\Modules\Accounting\Services\AccountingPostingService;
use App\Modules\Accounting\Services\FinancialReportService;
use App\Modules\Accounting\Services\NumberSeriesService;
use App\Modules\Accounting\Subledger\Contracts\BankReconciliationContract;
use App\Modules\Accounting\Subledger\Contracts\CustomerCreditCheckerContract;
use App\Modules\Accounting\Subledger\Contracts\PaymentServiceContract;
use App\Modules\Accounting\Subledger\Contracts\SubledgerAgingContract;
use App\Modules\Accounting\Subledger\Contracts\SubledgerContract;
use App\Modules\Accounting\Subledger\Contracts\SubledgerReconciliationContract;
use App\Modules\Accounting\Subledger\Contracts\SupplierPaymentVoucherContract;
use App\Modules\Accounting\Subledger\Contracts\VdsCertificateContract;
use App\Modules\Accounting\Subledger\Services\BankReconciliationService;
use App\Modules\Accounting\Subledger\Services\CustomerCreditChecker;
use App\Modules\Accounting\Subledger\Services\PaymentService;
use App\Modules\Accounting\Subledger\Services\SubledgerAgingService;
use App\Modules\Accounting\Subledger\Services\SubledgerReconciliationService;
use App\Modules\Accounting\Subledger\Services\SubledgerService;
use App\Modules\Accounting\Subledger\Services\SupplierPaymentVoucherService;
use App\Modules\Accounting\Subledger\Services\VdsCertificateService;

class AccountingServiceProvider extends AbstractModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->singleton(NumberSeriesContract::class, NumberSeriesService::class);
        $this->app->singleton(AccountingPostingServiceContract::class, AccountingPostingService::class);
        $this->app->singleton(CustomerCreditCheckerContract::class, CustomerCreditChecker::class);
        $this->app->singleton(PaymentServiceContract::class, PaymentService::class);
        $this->app->singleton(SupplierPaymentVoucherContract::class, SupplierPaymentVoucherService::class);
        $this->app->singleton(VdsCertificateContract::class, VdsCertificateService::class);
        $this->app->singleton(SubledgerReconciliationContract::class, SubledgerReconciliationService::class);
        $this->app->singleton(BankReconciliationContract::class, BankReconciliationService::class);
        $this->app->singleton(SubledgerAgingContract::class, SubledgerAgingService::class);
        $this->app->singleton(SubledgerContract::class, SubledgerService::class);
        $this->app->singleton(FinancialReportContract::class, FinancialReportService::class);
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
