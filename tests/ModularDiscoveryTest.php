<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Lavertia\Panels\Pages\Page;
use Lavertia\Panels\Panel;
use Lavertia\Panels\PanelRegistry;
use Lavertia\Panels\PanelsServiceProvider;
use Modules\Inventory\Lavertia\Pages\InventoryOverview;
use Orchestra\Testbench\TestCase;

require_once __DIR__.'/Fixtures/app-modules/inventory/src/Lavertia/Pages/InventoryOverview.php';
require_once __DIR__.'/Fixtures/app-modules/inventory/src/Lavertia/Pages/Reports/DailyStock.php';
require_once __DIR__.'/Fixtures/app-modules/inventory/src/Lavertia/Pages/AbstractModulePage.php';

final class ModularDiscoveryTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        // Intentionally boot the module *after* Panels to verify deferral.
        return [PanelsServiceProvider::class, FixtureInventoryModuleProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('lavertia.panels', [
            'admin' => [
                'path' => 'admin',
                'middleware' => ['web', 'auth'],
                'pages' => [ExplicitAdminPage::class],
            ],
        ]);
    }

    public function test_module_provider_discovers_nested_pages_with_original_middleware(): void
    {
        $routes = Route::getRoutes();
        $routes->refreshNameLookups();

        $overview = $routes->getByName('lavertia.admin.inventory.overview');
        $dailyStock = $routes->getByName('lavertia.admin.inventory.reports.daily-stock');
        $explicit = $routes->getByName('lavertia.admin.dashboard');

        self::assertNotNull($overview);
        self::assertNotNull($dailyStock);
        self::assertNotNull($explicit);
        self::assertSame('admin/inventory/overview', $overview->uri());
        self::assertSame('admin/inventory/reports/daily-stock', $dailyStock->uri());
        self::assertSame(InventoryOverview::class, $overview->getActionName());
        self::assertContains('web', $overview->gatherMiddleware());
        self::assertContains('auth', $overview->gatherMiddleware());
        self::assertSame(1, $routes->getRoutesByName()['lavertia.admin.inventory.overview'] === $overview ? 1 : 0);
        self::assertNull($routes->getByName('lavertia.admin.abstract-module-page'));
    }

    public function test_plain_panel_can_discover_module_pages_without_the_registry(): void
    {
        Panel::make('stock')
            ->path('stock')
            ->discoverPages(
                in: inventoryPagesPath(),
                for: 'Modules\\Inventory\\Lavertia\\Pages',
            )
            ->registerRoutes();

        Route::getRoutes()->refreshNameLookups();

        self::assertSame(
            'stock/inventory/overview',
            Route::getRoutes()->getByName('lavertia.stock.inventory.overview')?->uri(),
        );
    }

    public function test_missing_optional_module_directory_is_safe(): void
    {
        Panel::make('optional')
            ->discoverPages(
                in: inventoryPagesPath().'/not-installed',
                for: 'Modules\\Missing\\Lavertia\\Pages',
            )
            ->registerRoutes();

        self::assertTrue(true);
    }

    public function test_rejects_invalid_psr4_namespace(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Panel::make('admin')->discoverPages(
            in: inventoryPagesPath(),
            for: '../Modules/Inventory',
        );
    }

    public function test_duplicate_explicit_and_module_page_is_deduplicated(): void
    {
        Panel::make('duplicate')
            ->pages([InventoryOverview::class])
            ->discoverPages(
                in: inventoryPagesPath(),
                for: 'Modules\\Inventory\\Lavertia\\Pages',
            )
            ->registerRoutes();

        Route::getRoutes()->refreshNameLookups();

        self::assertNotNull(Route::getRoutes()->getByName('lavertia.duplicate.inventory.overview'));
    }
}

final class FixtureInventoryModuleProvider extends ServiceProvider
{
    public function boot(PanelRegistry $panels): void
    {
        $panels->discoverPages(
            panelId: 'admin',
            in: inventoryPagesPath(),
            for: 'Modules\\Inventory\\Lavertia\\Pages',
        );

        // Repeated module/provider registration must remain idempotent.
        $panels->discoverPages(
            panelId: 'admin',
            in: inventoryPagesPath(),
            for: 'Modules\\Inventory\\Lavertia\\Pages',
        );
    }
}

final class ExplicitAdminPage extends Page
{
    public static function slug(): string
    {
        return 'dashboard';
    }

    protected function components(): array
    {
        return [];
    }
}

function inventoryPagesPath(): string
{
    return __DIR__.'/Fixtures/app-modules/inventory/src/Lavertia/Pages';
}
