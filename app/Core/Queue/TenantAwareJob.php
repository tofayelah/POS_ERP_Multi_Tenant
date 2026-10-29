<?php

namespace App\Core\Queue;

use App\Core\Queue\Middleware\TenantJobMiddleware;
use App\Core\Tenancy\TenantContext;

/**
 * @phpstan-ignore trait.unused
 */
trait TenantAwareJob
{
    /**
     * The serialized tenant ID for this job.
     */
    public ?string $tenantId = null;

    /**
     * Initialize the tenant context from the current active tenant when dispatched.
     */
    public function initializeTenantAwareJob(): void
    {
        if ($this->tenantId === null) {
            $this->tenantId = TenantContext::id();
        }
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new TenantJobMiddleware];
    }
}
