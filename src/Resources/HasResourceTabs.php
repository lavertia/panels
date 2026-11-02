<?php

declare(strict_types=1);

namespace Lavertia\Panels\Resources;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Lavertia\Panels\Resources\Tabs\Tab;

/**
 * Filter tabs for any Resource Page presentation, including ListRecords.
 * Query scopes are applied to an already user/tenant-scoped Resource builder.
 */
trait HasResourceTabs
{
    /** @var array<string, Tab>|null */
    private ?array $resolvedResourceTabs = null;

    /** @return array<string, Tab> */
    protected function getTabs(): array
    {
        return [];
    }

    protected function getDefaultActiveTab(): ?string
    {
        return null;
    }

    /** @return array<string, Tab> */
    private function resourceTabs(): array
    {
        if ($this->resolvedResourceTabs !== null) {
            return $this->resolvedResourceTabs;
        }

        $tabs = $this->getTabs();

        if ($tabs !== [] && (array_is_list($tabs) || count($tabs) > 20)) {
            throw new InvalidArgumentException('Resource filter tabs require 1–20 named entries.');
        }

        foreach ($tabs as $key => $tab) {
            if (! is_string($key) || preg_match('/^[a-z][a-z0-9_-]{0,39}$/D', $key) !== 1
                || ! $tab instanceof Tab) {
                throw new InvalidArgumentException('Invalid Resource filter tab key or definition.');
            }
        }

        return $this->resolvedResourceTabs = $tabs;
    }

    protected function activeTab(): ?string
    {
        $tabs = $this->resourceTabs();

        if ($tabs === []) {
            return null;
        }

        $requested = request()->query('activeTab');

        if (is_string($requested) && array_key_exists($requested, $tabs)) {
            return $requested;
        }

        $default = $this->getDefaultActiveTab();

        if ($default !== null && array_key_exists($default, $tabs)) {
            return $default;
        }

        return array_key_first($tabs);
    }

    /**
     * Use this builder for an Eloquent table. Never derive the base query from
     * a tab: tabs only narrow the application's existing resource/tenant scope.
     */
    protected function filteredResourceQuery(): Builder
    {
        $resource = static::resource();
        $query = $resource::query();
        $active = $this->activeTab();

        if ($active !== null) {
            $this->resourceTabs()[$active]->scope($query);
        }

        return $query;
    }

    /**
     * Return header tabs serialized with only safe display props. These are
     * regular Inertia Links, so tab changes do not require a separate API.
     *
     * @return list<array<string, mixed>>
     */
    protected function resourceTabLinks(): array
    {
        $tabs = $this->resourceTabs();

        if ($tabs === []) {
            return [];
        }

        // Only preserve table state and locale. Reset pagination on tab
        // changes to avoid an empty out-of-range results page.
        $query = [];

        foreach (request()->query() as $key => $value) {
            if (! is_string($key) || ! is_string($value)
                || strlen($value) > 150 || count($query) >= 30) {
                continue;
            }

            if ($key === 'lang' || (preg_match('/^lvt_[a-zA-Z0-9_-]{1,51}_(search|sort|direction|filter_[a-zA-Z0-9_]+)$/D', $key) === 1)) {
                $query[$key] = $value;
            }
        }

        $active = $this->activeTab();
        $path = request()->getPathInfo();
        $links = [];

        foreach ($tabs as $key => $tab) {
            $parameters = [...$query, 'activeTab' => $key];
            $meta = $tab->metadata($key);

            $links[] = [
                ...$meta,
                'url' => $path.'?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986),
                'active' => $key === $active,
            ];
        }

        return $links;
    }
}
