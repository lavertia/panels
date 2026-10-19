<?php

declare(strict_types=1);

namespace Modules\Inventory\Lavertia\Pages\Reports;

use Lavertia\Panels\Pages\Page;
use Lavertia\Support\Components\Text;

final class DailyStock extends Page
{
    public static function slug(): string
    {
        return 'inventory/reports/daily-stock';
    }

    protected function components(): array
    {
        return [Text::make('Daily stock')];
    }
}
