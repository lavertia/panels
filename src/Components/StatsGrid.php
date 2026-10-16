<?php

declare(strict_types=1);

namespace Lavertia\Panels\Components;

use Lavertia\Support\Components\Container;

final class StatsGrid extends Container
{
    public function getType(): string
    {
        return 'stats-grid';
    }
}
