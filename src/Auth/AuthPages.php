<?php

declare(strict_types=1);

namespace Lavertia\Panels\Auth;

use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Lavertia\Panels\Panel;
use Lavertia\Panels\PanelRegistry;

final class AuthPages
{
    /**
     * Central auth is independent of Panels, shells and tenant data.
     * Call from your host routes with the desired guest/auth middleware.
     *
     * @param  list<AuthRoute>  $routes
     */
    public static function central(array $routes, string $prefix = 'identity'): void
    {
        self::mount($routes, $prefix, 'central', null);
    }

    /**
     * The Panel supplies locale scope, but the auth route deliberately
     * does not inherit the Panel's default 'auth' middleware or sidebar.
     *
     * @param  list<AuthRoute>  $routes
     */
    public static function forPanel(string $panelId, array $routes): void
    {
        $registry = app(PanelRegistry::class);
        $panel = $registry->panel($panelId);

        if (! $panel instanceof Panel) {
            throw new InvalidArgumentException('Unknown Auth Panel.');
        }

        self::mount(
            $routes,
            $panel->configuration()['path'].'/auth',
            $panelId,
            $panelId,
        );
    }

    /**
     * @param  list<AuthRoute>  $routes
     */
    public static function mount(array $routes, string $prefix, string $scope, ?string $panelId): void
    {
        if (preg_match('#^[a-z][a-z0-9/_-]*$#D', $prefix) !== 1
            || str_contains($prefix, '//')
            || preg_match('/^[a-z][a-z0-9_-]*$/D', $scope) !== 1
            || ! array_is_list($routes) || $routes === []) {
            throw new InvalidArgumentException('Invalid Auth Page route group.');
        }

        $seen = [];

        foreach ($routes as $route) {
            if (! $route instanceof AuthRoute || isset($seen[$route->slug()])) {
                throw new InvalidArgumentException('Duplicate or invalid Auth Page route.');
            }

            $seen[$route->slug()] = true;
        }

        foreach ($routes as $route) {
            $middleware = $route->middlewares();

            if ($panelId !== null) {
                $middleware[] = ResolveAuthLocale::class;
            }

            $item = Route::middleware($middleware)
                ->get($prefix.'/'.$route->slug(), $route->pageClass())
                ->name('lavertia-auth.'.$scope.'.'.str_replace('/', '.', $route->slug()));

            if ($panelId !== null) {
                $item->defaults('lavertia_auth_panel', $panelId);
            }
        }

        Route::getRoutes()->refreshNameLookups();
    }
}
