<?php

namespace App\Modules\Tax\Services;

use App\Models\TaxCode;
use App\Models\TaxCodeRate;
use App\Modules\Tax\Contracts\TaxRateResolverContract;
use App\Modules\Tax\DTOs\TaxRateResult;
use App\Modules\Tax\Exceptions\NoEffectiveTaxRateException;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class TaxRateResolver implements TaxRateResolverContract
{
    /**
     * Resolve the effective tax rate and supplementary duty for a tax code on a specified date.
     *
     * @param  TaxCode|string  $taxCode
     * @param  CarbonInterface|string  $date
     */
    public function resolve(TaxCode|string $taxCode, CarbonInterface|string $date): TaxRateResult
    {
        $taxCodeId = $taxCode instanceof TaxCode ? $taxCode->id : $taxCode;
        $formattedDate = $date instanceof CarbonInterface ? $date->toDateString() : Carbon::parse($date)->toDateString();

        /** @var TaxCodeRate|null $rate */
        $rate = TaxCodeRate::query()
            ->where('tax_code_id', $taxCodeId)
            ->where('effective_from', '<=', $formattedDate)
            ->where(function ($query) use ($formattedDate) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $formattedDate);
            })
            ->orderByDesc('effective_from')
            ->first();

        if (! $rate) {
            throw new NoEffectiveTaxRateException($taxCodeId, $formattedDate);
        }

        return new TaxRateResult(
            taxCodeId: $taxCodeId,
            vatRatePercent: (string) $rate->rate_percent,
            supplementaryDutyPercent: (string) $rate->supplementary_duty_percent,
            effectiveFrom: Carbon::parse($rate->effective_from)->toDateString(),
            effectiveTo: $rate->effective_to ? Carbon::parse($rate->effective_to)->toDateString() : null,
        );
    }
}
