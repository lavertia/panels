<?php

declare(strict_types=1);

namespace Lavertia\Panels\Navigation;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Lavertia\Panels\Pages\Page;
use Lavertia\Panels\PanelRegistry;
use Lavertia\Support\Components\AbstractComponent;

/**
 * Navigation items resolve to registered Lavertia Page routes in the same Panel.
 * No arbitrary external URLs or javascript: schemes are accepted.
 */
final class NavigationItem extends AbstractComponent
{
    private ?string $group = null;

    private int $sort = 0;

    /** @var list<string> */
    private const ICONS = [
        'bell', 'book-open', 'briefcase', 'calendar', 'chart-column', 'file-chart-column',
        'file-text', 'folder', 'house', 'layout-dashboard', 'list', 'receipt',
        'settings', 'shield', 'users', 'wallet',
    ];

    private ?string $icon = null;

    private ?string $badge = null;

    /** Parent must be a registered, authorized top-level Page in the same group. */
    private ?string $parent = null;

    private ?string $ability = null;

    private function __construct(
        public readonly string $slug,
        public readonly string $label,
    ) {
        if (preg_match('#^[a-z0-9][a-z0-9/-]*$#D', $slug) !== 1 || str_contains($slug, '//')) {
            throw new InvalidArgumentException('Navigation requires a safe Lavertia Page slug.');
        }

        if (trim($label) === '') {
            throw new InvalidArgumentException('Navigation label cannot be empty.');
        }
    }

    public static function make(string $slug, string $label): self
    {
        return new self($slug, $label);
    }

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        if (! is_string($config['slug'] ?? null) || ! is_string($config['label'] ?? null)) {
            throw new InvalidArgumentException('Configured navigation items require slug and label strings.');
        }

        $item = self::make($config['slug'], $config['label']);

        if (array_key_exists('group', $config) && $config['group'] !== null) {
            if (! is_string($config['group'])) {
                throw new InvalidArgumentException('Navigation group must be a string.');
            }

            $item = $item->group($config['group']);
        }

        if (array_key_exists('sort', $config)) {
            if (! is_int($config['sort'])) {
                throw new InvalidArgumentException('Navigation sort must be an integer.');
            }

            $item = $item->sort($config['sort']);
        }

        if (array_key_exists('icon', $config) && $config['icon'] !== null) {
            if (! is_string($config['icon'])) {
                throw new InvalidArgumentException('Navigation icon must be a string.');
            }

            $item = $item->icon($config['icon']);
        }

        if (array_key_exists('badge', $config) && $config['badge'] !== null) {
            if (! is_string($config['badge'])) {
                throw new InvalidArgumentException('Navigation badge must be a string.');
            }

            $item = $item->badge($config['badge']);
        }

        if (array_key_exists('parent', $config) && $config['parent'] !== null) {
            if (! is_string($config['parent'])) {
                throw new InvalidArgumentException('Navigation parent must be a Page slug.');
            }

            $item = $item->parent($config['parent']);
        }

        if (array_key_exists('ability', $config) && $config['ability'] !== null) {
            if (! is_string($config['ability'])) {
                throw new InvalidArgumentException('Navigation ability must be a string.');
            }

            $item = $item->ability($config['ability']);
        }

        return $item;
    }

    /**
     * Navigation items use the standard Component protocol, so the same
     * instance can be placed in a shell slot, a navigation group or a Page.
     * Never expose a hidden/unauthorized route's label or slug in props.
     */
    public function getType(): string
    {
        return 'navigation-item';
    }

    /** @return array<string, mixed> */
    public function getProps(): array
    {
        if (! app()->bound(PanelRegistry::class)) {
            return ['visible' => false];
        }

        $registry = app(PanelRegistry::class);
        $currentRoute = request()->route()?->getName();
        $panelId = $registry->panelForRoute($currentRoute);

        if ($panelId === null) {
            return ['visible' => false];
        }

        foreach ($registry->navigation($panelId, $currentRoute, true) as $link) {
            if ($link['slug'] === $this->slug) {
                return ['visible' => true, ...$link];
            }
        }

        return ['visible' => false];
    }

    public function group(string $group): self
    {
        if (trim($group) === '') {
            throw new InvalidArgumentException('Navigation group cannot be empty.');
        }

        $copy = clone $this;
        $copy->group = $group;

        return $copy;
    }

    public function sort(int $sort): self
    {
        $copy = clone $this;
        $copy->sort = $sort;

        return $copy;
    }

    /** Known Lucide icons only; adapters never resolve arbitrary component names. */
    public function icon(string $icon): self
    {
        if (! in_array($icon, self::ICONS, true)) {
            throw new InvalidArgumentException('Unsupported navigation icon.');
        }

        $copy = clone $this;
        $copy->icon = $icon;

        return $copy;
    }

    /** Compact status text. Never accept HTML or control characters. */
    public function badge(string $badge): self
    {
        if (trim($badge) === '' || mb_strlen($badge) > 20
            || preg_match('/[<>\\x00-\\x1F\\x7F]/u', $badge) !== 0) {
            throw new InvalidArgumentException('Invalid navigation badge.');
        }

        $copy = clone $this;
        $copy->badge = $badge;

        return $copy;
    }

    public function parent(string $slug): self
    {
        if ($slug === $this->slug || preg_match('#^[a-z0-9][a-z0-9/-]*$#D', $slug) !== 1
            || str_contains($slug, '//')) {
            throw new InvalidArgumentException('Navigation parent must be a different, safe Page slug.');
        }

        $copy = clone $this;
        $copy->parent = $slug;

        return $copy;
    }

    public function parentSlug(): ?string
    {
        return $this->parent;
    }

    /** Require a Laravel Gate / Policy ability before including this item. */
    public function ability(string $ability): self
    {
        if (trim($ability) === '') {
            throw new InvalidArgumentException('Navigation ability cannot be empty.');
        }

        $copy = clone $this;
        $copy->ability = $ability;

        return $copy;
    }

    public function groupName(): string
    {
        return $this->group ?? '';
    }

    public function sameConfiguration(self $other): bool
    {
        return $this->slug === $other->slug
            && $this->label === $other->label
            && $this->group === $other->group
            && $this->sort === $other->sort
            && $this->ability === $other->ability
            && $this->icon === $other->icon
            && $this->badge === $other->badge
            && $this->parent === $other->parent;
    }

    public function sortOrder(): int
    {
        return $this->sort;
    }

    /** @return array{label: string, slug: string, group: string|null, url: string, active: bool, icon: string|null, badge: string|null, parentSlug: string|null}|null */
    public function forPanel(string $panelId, ?string $currentRoute): ?array
    {
        if ($this->ability !== null && ! Gate::allows($this->ability)) {
            return null;
        }

        $name = 'lavertia.'.$panelId.'.'.str_replace('/', '.', $this->slug);
        $route = Route::getRoutes()->getByName($name);

        // Do not expose an unregistered or unrelated Laravel route via module nav.
        if ($route === null || ! is_subclass_of($route->getActionName(), Page::class)) {
            return null;
        }

        // Only URLs backed by a verified route (and without required parameters).
        if ($route->parameterNames() !== []) {
            return null;
        }

        $url = route($name, [], false);

        if (! is_string($url) || ! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }

        return [
            'label' => $this->label,
            'slug' => $this->slug,
            'group' => $this->group,
            'url' => $url,
            'active' => $currentRoute === $name,
            'icon' => $this->icon,
            'badge' => $this->badge,
            'parentSlug' => $this->parent,
        ];
    }
}
