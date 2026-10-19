<?php

declare(strict_types=1);

namespace Modules\Inventory\Providers;

use Illuminate\Support\ServiceProvider;
use Lavertia\Panels\PanelRegistry;
use Lavertia\Panels\Navigation\NavigationItem;

final class InventoryServiceProvider extends ServiceProvider
{
    public function boot(PanelRegistry $panels): void
    {
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
