<?php

declare(strict_types=1);

namespace Lavertia\Panels\Themes;

use InvalidArgumentException;

final class ThemeManifest
{
    /** Only CSS variables used by the Lavertia design system may be overridden. */
    private const TOKENS = [
        'background', 'foreground', 'card', 'card-foreground',
        'primary', 'primary-foreground', 'secondary', 'secondary-foreground',
        'accent', 'accent-foreground', 'muted', 'muted-foreground',
        'border', 'input', 'ring',
        'sidebar', 'sidebar-foreground', 'sidebar-primary',
        'sidebar-primary-foreground', 'sidebar-accent',
        'sidebar-accent-foreground', 'sidebar-border', 'sidebar-ring',
    ];

    /** @return array{id: string, tokens: array<string, string>} */
    public static function fromTheme(PanelTheme $theme): array
    {
        $id = $theme->id();

        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $id) !== 1) {
            throw new InvalidArgumentException('Theme ID must be a lowercase slug.');
        }

        $tokens = $theme->tokens();

        if ($tokens !== [] && array_is_list($tokens)) {
            throw new InvalidArgumentException('Theme tokens must be an associative array.');
        }

        foreach ($tokens as $name => $value) {
            if (! in_array($name, self::TOKENS, true) || ! is_string($value)
                || preg_match('/^#[0-9a-fA-F]{6}$/D', $value) !== 1) {
                throw new InvalidArgumentException('Theme contains an unsupported CSS token or unsafe color.');
            }

            $tokens[$name] = strtolower($value);
        }

        return ['id' => $id, 'tokens' => $tokens];
    }

    /** @return array{id: string, tokens: array<string, string>} */
    public static function default(): array
    {
        return ['id' => 'default', 'tokens' => []];
    }
}
