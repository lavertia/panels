<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Lavertia\Panels\Panel;
use Lavertia\Panels\Pages\Page;
use Orchestra\Testbench\TestCase;

final class PanelRoutingTest extends TestCase
{
    public function test_panel_pages_are_registered_behind_web_and_auth_middleware(): void
    {
        Panel::make('portal')
            ->path('work/admin')
            ->pages([RoutingFixturePage::class])
            ->registerRoutes();

        // Routes registered after Testbench boots need their name lookup refreshed.
        Route::getRoutes()->refreshNameLookups();
        $route = Route::getRoutes()->getByName('lavertia.portal.overview');

        self::assertNotNull($route);
        self::assertSame('work/admin/overview', $route->uri());
        self::assertContains('GET', $route->methods());
        self::assertContains('web', $route->gatherMiddleware());
        self::assertContains('auth', $route->gatherMiddleware());
        self::assertSame(RoutingFixturePage::class, $route->getActionName());
    }

    public function test_duplicate_page_slugs_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate or unsafe page slug');

        Panel::make('portal')
            ->pages([RoutingFixturePage::class, DuplicateRoutingFixturePage::class])
            ->registerRoutes();
    }

    public function test_unsafe_page_slug_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Panel::make('portal')->pages([UnsafeRoutingFixturePage::class])->registerRoutes();
    }

    public function test_invalid_panel_paths_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Panel::make('portal')->path('../admin');
    }
}

final class RoutingFixturePage extends Page
{
    public static function slug(): string
    {
        return 'overview';
    }

    protected function components(): array
    {
        return [];
    }
}

final class DuplicateRoutingFixturePage extends Page
{
    public static function slug(): string
    {
        return 'overview';
    }

    protected function components(): array
    {
        return [];
    }
}

final class UnsafeRoutingFixturePage extends Page
{
    public static function slug(): string
    {
        return '../admin';
    }

    protected function components(): array
    {
        return [];
    }
}
