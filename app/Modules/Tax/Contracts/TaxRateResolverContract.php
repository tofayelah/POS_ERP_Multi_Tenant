<?php

namespace App\Modules\Tax\Contracts;

use App\Models\TaxCode;
use App\Modules\Tax\DTOs\TaxRateResult;
use Carbon\CarbonInterface;

interface TaxRateResolverContract
{
    /**
     * Resolve the effective tax rate and supplementary duty for a tax code on a specified date.
     *
     * @param  TaxCode|string  $taxCode
     * @param  CarbonInterface|string  $date
     */
    public function resolve(TaxCode|string $taxCode, CarbonInterface|string $date): TaxRateResult;
}
