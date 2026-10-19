<?php

declare(strict_types=1);

namespace Lavertia\Panels\Pages;

use Lavertia\Panels\PanelRegistry;
use Lavertia\Support\Contracts\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Add this Page to a Panel's configured pages to show registered module
 * DashboardWidgets at /{panel}/dashboard. No cross-Panel widget fallback.
 */
final class DashboardPage extends Page
{
    public static function slug(): string
    {
        return 'dashboard';
    }

    public static function title(): string
    {
        return 'Dashboard';
    }

    /** @return list<Component> */
    protected function components(): array
    {
        $name = request()->route()?->getName();

        if (! is_string($name)
            || preg_match('/^lavertia\.([a-z][a-z0-9_-]*)\.dashboard$/D', $name, $matches) !== 1) {
            throw new NotFoundHttpException;
        }

        return app(PanelRegistry::class)->dashboardComponents($matches[1]);
    }
}
