<?php

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleMiddleware
{
    /**
     * Handle an incoming request and switch application locale.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $langQuery = $request->query('lang');
        $lang = is_string($langQuery) ? $langQuery : null;

        /** @var string|null $sessionLocale */
        $sessionLocale = $request->hasSession() ? $request->session()->get('locale') : null;

        $locale = $lang ?? $sessionLocale ?? (string) config('app.locale', 'en');

        if (in_array($locale, ['en', 'bn'], true)) {
            app()->setLocale($locale);
            if ($request->hasSession()) {
                $request->session()->put('locale', $locale);
            }
        }

        return $next($request);
    }
}
