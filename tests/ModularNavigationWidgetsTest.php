<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Lavertia\Panels\Navigation\NavigationItem;
use Lavertia\Panels\Pages\DashboardPage;
use Lavertia\Panels\PanelRegistry;
use Lavertia\Panels\PanelsServiceProvider;
use Lavertia\Panels\Widgets\DashboardWidget;
use Lavertia\Support\Components\Text;
use Modules\Inventory\Lavertia\Widgets\HiddenInventoryWidget;
use Modules\Inventory\Lavertia\Widgets\InventoryMetrics;
use Orchestra\Testbench\TestCase;

final class ModularNavigationWidgetsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        // Module boots AFTER Panels (also supported for Pages in v0.2).
        return [PanelsServiceProvider::class, FixtureNavigationModuleProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('lavertia.panels', [
            'admin' => [
                'path' => 'admin',
                'middleware' => ['web', 'auth'],
                'pages' => [DashboardPage::class],
                'navigation' => [
                    ['slug' => 'dashboard', 'label' => 'Dashboard', 'sort' => 0],
                ],
                'dashboard_widgets' => [InventoryMetrics::class],
                'discover_widgets' => [
                    [
                        'in' => inventoryWidgetPath(),
                        'for' => 'Modules\\Inventory\\Lavertia\\Widgets',
                    ],
                ],
            ],
            'branch' => [
                'path' => 'branch',
                'middleware' => ['web', 'auth'],
                'pages' => [DashboardPage::class],
                'navigation' => [
                    ['slug' => 'dashboard', 'label' => 'Branch dashboard'],
                ],
                'dashboard_widgets' => [BranchDashboardWidget::class],
            ],
        ]);
    }

    public function test_navigation_resolves_real_panel_routes_and_is_deterministic(): void
    {
        $registry = $this->app->make(PanelRegistry::class);
        Route::getRoutes()->refreshNameLookups();

        $items = $registry->navigation('admin', 'lavertia.admin.inventory.overview');

        self::assertCount(2, $items);
        self::assertSame('Dashboard', $items[0]['label']);
        self::assertSame('/admin/dashboard', $items[0]['url']);
        self::assertFalse($items[0]['active']);
        self::assertSame('Inventory', $items[1]['label']);
        self::assertSame('Operations', $items[1]['group']);
        self::assertSame('/admin/inventory/overview', $items[1]['url']);
        self::assertTrue($items[1]['active']);
        self::assertSame($items, $registry->navigation('admin', 'lavertia.admin.inventory.overview'));
    }

    public function test_navigation_never_exposes_other_panels_or_unregistered_pages(): void
    {
        $registry = $this->app->make(PanelRegistry::class);
        Route::getRoutes()->refreshNameLookups();

        self::assertSame(['Branch dashboard'], array_column($registry->navigation('branch'), 'label'));
        self::assertSame([], $registry->navigation('not-installed'));

        $registry->navigationItem('admin', NavigationItem::make('missing', 'Broken'));

        self::assertSame(
            ['Dashboard', 'Inventory'],
            array_column($registry->navigation('admin'), 'label'),
        );
    }

    public function test_gate_protected_navigation_is_hidden_until_authorized(): void
    {
        $registry = $this->app->make(PanelRegistry::class);
        Route::getRoutes()->refreshNameLookups();

        $registry->navigationItem(
            'admin',
            NavigationItem::make('inventory/reports/daily-stock', 'Reports')
                ->group('Operations')
                ->ability('inventory.reports.view'),
        );

        Gate::define('inventory.reports.view', static fn (GenericUser $user): bool => (int) $user->getAuthIdentifier() === 7);

        self::assertNotContains('Reports', array_column($registry->navigation('admin'), 'label'));

        $this->be(new GenericUser(['id' => 7, 'name' => 'Authorized']));

        self::assertContains('Reports', array_column($registry->navigation('admin'), 'label'));

        $this->be(new GenericUser(['id' => 8, 'name' => 'Unauthorized']));

        self::assertNotContains('Reports', array_column($registry->navigation('admin'), 'label'));
    }

    public function test_module_widgets_are_deduplicated_sorted_and_filtered_by_visibility(): void
    {
        $registry = $this->app->make(PanelRegistry::class);

        self::assertSame(
            [HiddenInventoryWidget::class, InventoryMetrics::class],
            $registry->widgetClasses('admin'),
        );

        $components = $registry->dashboardComponents('admin');
        self::assertCount(1, $components);
        self::assertSame('stats-grid', $components[0]->getType());

        $branch = $registry->dashboardComponents('branch');
        self::assertCount(1, $branch);
        self::assertSame('text', $branch[0]->getType());
        self::assertSame([], $registry->dashboardComponents('missing'));
    }

    public function test_dashboard_page_uses_only_its_active_panel_widgets(): void
    {
        Route::getRoutes()->refreshNameLookups();

        $request = Request::create('/admin/dashboard', 'GET');
        $request->setRouteResolver(static fn () => Route::getRoutes()->getByName('lavertia.admin.dashboard'));
        $this->app->instance('request', $request);

        $admin = (new DashboardPage)->tree();
        self::assertSame('stats-grid', $admin['components'][0]['type']);

        $request = Request::create('/branch/dashboard', 'GET');
        $request->setRouteResolver(static fn () => Route::getRoutes()->getByName('lavertia.branch.dashboard'));
        $this->app->instance('request', $request);

        $branch = (new DashboardPage)->tree();
        self::assertSame('text', $branch['components'][0]['type']);
    }

    public function test_rejects_cross_module_conflicting_navigation_slugs(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->app->make(PanelRegistry::class)
            ->navigationItem('admin', NavigationItem::make('dashboard', 'Override'));
    }

    public function test_rejects_non_widget_registration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->app->make(PanelRegistry::class)->dashboardWidget('admin', \stdClass::class);
    }

    public function test_rejects_navigation_config_with_objects_instead_of_cache_safe_arrays(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NavigationItem::fromArray(['slug' => 'dashboard', 'label' => 'Dashboard', 'sort' => 'first']);
    }

    public function test_rejects_unsafe_navigation_paths(): void
    {
        $this->expectException(InvalidArgumentException::class);
        NavigationItem::make('../private', 'Invalid');
    }
}

final class FixtureNavigationModuleProvider extends ServiceProvider
{
    public function boot(PanelRegistry $panels): void
    {
        $panels->discoverPages(
            panelId: 'admin',
            in: __DIR__.'/Fixtures/app-modules/inventory/src/Lavertia/Pages',
            for: 'Modules\\Inventory\\Lavertia\\Pages',
        );
        $panels->discoverWidgets(
            panelId: 'admin',
            in: inventoryWidgetPath(),
            for: 'Modules\\Inventory\\Lavertia\\Widgets',
        );
        $panels->navigationItem(
            panelId: 'admin',
            item: NavigationItem::make('inventory/overview', 'Inventory')->group('Operations')->sort(20),
        );
        // Provider retries are idempotent.
        $panels->navigationItem(
            panelId: 'admin',
            item: NavigationItem::make('inventory/overview', 'Inventory')->group('Operations')->sort(20),
        );
    }
}

final class BranchDashboardWidget extends DashboardWidget
{
    protected function components(): array
    {
        return [Text::make('Branch only')];
    }
}

function inventoryWidgetPath(): string
{
    return __DIR__.'/Fixtures/app-modules/inventory/src/Lavertia/Widgets';
}
