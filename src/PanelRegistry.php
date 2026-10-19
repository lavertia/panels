<?php

declare(strict_types=1);

namespace Lavertia\Panels;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Lavertia\Panels\Http\Controllers\ResourceController;
use Lavertia\Panels\Http\Middleware\ResolveLocale;
use Lavertia\Panels\Navigation\NavigationItem;
use Lavertia\Panels\Pages\Page;
use Lavertia\Panels\Resources\Resource;
use Lavertia\Panels\Widgets\DashboardWidget;
use Lavertia\Support\Contracts\Component;
use ReflectionClass;

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

    /** @var array<string, array<string, NavigationItem>> */
    private array $navigationItems = [];

    /** @var array<string, array<class-string<DashboardWidget>, class-string<DashboardWidget>>> */
    private array $dashboardWidgets = [];

    /** @var array<string, list<array{in: string, for: string}>> */
    private array $widgetDirectories = [];


    /** @var array<string, array<string, class-string<Resource>>> */
    private array $resources = [];

    /** @var array<string, array<string, bool>> */
    private array $mountedResources = [];

    /** @var array<string, list<array{in: string, for: string}>> */
    private array $resourceDirectories = [];

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

    /**
     * Modules may add navigation before or after PanelsServiceProvider boots.
     * Items never create routes; they only link to registered Pages of this Panel.
     */
    public function navigationItem(string $panelId, NavigationItem $item): void
    {
        Panel::make($panelId);

        $existing = $this->navigationItems[$panelId][$item->slug] ?? null;

        if ($existing !== null && ! $existing->sameConfiguration($item)) {
            throw new InvalidArgumentException('Conflicting navigation item for Page slug '.$item->slug);
        }

        $this->navigationItems[$panelId][$item->slug] = $item;
    }

    /**
     * @return list<array{label: string, slug: string, group: string|null, url: string, active: bool}>
     */
    public function navigation(string $panelId, ?string $currentRoute = null): array
    {
        if (! is_array(config('lavertia.panels.'.$panelId))) {
            return [];
        }

        $items = array_values($this->navigationItems[$panelId] ?? []);

        usort($items, static fn (NavigationItem $a, NavigationItem $b): int => ($a->groupName() <=> $b->groupName())
            ?: ($a->sortOrder() <=> $b->sortOrder())
            ?: ($a->slug <=> $b->slug));

        $currentRoute ??= request()->route()?->getName();
        $navigation = [];

        foreach ($items as $item) {
            $payload = $item->forPanel($panelId, $currentRoute);

            if ($payload !== null) {
                $navigation[] = $payload;
            }
        }

        return $navigation;
    }

    /** @param class-string<DashboardWidget> $widget */
    public function dashboardWidget(string $panelId, string $widget): void
    {
        Panel::make($panelId);

        if (! is_subclass_of($widget, DashboardWidget::class)
            || (new ReflectionClass($widget))->isAbstract()) {
            throw new InvalidArgumentException('Dashboard widgets must be concrete DashboardWidget subclasses.');
        }

        $this->dashboardWidgets[$panelId][$widget] = $widget;
    }

    /** Discover Composer PSR-4 widgets in an optional Laravel module. */
    public function discoverWidgets(string $panelId, string $in, string $for): void
    {
        Panel::make($panelId);

        $namespace = trim($for, '\\');

        if (trim($in) === ''
            || preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D', $namespace) !== 1) {
            throw new InvalidArgumentException('Widget discovery requires a directory and valid PHP namespace.');
        }

        $entry = ['in' => $in, 'for' => $namespace];

        if (! in_array($entry, $this->widgetDirectories[$panelId] ?? [], true)) {
            $this->widgetDirectories[$panelId][] = $entry;
        }
    }

    /** @return list<class-string<DashboardWidget>> */
    public function widgetClasses(string $panelId): array
    {
        if (! is_array(config('lavertia.panels.'.$panelId))) {
            return [];
        }

        $classes = $this->dashboardWidgets[$panelId] ?? [];
        $filesystem = new Filesystem;

        foreach ($this->widgetDirectories[$panelId] ?? [] as $directory) {
            if (! $filesystem->isDirectory($directory['in'])) {
                continue;
            }

            foreach ($filesystem->allFiles($directory['in']) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative = substr($file->getRelativePathname(), 0, -4);
                $class = $directory['for'].'\\'.str_replace(['/', '\\'], '\\', $relative);

                if (class_exists($class) && is_subclass_of($class, DashboardWidget::class)
                    && ! (new ReflectionClass($class))->isAbstract()) {
                    $classes[$class] = $class;
                }
            }
        }

        $widgets = array_values($classes);

        usort($widgets, static fn (string $a, string $b): int => ($a::sort() <=> $b::sort()) ?: ($a <=> $b));

        return $widgets;
    }

    /** @return list<Component> */
    public function dashboardComponents(string $panelId): array
    {
        $components = [];

        foreach ($this->widgetClasses($panelId) as $class) {
            $widget = app()->make($class);

            if ($widget->canView()) {
                array_push($components, ...$widget->render());
            }
        }

        return $components;
    }


    /** @param class-string<Resource> $resourceClass */
    public function resource(string $panelId, string $resourceClass): void
    {
        Panel::make($panelId);

        if (! is_subclass_of($resourceClass, Resource::class)
            || (new ReflectionClass($resourceClass))->isAbstract()
            || ! is_subclass_of($resourceClass::model(), Model::class)) {
            throw new InvalidArgumentException('Resources must be concrete Resource classes for Eloquent models.');
        }

        $slug = $resourceClass::slug();

        if (preg_match('/^[a-z][a-z0-9-]*$/D', $slug) !== 1 || $slug === 'create') {
            throw new InvalidArgumentException('Resource slug must be a safe single URL segment.');
        }

        $existing = $this->resources[$panelId][$slug] ?? null;

        if ($existing !== null && $existing !== $resourceClass) {
            throw new InvalidArgumentException('Duplicate resource slug in Panel '.$panelId.': '.$slug);
        }

        $this->resources[$panelId][$slug] = $resourceClass;

        $item = NavigationItem::make('resources/'.$slug, $resourceClass::title());

        if ($group = $resourceClass::navigationGroup()) {
            $item = $item->group($group);
        }

        $this->navigationItem($panelId, $item);

        if ($this->mounted && ! app()->routesAreCached()) {
            $options = config('lavertia.panels.'.$panelId);

            if (is_array($options)) {
                $this->mountResource($panelId, $options, $resourceClass);
            }
        }
    }

    /** Recursively discover PSR-4 Composer classes extending Resource. */
    public function discoverResources(string $panelId, string $in, string $for): void
    {
        Panel::make($panelId);

        $namespace = trim($for, '\\');

        if (trim($in) === ''
            || preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D', $namespace) !== 1) {
            throw new InvalidArgumentException('Resource discovery requires a directory and valid PHP namespace.');
        }

        $entry = ['in' => $in, 'for' => $namespace];

        if (! in_array($entry, $this->resourceDirectories[$panelId] ?? [], true)) {
            $this->resourceDirectories[$panelId][] = $entry;
        }

        if ($this->mounted) {
            $this->discoverRegisteredResources($panelId);
        }
    }

    private function discoverRegisteredResources(string $panelId): void
    {
        $filesystem = new Filesystem;

        foreach ($this->resourceDirectories[$panelId] ?? [] as $directory) {
            if (! $filesystem->isDirectory($directory['in'])) {
                continue;
            }

            foreach ($filesystem->allFiles($directory['in']) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative = substr($file->getRelativePathname(), 0, -4);
                $class = $directory['for'].'\\'.str_replace(['/', '\\'], '\\', $relative);

                if (class_exists($class) && is_subclass_of($class, Resource::class)
                    && ! (new ReflectionClass($class))->isAbstract()) {
                    $this->resource($panelId, $class);
                }
            }
        }
    }

    /** @return array<string, class-string<Resource>> */
    public function resourceClasses(string $panelId): array
    {
        return $this->resources[$panelId] ?? [];
    }

    /**
     * @param array<string, mixed> $options
     * @param class-string<Resource> $resourceClass
     */
    private function mountResource(string $panelId, array $options, string $resourceClass): void
    {
        $slug = $resourceClass::slug();

        if (isset($this->mountedResources[$panelId][$slug])) {
            return;
        }

        $url = 'resources/'.$slug;
        $name = 'lavertia.'.$panelId.'.resources.'.$slug;

        if (isset($this->registeredSlugs[$panelId][$url])
            || Route::getRoutes()->getByName($name) !== null) {
            throw new InvalidArgumentException('Resource conflicts with an existing Panel route: '.$url);
        }

        $prefix = (string) ($options['path'] ?? $panelId);
        $middleware = $options['middleware'] ?? ['web', 'auth', ResolveLocale::class];

        // Every operation uses an invokable controller method; no route closures,
        // ensuring route:cache works in production Laravel applications.
        Route::middleware($middleware)
            ->prefix($prefix)
            ->name('lavertia.'.$panelId.'.')
            ->group(static function () use ($url, $name, $panelId, $resourceClass): void {
                $definitions = [
                    ['get', $url, 'index', ''],
                    ['get', $url.'/create', 'create', '.create'],
                    ['post', $url, 'store', '.store'],
                    ['get', $url.'/{record}', 'show', '.show'],
                    ['get', $url.'/{record}/edit', 'edit', '.edit'],
                    ['put', $url.'/{record}', 'update', '.update'],
                    ['delete', $url.'/{record}', 'destroy', '.destroy'],
                ];

                foreach ($definitions as [$verb, $path, $method, $suffix]) {
                    Route::$verb($path, [ResourceController::class, $method])
                        ->defaults('lavertiaResource', $resourceClass)
                        ->defaults('lavertiaPanel', $panelId)
                        ->name('resources.'.$resourceClass::slug().$suffix);
                }
            });

        $this->mountedResources[$panelId][$slug] = true;
    }

    public function mountConfiguredPanels(): void
    {
        if ($this->mounted) {
            return;
        }

        $this->mounted = true;

        foreach (config('lavertia.panels', []) as $id => $options) {
            if (! is_string($id) || ! is_array($options)) {
                continue;
            }

            foreach ($options['navigation'] ?? [] as $item) {
                if (! is_array($item)) {
                    throw new InvalidArgumentException('Panel config navigation must contain arrays for config:cache compatibility.');
                }

                $this->navigationItem($id, NavigationItem::fromArray($item));
            }

            foreach ($options['dashboard_widgets'] ?? [] as $widget) {
                if (! is_string($widget)) {
                    throw new InvalidArgumentException('Panel dashboard widgets must be class names.');
                }

                $this->dashboardWidget($id, $widget);
            }

            foreach ($options['discover_widgets'] ?? [] as $discovery) {
                if (! is_array($discovery)
                    || ! is_string($discovery['in'] ?? null)
                    || ! is_string($discovery['for'] ?? null)) {
                    throw new InvalidArgumentException('Panel discover_widgets entries require in and for strings.');
                }

                $this->discoverWidgets($id, $discovery['in'], $discovery['for']);
            }

            foreach ($options['resources'] ?? [] as $resourceClass) {
                if (! is_string($resourceClass)) {
                    throw new InvalidArgumentException('Configured resources must be class names.');
                }

                $this->resource($id, $resourceClass);
            }

            foreach ($options['discover_resources'] ?? [] as $directory) {
                if (! is_array($directory)
                    || ! is_string($directory['in'] ?? null)
                    || ! is_string($directory['for'] ?? null)) {
                    throw new InvalidArgumentException('Resource discovery requires in and for strings.');
                }

                $this->discoverResources($id, $directory['in'], $directory['for']);
            }

            if (app()->routesAreCached()) {
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

            // Register Resource routes after ordinary Pages but before host
            // wildcard routes. Resources from late module providers also work.
            $this->discoverRegisteredResources($id);

            foreach ($this->resourceClasses($id) as $class) {
                $this->mountResource($id, $options, $class);
            }
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
