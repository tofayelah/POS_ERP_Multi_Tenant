<?php

namespace App\Core\Notifications\Contracts;

use App\Core\Notifications\DTOs\SmsSendResult;

interface SmsProviderInterface
{
    /**
     * Dispatches an SMS to the target phone number.
     */
    public function send(string $to, string $message): SmsSendResult;
}
