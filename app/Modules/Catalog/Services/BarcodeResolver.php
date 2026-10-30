<?php

namespace App\Modules\Catalog\Services;

use App\Models\VariantBarcode;
use App\Modules\Catalog\Contracts\BarcodeResolverContract;
use App\Modules\Catalog\DTOs\BarcodeResolution;
use App\Modules\Catalog\Exceptions\BarcodeNotFoundException;

class BarcodeResolver implements BarcodeResolverContract
{
    /**
     * Resolve a barcode to a product variant with its UOM and multiplier.
     */
    public function resolve(string $barcode, ?string $companyId = null): BarcodeResolution
    {
        $query = VariantBarcode::query()
            ->with(['variant.product', 'variant.taxCode'])
            ->where('barcode', $barcode);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        /** @var VariantBarcode|null $record */
        $record = $query->first();

        if (! $record || ! $record->variant) {
            throw new BarcodeNotFoundException($barcode);
        }

        return new BarcodeResolution(
            variant: $record->variant,
            barcode: $record->barcode,
            uom: $record->uom,
            multiplier: (string) $record->multiplier,
            isPrimary: (bool) $record->is_primary,
        );
    }
}
