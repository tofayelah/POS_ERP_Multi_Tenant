<?php

use App\Modules\Accounting\Providers\AccountingServiceProvider;
use App\Modules\Catalog\Providers\CatalogServiceProvider;
use App\Modules\Inventory\Providers\InventoryServiceProvider;
use App\Modules\Pos\Providers\PosServiceProvider;
use App\Modules\Procurement\Providers\ProcurementServiceProvider;
use App\Modules\Reporting\Providers\ReportingServiceProvider;
use App\Modules\Sales\Providers\SalesServiceProvider;
use App\Modules\Tax\Providers\TaxServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\PlatformPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    PlatformPanelProvider::class,
    HorizonServiceProvider::class,

    // Core Modular Architecture Providers
    AccountingServiceProvider::class,
    TaxServiceProvider::class,
    CatalogServiceProvider::class,
    InventoryServiceProvider::class,
    ProcurementServiceProvider::class,
    SalesServiceProvider::class,
    PosServiceProvider::class,
    ReportingServiceProvider::class,
];
