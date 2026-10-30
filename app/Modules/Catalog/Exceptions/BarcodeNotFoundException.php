<?php

namespace App\Modules\Catalog\Exceptions;

use RuntimeException;

class BarcodeNotFoundException extends RuntimeException
{
    public function __construct(string $barcode)
    {
        parent::__construct("No variant found for barcode [{$barcode}].");
    }
}
