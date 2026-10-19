<?php

declare(strict_types=1);

namespace Modules\Inventory\Providers;

use Illuminate\Support\ServiceProvider;
use Lavertia\Panels\PanelRegistry;

final class InventoryServiceProvider extends ServiceProvider
{
    public function boot(PanelRegistry $panels): void
    {
        $panels->discoverPages(
            panelId: 'admin',
            in: dirname(__DIR__).'/Lavertia/Pages',
            for: 'Modules\\Inventory\\Lavertia\\Pages',
        );
    }
}
