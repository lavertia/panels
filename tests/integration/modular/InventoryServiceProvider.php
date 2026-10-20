<?php

declare(strict_types=1);

namespace Modules\Inventory\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Lavertia\Panels\Navigation\NavigationItem;
use Lavertia\Panels\PanelRegistry;
use Modules\Inventory\Models\StockItem;
use Modules\Inventory\Policies\StockItemPolicy;

final class InventoryServiceProvider extends ServiceProvider
{
    public function boot(PanelRegistry $panels): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations');
        Gate::policy(StockItem::class, StockItemPolicy::class);

        $panels->discoverResources(
            panelId: 'admin',
            in: dirname(__DIR__).'/Lavertia/Resources',
            for: 'Modules\\Inventory\\Lavertia\\Resources',
        );
        $panels->discoverPages(
            panelId: 'admin',
            in: dirname(__DIR__).'/Lavertia/Pages',
            for: 'Modules\\Inventory\\Lavertia\\Pages',
        );
        $panels->navigationItem(
            panelId: 'admin',
            item: NavigationItem::make('inventory/overview', 'Inventory')->group('Operations'),
        );
        $panels->navigationItem(
            panelId: 'inventory',
            item: NavigationItem::make('dashboard', 'Inventory dashboard'),
        );
        $panels->discoverWidgets(
            panelId: 'inventory',
            in: dirname(__DIR__).'/Lavertia/Widgets',
            for: 'Modules\\Inventory\\Lavertia\\Widgets',
        );
    }
}
