<?php

declare(strict_types=1);

namespace Lavertia\Panels\Navigation;

use Illuminate\Support\Facades\Gate;
use Lavertia\Panels\Http\Controllers\ResourceController;
use Lavertia\Panels\Resources\Resource;
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

        if (array_key_exists('ability', $config) && $config['ability'] !== null) {
            if (! is_string($config['ability'])) {
                throw new InvalidArgumentException('Navigation ability must be a string.');
            }

            $item = $item->ability($config['ability']);
        }

        return $item;
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

        // Only known Lavertia Pages or a policy-authorized Resource index
        // may appear in navigation. Raw controller routes are never trusted.
        if ($route === null) {
            return null;
        }

        $page = is_subclass_of($route->getActionName(), Page::class);
        $resourceClass = $route->defaults['lavertiaResource'] ?? null;
        $resource = $route->getActionName() === ResourceController::class.'@index'
            && ($route->defaults['lavertiaPanel'] ?? null) === $panelId
            && is_string($resourceClass)
            && is_subclass_of($resourceClass, Resource::class)
            && Gate::allows('viewAny', $resourceClass::model());

        if (! $page && ! $resource) {
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
