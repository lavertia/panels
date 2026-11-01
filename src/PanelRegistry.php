<?php

declare(strict_types=1);

namespace Lavertia\Panels;

use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Lavertia\Panels\Http\Middleware\ResolveLocale;
use Lavertia\Panels\Localization\LocaleContext;
use Lavertia\Panels\Navigation\NavigationItem;
use Lavertia\Panels\Pages\Page;
use Lavertia\Panels\Widgets\DashboardWidget;
use Lavertia\Support\Contracts\Component;
use ReflectionClass;

/**
 * Register Panel routes during service-provider boot, before host web routes.
 * Module providers may boot before or after PanelsServiceProvider.
 */
final class PanelRegistry
{
    /** @var array<string, Panel> */
    private array $panels = [];

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

    private bool $mounted = false;

    /** Register the only authoritative Panel definition, before route mounting. */
    public function registerPanel(Panel $panel): void
    {
        if ($this->mounted) {
            throw new InvalidArgumentException('Register PanelProviders before Lavertia routes are mounted.');
        }

        $id = $panel->getId();

        if (isset($this->panels[$id])) {
            throw new InvalidArgumentException('Panel ['.$id.'] has already been registered.');
        }

        $this->panels[$id] = $panel;

        foreach ($panel->getNavigationItems() as $item) {
            $this->navigationItem($id, $item);
        }

        // Slots and Groups may place a Page in several regions, but route
        // registration and Gate checks remain single-source in this registry.
        foreach ($panel->getShellSlots() as $slot) {
            foreach ($slot->getComponents() as $child) {
                if ($child instanceof NavigationItem) {
                    $this->navigationItem($id, $child);

                    continue;
                }

                foreach ($child->getComponents() as $item) {
                    if ($item instanceof NavigationItem) {
                        $this->navigationItem($id, $item);
                    }
                }
            }
        }

        foreach ($panel->getPlugins() as $plugin) {
            $plugin->register($this, $id);
        }
    }

    public function hasPanel(string $panelId): bool
    {
        return isset($this->panels[$panelId]);
    }

    /** @return list<string> */
    public function panelIds(): array
    {
        return array_keys($this->panels);
    }

    public function panel(string $panelId): ?Panel
    {
        return $this->panels[$panelId] ?? null;
    }

    public function panelForRoute(?string $routeName): ?string
    {
        if (is_string($routeName)
            && preg_match('/^lavertia\.([a-z][a-z0-9_-]*)\./', $routeName, $matches) === 1
            && isset($this->panels[$matches[1]])) {
            return $matches[1];
        }

        // Starter Kit routes (e.g. profile/settings) remain attached to the
        // default Panel when they do not belong to a Lavertia Page route.
        return isset($this->panels['admin'])
            ? 'admin'
            : (array_key_first($this->panels) ?: null);
    }

    /**
     * Return only validated, JSON-safe presentation metadata.
     * The host Starter Kit owns the actual sidebar and appearance controls.
     *
     * @return array<string, mixed>|null
     */
    public function appearance(string $panelId): ?array
    {
        $options = ($this->panels[$panelId] ?? null)?->configuration();

        if (! is_array($options)) {
            return null;
        }

        $routeName = request()->route()?->getName();
        $currentPage = is_string($routeName) && str_starts_with($routeName, 'lavertia.'.$panelId.'.')
            ? LocaleContext::pageClass(request())
            : null;
        $context = LocaleContext::resolve(request(), $this, $panelId);
        $layout = $options['layout'] ?? 'sidebar';

        if ($currentPage !== null && $currentPage::layout() !== null) {
            $layout = $currentPage::layout();
        }

        if (! in_array($layout, ['sidebar', 'top', 'full', 'workspace'], true)) {
            throw new InvalidArgumentException('Invalid Page or Panel layout.');
        }

        $primary = is_string($options['primary_color'] ?? null)
            && preg_match('/^#[0-9a-fA-F]{6}$/D', $options['primary_color']) === 1
            ? strtolower($options['primary_color'])
            : '#f59e0b';

        $channels = [
            hexdec(substr($primary, 1, 2)) / 255,
            hexdec(substr($primary, 3, 2)) / 255,
            hexdec(substr($primary, 5, 2)) / 255,
        ];

        $linear = array_map(
            static fn (float $channel): float => $channel <= 0.04045
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4,
            $channels,
        );

        $luminance = 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
        $foreground = $luminance > 0.179 ? '#111827' : '#ffffff';

        return [
            'id' => $panelId,
            'brandName' => (string) ($options['brand_name'] ?? config('app.name', 'Lavertia')),
            'brandSubtitle' => (string) ($options['brand_subtitle'] ?? ''),
            'brandLogo' => is_string($options['brand_logo'] ?? null) ? $options['brand_logo'] : null,
            'brandLogoDisplay' => $options['brand_logo_display'],
            'brandLogoPlacement' => $options['brand_logo_placement'],
            'tenantSwitcher' => $options['tenant_switcher'],
            'tenantSwitcherPlacement' => $options['tenant_switcher_placement'],
            'globalSearch' => $options['global_search'],
            'notifications' => $options['notifications'],
            'footerLinks' => $options['footer_links'],
            'userMenuLinks' => $options['user_menu_links'],
            'userMenuSettings' => $options['user_menu_settings'],
            'userMenuLogout' => $options['user_menu_logout'],
            'primaryColor' => $primary,
            'primaryForeground' => $foreground,
            'themeMode' => $options['theme_mode'],
            'fontFamily' => $options['font_family'],
            'borderRadius' => $options['border_radius'],
            'contentWidth' => $options['content_width'],
            'sidebarWidth' => $options['sidebar_width'],
            'navigationPosition' => $layout === 'full' ? 'none' : ($layout === 'top' ? 'top' : 'sidebar'),
            'layout' => $layout,
            'locale' => $context['locale'],
            'direction' => $context['direction'],
            'supportedLocales' => $context['locales'],
            'languageSwitcher' => count($context['locales']) > 1,
            'navigationGroups' => $options['navigation_groups'] ?? [],
            'topbar' => $options['topbar'],
            'breadcrumbs' => $options['breadcrumbs'],
            'stickyHeader' => $options['sticky_header'],
            'mobileNavigation' => $options['mobile_navigation'],
            'sidebarCollapsible' => $options['sidebar_collapsible'] ?? true,
            'sidebarVariant' => (string) ($options['sidebar_variant'] ?? 'inset'),
            'theme' => $options['theme'],
            'shellSlots' => array_map(
                static fn ($slot): array => $slot->toArray(),
                $options['shell_slots'] ?? [],
            ),
        ];
    }

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
            $options = ($this->panels[$panelId] ?? null)?->configuration();

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
     * Only registered, Gate-authorized Pages appear in navigation. A nested
     * item is visible only when its top-level parent is also authorized.
     *
     * @return list<array<string, mixed>>
     */
    public function navigation(string $panelId, ?string $currentRoute = null, bool $includeSlotItems = false): array
    {
        if (! isset($this->panels[$panelId])) {
            return [];
        }

        $groups = $this->panels[$panelId]->configuration()['navigation_groups'] ?? [];
        $groupRanks = [];

        foreach ($groups as $index => $group) {
            $groupRanks[$group['label']] = $index;
        }

        $items = array_values($this->navigationItems[$panelId] ?? []);

        // Ungrouped Pages render in the Starter Kit's "Lavertia" group.
        $visibleGroup = static fn (NavigationItem $item): string => $item->groupName() === ''
            ? 'Lavertia'
            : $item->groupName();

        usort($items, static fn (NavigationItem $a, NavigationItem $b): int => (($groupRanks[$visibleGroup($a)] ?? PHP_INT_MAX) <=> ($groupRanks[$visibleGroup($b)] ?? PHP_INT_MAX))
            ?: ($visibleGroup($a) <=> $visibleGroup($b))
            ?: ($a->sortOrder() <=> $b->sortOrder())
            ?: ($a->slug <=> $b->slug));

        $currentRoute ??= request()->route()?->getName();
        $visible = [];
        $itemBySlug = [];

        foreach ($items as $item) {
            $itemBySlug[$item->slug] = $item;
            $payload = $item->forPanel($panelId, $currentRoute);

            if ($payload !== null) {
                $visible[$item->slug] = $payload;
            }
        }

        $children = [];

        foreach ($items as $item) {
            $parentSlug = $item->parentSlug();

            if ($parentSlug === null || ! isset($visible[$item->slug])) {
                continue;
            }

            // Reject cycles, multi-level nesting, and cross-group children.
            if (! isset($visible[$parentSlug]) || ($itemBySlug[$parentSlug] ?? null)?->parentSlug() !== null
                || $item->groupName() !== $itemBySlug[$parentSlug]->groupName()) {
                unset($visible[$item->slug]);

                continue;
            }

            $children[$parentSlug][] = $visible[$item->slug];
        }

        $navigation = [];

        foreach ($items as $item) {
            if ($item->parentSlug() !== null || ! isset($visible[$item->slug])) {
                continue;
            }

            $root = $visible[$item->slug];
            $descendants = $children[$item->slug] ?? [];
            $root['descendantActive'] = in_array(true, array_column($descendants, 'active'), true);
            $navigation[] = $root;

            foreach ($descendants as $child) {
                $child['descendantActive'] = false;
                $navigation[] = $child;
            }
        }

        if (! $includeSlotItems) {
            $slotted = [];

            foreach ($this->panels[$panelId]->getShellSlots() as $slot) {
                foreach ($slot->getComponents() as $child) {
                    if ($child instanceof NavigationItem) {
                        $slotted[$child->slug] = true;

                        continue;
                    }

                    foreach ($child->getComponents() as $item) {
                        if ($item instanceof NavigationItem) {
                            $slotted[$item->slug] = true;
                        }
                    }
                }
            }

            return array_values(array_filter(
                $navigation,
                static fn (array $link): bool => ! isset($slotted[$link['slug']]),
            ));
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
        if (! isset($this->panels[$panelId])) {
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

    public function mountRegisteredPanels(): void
    {
        if ($this->mounted) {
            return;
        }

        $this->mounted = true;

        foreach ($this->panels as $id => $panel) {
            $options = $panel->configuration();

            foreach ($options['dashboard_widgets'] as $widget) {
                $this->dashboardWidget($id, $widget);
            }

            foreach ($options['discover_widgets'] as $discovery) {
                $this->discoverWidgets($id, $discovery['in'], $discovery['for']);
            }

            if (app()->routesAreCached()) {
                continue;
            }

            $pages = $panel->pageClasses();

            foreach ($this->pageDirectories($id) as $discovery) {
                $pages = array_merge($pages, Panel::make($id)
                    ->discoverPages(in: $discovery['in'], for: $discovery['for'])
                    ->pageClasses());
            }

            $this->mountPages($id, $options, array_values(array_unique($pages)));
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
