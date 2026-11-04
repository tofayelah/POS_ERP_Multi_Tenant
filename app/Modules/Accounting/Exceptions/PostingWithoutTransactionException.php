<?php

namespace App\Modules\Accounting\Exceptions;

use RuntimeException;

class PostingWithoutTransactionException extends RuntimeException
{
    public function __construct(string $message = 'Accounting posting must occur within caller active database transaction (Design §5.4).')
    {
        parent::__construct($message);
    }
}
