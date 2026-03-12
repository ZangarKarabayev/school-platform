<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const ALLOWED = ['ru', 'kk'];

    public function handle(Request $request, Closure $next): Response
    {
        $queryLocale = $request->query('lang');

        if (is_string($queryLocale) && in_array($queryLocale, self::ALLOWED, true)) {
            $request->session()->put('locale', $queryLocale);
        }

        $sessionLocale = $request->session()->get('locale');

        app()->setLocale(
            $this->resolveLocale(
                $sessionLocale,
                $request->user()?->preferred_locale,
                config('app.fallback_locale'),
                config('app.locale'),
            ),
        );

        return $next($request);
    }

    private function resolveLocale(mixed ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && in_array($candidate, self::ALLOWED, true)) {
                return $candidate;
            }
        }

        return 'ru';
    }
}
