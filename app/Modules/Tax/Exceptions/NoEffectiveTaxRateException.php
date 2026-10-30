<?php

namespace App\Modules\Tax\Exceptions;

use RuntimeException;

class NoEffectiveTaxRateException extends RuntimeException
{
    public function __construct(string $taxCodeId, string $date)
    {
        parent::__construct("No effective tax rate found for tax code [{$taxCodeId}] on date [{$date}].");
    }
}
