<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const SUPPORTED_LOCALES = ['es', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        app()->setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        // 1. Authenticated user's saved preference
        if ($request->user() && in_array($request->user()->locale, self::SUPPORTED_LOCALES)) {
            return $request->user()->locale;
        }

        // 2. Browser's Accept-Language header
        $preferred = $request->getPreferredLanguage(self::SUPPORTED_LOCALES);

        if ($preferred) {
            return $preferred;
        }

        // 3. Fall back to app default
        return config('app.locale');
    }
}
