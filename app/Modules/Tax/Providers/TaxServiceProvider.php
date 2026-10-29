<?php

namespace App\Modules\Tax\Providers;

use App\Core\Providers\AbstractModuleServiceProvider;

class TaxServiceProvider extends AbstractModuleServiceProvider
{
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
