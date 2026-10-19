<?php

declare(strict_types=1);

namespace Lavertia\Panels;

use InvalidArgumentException;
use Lavertia\Panels\Http\Middleware\ResolveLocale;
use Lavertia\Panels\Pages\Page;

/**
 * Register Panel routes during service-provider boot, before host web routes.
 * Module providers may boot before or after PanelsServiceProvider.
 */
final class PanelRegistry
{
    /** @var array<string, list<array{in: string, for: string}>> */
    private array $pageDirectories = [];

    /** @var array<string, array<class-string<Page>, string>> */
    private array $registeredPages = [];

    /** @var array<string, array<string, class-string<Page>>> */
    private array $registeredSlugs = [];

    private bool $mounted = false;

    public function discoverPages(string $panelId, string $in, string $for): void
    {
        // Validate inputs even when the selected Panel is not yet configured.
        Panel::make($panelId)->discoverPages(in: $in, for: $for);

        $entry = ['in' => $in, 'for' => trim($for, '\\')];

        if (in_array($entry, $this->pageDirectories[$panelId] ?? [], true)) {
            return;
        }

        $this->pageDirectories[$panelId][] = $entry;

        // Register immediately when a module provider boots after Panels.
        // Deferring until app->booted() lets broad host routes shadow Page routes.
        if ($this->mounted && ! app()->routesAreCached()) {
            $options = config('lavertia.panels.'.$panelId);

            if (is_array($options)) {
                $this->mountPages(
                    $panelId,
                    $options,
                    Panel::make($panelId)->discoverPages(in: $in, for: $for)->pageClasses(),
                );
            }
        }
    }

    /** @return list<array{in: string, for: string}> */
    public function pageDirectories(string $panelId): array
    {
        return $this->pageDirectories[$panelId] ?? [];
    }

    public function mountConfiguredPanels(): void
    {
        if ($this->mounted) {
            return;
        }

        $this->mounted = true;

        if (app()->routesAreCached()) {
            return;
        }

        foreach (config('lavertia.panels', []) as $id => $options) {
            if (! is_string($id) || ! is_array($options)) {
                continue;
            }

            $panel = Panel::make($id)
                ->pages($options['pages'] ?? []);

            foreach ($options['discover_pages'] ?? [] as $discovery) {
                if (! is_array($discovery)
                    || ! is_string($discovery['in'] ?? null)
                    || ! is_string($discovery['for'] ?? null)) {
                    throw new InvalidArgumentException('Panel discover_pages entries require in and for strings.');
                }

                $panel = $panel->discoverPages(in: $discovery['in'], for: $discovery['for']);
            }

            foreach ($this->pageDirectories($id) as $discovery) {
                $panel = $panel->discoverPages(in: $discovery['in'], for: $discovery['for']);
            }

            $this->mountPages($id, $options, $panel->pageClasses());
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  list<class-string<Page>>  $pages
     */
    private function mountPages(string $panelId, array $options, array $pages): void
    {
        $slugs = $this->registeredSlugs[$panelId] ?? [];
        $registered = $this->registeredPages[$panelId] ?? [];
        $pending = [];

        foreach ($pages as $page) {
            $slug = $page::slug();

            if (isset($slugs[$slug]) && $slugs[$slug] !== $page) {
                throw new InvalidArgumentException('Duplicate or unsafe page slug.');
            }

            $slugs[$slug] = $page;

            if (! isset($registered[$page])) {
                $pending[] = $page;
                $registered[$page] = $slug;
            }
        }

        if ($pending === []) {
            return;
        }

        // Panel applies the exact configured middleware and checks unsafe slugs.
        Panel::make($panelId)
            ->path((string) ($options['path'] ?? $panelId))
            ->middleware($options['middleware'] ?? ['web', 'auth', ResolveLocale::class])
            ->pages($pending)
            ->registerRoutes();

        $this->registeredPages[$panelId] = $registered;
        $this->registeredSlugs[$panelId] = $slugs;
    }
}
