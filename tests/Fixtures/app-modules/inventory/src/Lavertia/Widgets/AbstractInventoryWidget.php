<?php

declare(strict_types=1);

namespace Modules\Inventory\Lavertia\Widgets;

use Lavertia\Panels\Widgets\DashboardWidget;
use Lavertia\Support\Contracts\Component;

abstract class AbstractInventoryWidget extends DashboardWidget
{
    /** @return list<Component> */
    protected function components(): array
    {
        return [];
    }
}
