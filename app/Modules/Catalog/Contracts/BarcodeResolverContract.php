<?php

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\DTOs\BarcodeResolution;

interface BarcodeResolverContract
{
    /**
     * Resolve a barcode to a product variant with its UOM and multiplier.
     */
    public function resolve(string $barcode, ?string $companyId = null): BarcodeResolution;
}
