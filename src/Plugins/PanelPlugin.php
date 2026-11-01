<?php

declare(strict_types=1);

namespace Lavertia\Panels\Plugins;

use Lavertia\Panels\PanelRegistry;

/**
 * A plugin contributes server-owned Pages, widgets and navigation.
 * It does not execute arbitrary JavaScript or bypass Panel middleware.
 */
interface PanelPlugin
{
    public function register(PanelRegistry $registry, string $panelId): void;
}
