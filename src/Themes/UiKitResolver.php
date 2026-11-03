<?php

declare(strict_types=1);

namespace Lavertia\Panels\Themes;

use InvalidArgumentException;

/**
 * Resolve an implementation-agnostic UI Kit ID for the current Page.
 *
 * Priority: Page override > Panel override > application default.
 * The ID selects a frontend package/renderer; it never loads executable code.
 */
final class UiKitResolver
{
    /** @return array{id: string} */
    public function forPanel(?string $panelId, ?string $pageOverride = null): array
    {
        $panelKit = $panelId !== null ? config('lavertia.panels.'.$panelId.'.ui_kit') : null;
        $kit = $pageOverride ?? $panelKit ?? config('lavertia.ui.kit', 'default');

        if (! is_string($kit)
            || strlen($kit) > 96
            || preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z][a-z0-9-]*)?$/D', $kit) !== 1) {
            throw new InvalidArgumentException('Invalid Lavertia UI Kit ID.');
        }

        return ['id' => $kit];
    }
}
