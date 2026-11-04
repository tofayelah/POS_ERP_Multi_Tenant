<?php

namespace App\Modules\Reporting\Providers;

use App\Core\Providers\AbstractModuleServiceProvider;

class ReportingServiceProvider extends AbstractModuleServiceProvider
{
    public function getIdentifier(): string
    {
        return 'reporting';
    }

    public function getName(): string
    {
        return 'Executive BI & Reporting Views';
    }

    public function getDependencies(): array
    {
        return ['accounting', 'sales', 'inventory'];
    }
}
