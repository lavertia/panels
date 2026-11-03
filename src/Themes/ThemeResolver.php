<?php

declare(strict_types=1);

namespace Lavertia\Panels\Themes;

use InvalidArgumentException;

final class ThemeResolver
{
    /** @var array<string, string> */
    private const DEFAULT_TOKENS = [
        'primary' => '#b45309',
        'primary-foreground' => '#ffffff',
        'sidebar-primary' => '#b45309',
        'sidebar-primary-foreground' => '#ffffff',
        'ring' => '#d97706',
    ];

    /** @return array{id: string, tokens: array<string, string>} */
    public function forPanel(?string $panelId): array
    {
        $class = $panelId !== null ? config('lavertia.panels.'.$panelId.'.theme') : null;

        if ($class === null) {
            return ['id' => 'default', 'tokens' => self::DEFAULT_TOKENS];
        }

        if (! is_string($class) || ! is_subclass_of($class, PanelTheme::class)) {
            throw new InvalidArgumentException('Panel theme must be a class implementing PanelTheme.');
        }

        /** @var PanelTheme $theme */
        $theme = app()->make($class);
        $id = $theme->id();
        $tokens = $theme->tokens();

        if (preg_match('/^[a-z][a-z0-9_-]{0,49}$/D', $id) !== 1) {
            throw new InvalidArgumentException('Invalid Panel theme ID.');
        }

        if (count($tokens) !== count(self::DEFAULT_TOKENS)) {
            throw new InvalidArgumentException('Panel theme must define all five semantic tokens.');
        }

        foreach ($tokens as $name => $value) {
            if (! is_string($name)
                || ! array_key_exists($name, self::DEFAULT_TOKENS)
                || ! is_string($value)
                || preg_match('/^#[0-9a-fA-F]{6}$/D', $value) !== 1) {
                throw new InvalidArgumentException('Invalid Panel theme token.');
            }
        }

        return ['id' => $id, 'tokens' => $tokens];
    }
}
