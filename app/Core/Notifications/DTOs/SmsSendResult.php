<?php

namespace App\Core\Notifications\DTOs;

class SmsSendResult
{
    public function __construct(
        public bool $success,
        public string $provider,
        public ?string $messageId = null,
        public ?string $errorMessage = null,
    ) {}
}
