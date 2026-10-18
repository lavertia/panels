<?php

declare(strict_types=1);

namespace Modules\Inventory\Lavertia\Pages;

use Lavertia\Panels\Pages\Page;

abstract class AbstractModulePage extends Page
{
    protected function components(): array
    {
        return [];
    }
}
