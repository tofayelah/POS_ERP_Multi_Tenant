<?php

namespace App\Modules\Inventory\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(string $message = 'Insufficient warehouse stock for the requested transaction.')
    {
        parent::__construct($message);
    }
}
