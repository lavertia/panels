<?php

declare(strict_types=1);

namespace Lavertia\Panels\Themes;

/**
 * Stable, adapter-agnostic theme contract.
 * Implement this in a separately versioned Composer theme package.
 */
interface PanelTheme
{
    public function id(): string;

    /** @return array<string, string> CSS design-token names without "--". */
    public function tokens(): array;
}
