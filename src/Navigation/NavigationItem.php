<?php

declare(strict_types=1);

namespace Lavertia\Panels\Navigation;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Lavertia\Panels\Pages\Page;

/**
 * Navigation items resolve to registered Lavertia Page routes in the same Panel.
 * No arbitrary external URLs or javascript: schemes are accepted.
 */
final class NavigationItem
{
    private ?string $group = null;

    private int $sort = 0;

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
            && $this->ability === $other->ability;
    }

    public function sortOrder(): int
    {
        return $this->sort;
    }

    /** @return array{label: string, slug: string, group: string|null, url: string, active: bool}|null */
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
        ];
    }
}
