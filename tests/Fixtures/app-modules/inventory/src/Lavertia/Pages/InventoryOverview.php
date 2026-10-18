<?php

declare(strict_types=1);

namespace Modules\Inventory\Lavertia\Pages;

use Lavertia\Panels\Pages\Page;
use Lavertia\Support\Components\Text;

final class InventoryOverview extends Page
{
    public static function slug(): string
    {
        return 'inventory/overview';
    }

    protected function components(): array
    {
        return [Text::make('Inventory overview')];
    }
}
