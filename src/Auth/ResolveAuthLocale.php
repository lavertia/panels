<?php

declare(strict_types=1);

namespace Lavertia\Panels\Auth;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Lavertia\Panels\Localization\LocaleContext;
use Lavertia\Panels\PanelRegistry;
use Symfony\Component\HttpFoundation\Response;

final class ResolveAuthLocale
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $panelId = $request->route()?->defaults['lavertia_auth_panel'] ?? null;
        $registry = app(PanelRegistry::class);

        if (is_string($panelId) && $registry->hasPanel($panelId)) {
            $context = LocaleContext::resolve($request, $registry, $panelId, true);
            App::setLocale($context['locale']);
        }

        return $next($request);
    }
}
