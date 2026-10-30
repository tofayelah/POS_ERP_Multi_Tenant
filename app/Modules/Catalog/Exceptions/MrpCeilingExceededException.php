<?php

namespace App\Modules\Catalog\Exceptions;

use DomainException;

class MrpCeilingExceededException extends DomainException
{
    public function __construct(string $price, string $mrp, ?string $sku = null)
    {
        $target = $sku ? "for variant [{$sku}]" : '';
        parent::__construct("Selling price [{$price}] cannot exceed the Maximum Retail Price (MRP) [{$mrp}] {$target}.");
    }
}
