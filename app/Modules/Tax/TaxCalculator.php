<?php

namespace App\Modules\Tax;

use App\Core\ValueObjects\Money;
use App\Models\TaxCode;
use App\Modules\Tax\Contracts\TaxRateResolverContract;
use App\Modules\Tax\DTOs\BasketTaxResult;
use App\Modules\Tax\DTOs\CashRoundingResult;
use App\Modules\Tax\DTOs\TaxLineResult;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;

class TaxCalculator
{
    public function __construct(
        protected ?TaxRateResolverContract $rateResolver = null,
    ) {}

    /**
     * Calculate tax for a line item, resolving rates from TaxRateResolver if a tax code is given.
     */
    public function calculateLine(
        Money|string $price,
        string|int $quantity = 1,
        Money|string $discount = '0',
        string $pricingBasis = 'tax_inclusive',
        TaxCode|string|null $taxCode = null,
        CarbonInterface|string|null $date = null,
        ?string $vatRatePercent = null,
        ?string $supplementaryDutyPercent = null,
    ): TaxLineResult {
        $vatRate = $vatRatePercent ?? '0';
        $sdRate = $supplementaryDutyPercent ?? '0';

        if ($taxCode !== null && $this->rateResolver !== null) {
            $effectiveDate = $date ?? now()->toDateString();
            $resolvedRate = $this->rateResolver->resolve($taxCode, $effectiveDate);
            $vatRate = $resolvedRate->vatRatePercent;
            $sdRate = $resolvedRate->supplementaryDutyPercent;
        }

        $unitPriceMoney = Money::of($price);
        $totalPriceMoney = $unitPriceMoney->multiply($quantity);
        $discountMoney = Money::of($discount);
        $effectivePriceMoney = $totalPriceMoney->subtract($discountMoney);

        if ($pricingBasis === 'tax_exclusive') {
            return $this->calculateTaxExclusiveLine($effectivePriceMoney, $vatRate, $sdRate, $discountMoney);
        }

        return $this->calculateTaxInclusiveLine($effectivePriceMoney, $vatRate, $sdRate, $discountMoney);
    }

    /**
     * Calculate Net Price, Supplementary Duty, and VAT from tax-inclusive Gross MRP.
     * Formula from Design §11.4 & Bangladesh VAT Act 2012:
     *   Value with SD = round(Gross / (1 + VAT_Rate), 2)
     *   VAT Amount = Gross - Value with SD
     *   Net Price = round(Value with SD / (1 + SD_Rate), 2)
     *   SD Amount = Value with SD - Net Price
     */
    public function calculateTaxInclusiveLine(
        Money|string $gross,
        string $vatRate = '0',
        string $sdRate = '0',
        Money|string $discount = '0',
    ): TaxLineResult {
        $grossMoney = Money::of($gross);
        $discountMoney = Money::of($discount);
        $currency = $grossMoney->getCurrency();

        $vatRateBd = BigDecimal::of($vatRate);
        $sdRateBd = BigDecimal::of($sdRate);

        if ($grossMoney->getBigDecimal()->isZero()) {
            return new TaxLineResult(
                gross: $grossMoney,
                net: Money::zero($currency),
                supplementaryDuty: Money::zero($currency),
                vat: Money::zero($currency),
                total: Money::zero($currency),
                discount: $discountMoney,
                vatRatePercent: (string) $vatRateBd,
                supplementaryDutyPercent: (string) $sdRateBd,
                pricingBasis: 'tax_inclusive',
            );
        }

        // 1. Extract VAT from Gross
        $valWithSdMoney = $grossMoney;
        $vatMoney = Money::zero($currency);
        if (! $vatRateBd->isZero()) {
            $valWithSdMoney = $grossMoney->multiply(100)->divide($vatRateBd->plus(100), 2);
            $vatMoney = $grossMoney->subtract($valWithSdMoney);
        }

        // 2. Extract Supplementary Duty from Value with SD
        $netMoney = $valWithSdMoney;
        $sdMoney = Money::zero($currency);
        if (! $sdRateBd->isZero()) {
            $netMoney = $valWithSdMoney->multiply(100)->divide($sdRateBd->plus(100), 2);
            $sdMoney = $valWithSdMoney->subtract($netMoney);
        }

        return new TaxLineResult(
            gross: $grossMoney,
            net: $netMoney,
            supplementaryDuty: $sdMoney,
            vat: $vatMoney,
            total: $grossMoney,
            discount: $discountMoney,
            vatRatePercent: (string) $vatRateBd,
            supplementaryDutyPercent: (string) $sdRateBd,
            pricingBasis: 'tax_inclusive',
        );
    }

    /**
     * Calculate Supplementary Duty, VAT, and Gross Total for a tax-exclusive line.
     * Formula from Bangladesh VAT Act 2012:
     *   SD Amount = round(Net * SD_Rate, 2)
     *   Value for VAT = Net + SD Amount
     *   VAT Amount = round(Value for VAT * VAT_Rate, 2)
     *   Total Payable = Net + SD Amount + VAT Amount
     */
    public function calculateTaxExclusiveLine(
        Money|string $net,
        string $vatRate = '0',
        string $sdRate = '0',
        Money|string $discount = '0',
    ): TaxLineResult {
        $netMoney = Money::of($net);
        $discountMoney = Money::of($discount);
        $currency = $netMoney->getCurrency();

        $vatRateBd = BigDecimal::of($vatRate);
        $sdRateBd = BigDecimal::of($sdRate);

        // SD Amount = round(Net * (SD_Rate / 100), 2)
        $sdMoney = Money::zero($currency);
        if (! $sdRateBd->isZero()) {
            $sdMoney = $netMoney->multiply($sdRateBd)->divide(100, 2);
        }

        $valForVatMoney = $netMoney->add($sdMoney);

        // VAT Amount = round(ValueForVat * (VAT_Rate / 100), 2)
        $vatMoney = Money::zero($currency);
        if (! $vatRateBd->isZero()) {
            $vatMoney = $valForVatMoney->multiply($vatRateBd)->divide(100, 2);
        }

        $totalMoney = $valForVatMoney->add($vatMoney);

        return new TaxLineResult(
            gross: $totalMoney,
            net: $netMoney,
            supplementaryDuty: $sdMoney,
            vat: $vatMoney,
            total: $totalMoney,
            discount: $discountMoney,
            vatRatePercent: (string) $vatRateBd,
            supplementaryDutyPercent: (string) $sdRateBd,
            pricingBasis: 'tax_exclusive',
        );
    }

    /**
     * Calculate basket totals, supporting optional invoice-level discount proportional allocation.
     *
     * @param  array<int, array{gross?: Money|string, net?: Money|string, vat_rate?: string, sd_rate?: string, basis?: string}>  $lines
     */
    public function calculateBasket(array $lines, Money|string $invoiceDiscount = '0'): BasketTaxResult
    {
        $discountMoney = Money::of($invoiceDiscount);
        $currency = 'BDT';

        if (empty($lines)) {
            return new BasketTaxResult(
                gross: Money::zero($currency),
                net: Money::zero($currency),
                discount: Money::zero($currency),
                supplementaryDuty: Money::zero($currency),
                vat: Money::zero($currency),
                totalPayable: Money::zero($currency),
                lines: [],
            );
        }

        // Calculate initial line gross totals
        $lineGrosses = [];
        $totalGrossMoney = Money::zero($currency);

        foreach ($lines as $line) {
            $basis = $line['basis'] ?? 'tax_inclusive';
            $vatRate = $line['vat_rate'] ?? '0';
            $sdRate = $line['sd_rate'] ?? '0';

            if ($basis === 'tax_exclusive') {
                $lineRes = $this->calculateTaxExclusiveLine($line['net'] ?? '0', $vatRate, $sdRate);
            } else {
                $lineRes = $this->calculateTaxInclusiveLine($line['gross'] ?? '0', $vatRate, $sdRate);
            }

            $lineGrosses[] = $lineRes;
            $totalGrossMoney = $totalGrossMoney->add($lineRes->gross);
        }

        $currency = $totalGrossMoney->getCurrency();

        // If invoice discount > 0, distribute proportionally
        $calculatedLines = [];
        $totalNetMoney = Money::zero($currency);
        $totalSdMoney = Money::zero($currency);
        $totalVatMoney = Money::zero($currency);
        $allocatedDiscountSum = Money::zero($currency);

        $lineCount = count($lines);

        for ($i = 0; $i < $lineCount; $i++) {
            $line = $lines[$i];
            $initialLine = $lineGrosses[$i];
            $vatRate = $line['vat_rate'] ?? '0';
            $sdRate = $line['sd_rate'] ?? '0';
            $basis = $line['basis'] ?? 'tax_inclusive';

            $allocatedDiscount = Money::zero($currency);

            if (! $discountMoney->getBigDecimal()->isZero() && ! $totalGrossMoney->getBigDecimal()->isZero()) {
                if ($i === $lineCount - 1) {
                    $allocatedDiscount = $discountMoney->subtract($allocatedDiscountSum);
                } else {
                    $allocatedDiscount = $discountMoney->multiply($initialLine->gross->getBigDecimal())
                        ->divide($totalGrossMoney->getBigDecimal(), 2);
                    $allocatedDiscountSum = $allocatedDiscountSum->add($allocatedDiscount);
                }
            }

            $effectiveGross = $initialLine->gross->subtract($allocatedDiscount);

            if ($basis === 'tax_exclusive') {
                $res = $this->calculateTaxExclusiveLine($effectiveGross, $vatRate, $sdRate, $allocatedDiscount);
            } else {
                $res = $this->calculateTaxInclusiveLine($effectiveGross, $vatRate, $sdRate, $allocatedDiscount);
            }

            $calculatedLines[] = $res;
            $totalNetMoney = $totalNetMoney->add($res->net);
            $totalSdMoney = $totalSdMoney->add($res->supplementaryDuty);
            $totalVatMoney = $totalVatMoney->add($res->vat);
        }

        $totalPayable = $totalGrossMoney->subtract($discountMoney);

        return new BasketTaxResult(
            gross: $totalGrossMoney,
            net: $totalNetMoney,
            discount: $discountMoney,
            supplementaryDuty: $totalSdMoney,
            vat: $totalVatMoney,
            totalPayable: $totalPayable,
            lines: $calculatedLines,
        );
    }

    /**
     * Round cash tender to nearest integer Taka ৳1 (§11.4).
     */
    public function roundCash(Money|string $amount): CashRoundingResult
    {
        $money = Money::of($amount);
        $amountBd = $money->getBigDecimal();
        $roundedBd = $amountBd->toScale(0, RoundingMode::HalfUp)->toScale(2);

        $roundedMoney = Money::of((string) $roundedBd, $money->getCurrency());
        $adjustmentMoney = $roundedMoney->subtract($money);

        return new CashRoundingResult(
            roundedCash: $roundedMoney,
            adjustment: $adjustmentMoney,
        );
    }
}
