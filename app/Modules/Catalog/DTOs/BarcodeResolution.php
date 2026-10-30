<?php

namespace App\Modules\Catalog\DTOs;

use App\Models\ProductVariant;

readonly class BarcodeResolution
{
    public function __construct(
        public ProductVariant $variant,
        public string $barcode,
        public string $uom,
        public string $multiplier,
        public bool $isPrimary,
    ) {}
}
