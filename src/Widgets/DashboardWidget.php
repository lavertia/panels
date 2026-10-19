<?php

declare(strict_types=1);

namespace Lavertia\Panels\Widgets;

use InvalidArgumentException;
use Lavertia\Support\Contracts\Component;

/**
 * Widget classes are resolved from Laravel's container for every dashboard
 * request. Components are passed through the normal Lavertia protocol.
 */
abstract class DashboardWidget
{
    public static function sort(): int
    {
        return 0;
    }

    /** Override to apply Laravel Policies, Gates, or tenant checks. */
    public function canView(): bool
    {
        return true;
    }

    /** @return list<Component> */
    abstract protected function components(): array;

    /** @return list<Component> */
    final public function render(): array
    {
        $components = $this->components();

        if (! array_is_list($components)) {
            throw new InvalidArgumentException('Dashboard widget components must be a list.');
        }

        foreach ($components as $component) {
            if (! $component instanceof Component) {
                throw new InvalidArgumentException('Dashboard widget must return Lavertia components.');
            }
        }

        return $components;
    }
}
