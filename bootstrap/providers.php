<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\HorizonServiceProvider::class,

    // Core Modular Architecture Providers
    App\Modules\Accounting\Providers\AccountingServiceProvider::class,
    App\Modules\Tax\Providers\TaxServiceProvider::class,
    App\Modules\Catalog\Providers\CatalogServiceProvider::class,
    App\Modules\Inventory\Providers\InventoryServiceProvider::class,
    App\Modules\Procurement\Providers\ProcurementServiceProvider::class,
    App\Modules\Sales\Providers\SalesServiceProvider::class,
    App\Modules\Pos\Providers\PosServiceProvider::class,
    App\Modules\Reporting\Providers\ReportingServiceProvider::class,
];
