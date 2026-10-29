<?php

namespace App\Core\Queue\Middleware;

use App\Core\Tenancy\TenantContext;
use Closure;

class TenantJobMiddleware
{
    /**
     * Process the queued job with restored tenant context and guarantee cleanup.
     *
     * @param  mixed  $job
     * @param  Closure(mixed): void  $next
     */
    public function handle(mixed $job, Closure $next): void
    {
        $tenantId = $job->tenantId ?? null;

        if ($tenantId) {
            TenantContext::setById($tenantId);
        } else {
            TenantContext::clear();
        }

        try {
            $next($job);
        } finally {
            TenantContext::clear();
        }
    }
}
