<?php

namespace App\Core;

class HealthCheckResult
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public bool $isHealthy,
        public string $message = '',
        public array $details = []
    ) {}

    /**
     * @param array<string, mixed> $details
     */
    public static function healthy(string $message = 'Healthy', array $details = []): self
    {
        return new self(true, $message, $details);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function unhealthy(string $message = 'Unhealthy', array $details = []): self
    {
        return new self(false, $message, $details);
    }
}
