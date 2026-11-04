<?php

namespace App\Providers;

use App\Core\Tenancy\TenantContext;
use App\Models\Tenant;
use Filament\Events\TenantSet;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        set_error_handler(function (int $severity, string $message): bool {
            if (str_contains($message, 'touch(): Utime failed')) {
                return true;
            }

            return false;
        }, E_WARNING);

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return Limit::perMinute(5)->by($email.$request->ip());
        });

        Event::listen(TenantSet::class, function (TenantSet $event): void {
            $tenant = $event->getTenant();
            if ($tenant instanceof Tenant) {
                TenantContext::set($tenant);
            }
        });
    }
}
