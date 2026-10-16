<?php

declare(strict_types=1);

namespace Lavertia\Panels\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class ResolveLocale
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = ['en', 'ar', 'fr'];
        $candidate = $request->query('lang');

        if (is_string($candidate) && in_array($candidate, $supported, true)) {
            $request->session()->put('lavertia_locale', $candidate);
        }

        $locale = $request->session()->get('lavertia_locale', config('app.locale', 'en'));

        App::setLocale(is_string($locale) && in_array($locale, $supported, true) ? $locale : 'en');

        return $next($request);
    }
}
