<?php

declare(strict_types=1);

namespace Modules\Inventory\Lavertia\Widgets;

use Lavertia\Panels\Widgets\DashboardWidget;
use Lavertia\Support\Components\Text;
use Lavertia\Support\Contracts\Component;

final class HiddenInventoryWidget extends DashboardWidget
{
    public function canView(): bool
    {
        return false;
    }

    /** @return list<Component> */
    protected function components(): array
    {
        return [Text::make('PRIVATE STOCK')];
    }
}
