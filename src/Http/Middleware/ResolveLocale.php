<?php

declare(strict_types=1);

namespace Lavertia\Panels\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Lavertia\Panels\Localization\LocaleContext;
use Lavertia\Panels\PanelRegistry;
use Symfony\Component\HttpFoundation\Response;

final class ResolveLocale
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $registry = app(PanelRegistry::class);
        $routeName = $request->route()?->getName();
        $panelId = $registry->panelForRoute($routeName);

        if ($panelId !== null) {
            $context = LocaleContext::resolve($request, $registry, $panelId, true);
            App::setLocale($context['locale']);
        }

        return $next($request);
    }
}
