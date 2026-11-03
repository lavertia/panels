<?php

declare(strict_types=1);

namespace Lavertia\Panels\Themes;

/**
 * Theme implementations supply presentation tokens, never executable CSS.
 * Configure class names rather than object instances for config:cache safety.
 */
interface PanelTheme
{
    public function id(): string;

    /** @return array<string, string> */
    public function tokens(): array;
}
