<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Lavertia\Panels\Pages\Page;
use Lavertia\Panels\Panel;
use Lavertia\Panels\PanelRegistry;
use Lavertia\Panels\PanelsServiceProvider;
use Modules\Inventory\Lavertia\Pages\InventoryOverview;
use Orchestra\Testbench\TestCase;

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
            'reports' => [
                'path' => 'reports',
                'middleware' => ['web', 'auth'],
                'pages' => [],
                'discover_pages' => [
                    [
                        'in' => inventoryPagesPath(),
                        'for' => 'Modules\\\\Inventory\\\\Lavertia\\\\Pages',
                    ],
                ],
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
        self::assertCount(1, array_filter(
            $routes->getRoutes(),
            static fn ($route): bool => $route->getName() === 'lavertia.admin.inventory.overview',
        ));
        self::assertNull($routes->getByName('lavertia.admin.abstract-module-page'));
    }

    public function test_config_driven_discovery_supports_modular_pages_without_provider_registration(): void
    {
        Route::getRoutes()->refreshNameLookups();

        $route = Route::getRoutes()->getByName('lavertia.reports.inventory.overview');

        self::assertNotNull($route);
        self::assertSame('reports/inventory/overview', $route->uri());
        self::assertContains('auth', $route->gatherMiddleware());
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

        Route::getRoutes()->refreshNameLookups();
        self::assertNull(Route::getRoutes()->getByName('lavertia.optional.missing'));
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
