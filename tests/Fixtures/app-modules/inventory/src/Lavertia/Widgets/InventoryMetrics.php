<?php

declare(strict_types=1);

namespace Modules\Inventory\Lavertia\Widgets;

use Lavertia\Panels\Components\Stat;
use Lavertia\Panels\Components\StatsGrid;
use Lavertia\Panels\Widgets\DashboardWidget;
use Lavertia\Support\Contracts\Component;

final class InventoryMetrics extends DashboardWidget
{
    public static function sort(): int
    {
        return 5;
    }

    /** @return list<Component> */
    protected function components(): array
    {
        return [StatsGrid::make([Stat::make('Inventory items', 12)->tone('success')])];
    }
}
