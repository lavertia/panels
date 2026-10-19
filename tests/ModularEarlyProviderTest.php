<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Lavertia\Panels\PanelRegistry;
use Lavertia\Panels\PanelsServiceProvider;
use Orchestra\Testbench\TestCase;

final class ModularEarlyProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        // In Composer modular applications the provider order is not guaranteed.
        return [FixtureEarlyInventoryModuleProvider::class, PanelsServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('lavertia.panels', [
            'admin' => [
                'path' => 'admin',
                'middleware' => ['web', 'auth'],
                'pages' => [],
            ],
        ]);
    }

    public function test_module_booting_before_panels_can_register_lavertia_pages(): void
    {
        Route::getRoutes()->refreshNameLookups();

        $route = Route::getRoutes()->getByName('lavertia.admin.inventory.overview');

        self::assertNotNull($route);
        self::assertSame('admin/inventory/overview', $route->uri());
        self::assertContains('auth', $route->gatherMiddleware());
    }
}

final class FixtureEarlyInventoryModuleProvider extends ServiceProvider
{
    public function boot(PanelRegistry $panels): void
    {
        $panels->discoverPages(
            panelId: 'admin',
            in: __DIR__.'/Fixtures/app-modules/inventory/src/Lavertia/Pages',
            for: 'Modules\\Inventory\\Lavertia\\Pages',
        );
    }
}
