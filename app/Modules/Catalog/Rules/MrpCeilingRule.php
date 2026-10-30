<?php

namespace App\Modules\Catalog\Rules;

use App\Models\ProductVariant;
use Brick\Math\BigDecimal;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MrpCeilingRule implements ValidationRule
{
    public function __construct(protected ProductVariant|string $variant) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $variant = $this->variant instanceof ProductVariant
            ? $this->variant
            : ProductVariant::query()->find($this->variant);

        if (! $variant) {
            return;
        }

        $price = BigDecimal::of((string) $value);
        $mrp = BigDecimal::of((string) $variant->mrp);

        if ($price->compareTo($mrp) > 0) {
            $fail("The {$attribute} cannot exceed the Maximum Retail Price (MRP) of {$mrp}.");
        }
    }
}
