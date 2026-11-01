<?php

declare(strict_types=1);

namespace Lavertia\Panels;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Lavertia\Panels\Http\Middleware\ResolveLocale;
use Lavertia\Panels\Localization\LocaleContext;
use Lavertia\Panels\Navigation\NavigationGroup;
use Lavertia\Panels\Navigation\NavigationItem;
use Lavertia\Panels\Navigation\NavigationSlot;
use Lavertia\Panels\Themes\PanelTheme;
use Lavertia\Panels\Themes\ThemeManifest;
use Lavertia\Panels\Pages\Page;
use Lavertia\Panels\Plugins\PanelPlugin;
use Lavertia\Panels\Widgets\DashboardWidget;
use ReflectionClass;

final class Panel
{
    private string $path;

    private bool $explicitPath = false;

    private bool $explicitMiddleware = false;

    private ?string $brandName = null;

    private ?string $brandSubtitle = null;

    private ?string $brandLogo = null;

    private string $brandLogoDisplay = 'full';

    private string $brandLogoPlacement = 'sidebar';

    private bool $tenantSwitcher = true;

    private string $tenantSwitcherPlacement = 'sidebar';

    private bool $globalSearch = false;

    private bool $notifications = false;

    private bool $userMenuSettings = true;

    private bool $userMenuLogout = true;

    /** @var list<array{label: string, url: string, external: bool}> */
    private array $footerLinks = [];

    /** @var list<array{label: string, url: string, external: bool}> */
    private array $userMenuLinks = [];

    private ?string $primaryColor = null;

    private ?bool $sidebarCollapsible = null;

    private ?string $sidebarVariant = null;

    private string $themeMode = 'system';

    private string $fontFamily = 'inherit';

    private string $radius = 'md';

    private string $contentWidth = 'full';

    private string $sidebarWidth = '18rem';

    private string $navigationPosition = 'sidebar';

    private bool $topbar = true;

    private bool $breadcrumbs = true;

    private bool $stickyHeader = false;

    private bool $mobileNavigation = true;

    /** @var list<string> Empty means Laravel's configured default language. */
    private array $locales = [];

    private ?string $defaultLocale = null;

    private ?string $layout = null;

    /** @var list<PanelPlugin> */
    private array $plugins = [];

    /** @var list<class-string<DashboardWidget>> */
    private array $dashboardWidgets = [];

    /** @var list<array{in: string, for: string}> */
    private array $widgetDirectories = [];

    /** @var list<array{label: string, collapsible: bool, collapsedByDefault: bool, sort: int}> */
    private array $navigationGroups = [];

    /** @var list<NavigationItem> */
    private array $navigationItems = [];

    /** @var list<NavigationSlot> */
    private array $shellSlots = [];

    private ?PanelTheme $theme = null;

    /** @var list<string> */
    private array $middleware = ['web', 'auth'];

    /** @var list<class-string<Page>> */
    private array $pages = [];

    /** @var list<array{in: string, for: string}> */
    private array $pageDirectories = [];

    private function __construct(private string $id)
    {
        if (preg_match('/^[a-z][a-z0-9_-]*$/D', $id) !== 1) {
            throw new InvalidArgumentException('Invalid panel ID.');
        }

        $this->path = $id;
    }

    public static function make(string $id = 'admin'): self
    {
        return new self($id);
    }

    public function id(string $id): self
    {
        if (preg_match('/^[a-z][a-z0-9_-]*$/D', $id) !== 1) {
            throw new InvalidArgumentException('Invalid panel ID.');
        }

        $copy = clone $this;

        if (! $copy->explicitPath) {
            $copy->path = $id;
        }

        $copy->id = $id;

        return $copy;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function brandName(string $name): self
    {
        if (trim($name) === '' || mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Invalid panel brand name.');
        }

        $copy = clone $this;
        $copy->brandName = $name;

        return $copy;
    }

    public function brandSubtitle(string $subtitle): self
    {
        if (mb_strlen($subtitle) > 120) {
            throw new InvalidArgumentException('Invalid panel brand subtitle.');
        }

        $copy = clone $this;
        $copy->brandSubtitle = $subtitle;

        return $copy;
    }

    public function brandLogo(string $logo): self
    {
        if (! str_starts_with($logo, '/') || str_starts_with($logo, '//')
            || str_contains($logo, '..') || preg_match('/[\s\\\\?#<>]/', $logo) === 1) {
            throw new InvalidArgumentException('Panel logo must use a safe same-origin absolute path.');
        }

        $copy = clone $this;
        $copy->brandLogo = $logo;

        return $copy;
    }

    public function brandLogoDisplay(string $display): self
    {
        if (! in_array($display, ['full', 'icon', 'hidden'], true)) {
            throw new InvalidArgumentException('Logo display must be full, icon or hidden.');
        }

        $copy = clone $this;
        $copy->brandLogoDisplay = $display;

        return $copy;
    }

    public function brandLogoPlacement(string $placement): self
    {
        if (! in_array($placement, ['sidebar', 'topbar', 'both'], true)) {
            throw new InvalidArgumentException('Logo placement must be sidebar, topbar or both.');
        }

        $copy = clone $this;
        $copy->brandLogoPlacement = $placement;

        return $copy;
    }

    public function tenantSwitcher(bool $enabled = true): self
    {
        $copy = clone $this;
        $copy->tenantSwitcher = $enabled;

        return $copy;
    }

    public function tenantSwitcherPlacement(string $placement): self
    {
        if (! in_array($placement, ['sidebar', 'topbar'], true)) {
            throw new InvalidArgumentException('Tenant switcher placement must be sidebar or topbar.');
        }

        $copy = clone $this;
        $copy->tenantSwitcherPlacement = $placement;

        return $copy;
    }

    public function globalSearch(bool $enabled = true): self
    {
        $copy = clone $this;
        $copy->globalSearch = $enabled;

        return $copy;
    }

    public function notifications(bool $enabled = true): self
    {
        $copy = clone $this;
        $copy->notifications = $enabled;

        return $copy;
    }

    public function userMenuSettings(bool $enabled = true): self
    {
        $copy = clone $this;
        $copy->userMenuSettings = $enabled;

        return $copy;
    }

    public function userMenuLogout(bool $enabled = true): self
    {
        $copy = clone $this;
        $copy->userMenuLogout = $enabled;

        return $copy;
    }

    /**
     * @param  list<array{label: string, url: string, external?: bool}>  $links
     */
    public function footerLinks(array $links): self
    {
        $copy = clone $this;
        $copy->footerLinks = $this->normalizeLinks($links);

        return $copy;
    }

    /**
     * @param  list<array{label: string, url: string, external?: bool}>  $links
     */
    public function userMenuLinks(array $links): self
    {
        $copy = clone $this;
        $copy->userMenuLinks = $this->normalizeLinks($links);

        return $copy;
    }

    /**
     * @param  list<array{label: string, url: string, external?: bool}>  $links
     * @return list<array{label: string, url: string, external: bool}>
     */
    private function normalizeLinks(array $links): array
    {
        if (! array_is_list($links)) {
            throw new InvalidArgumentException('Links must be an ordered list.');
        }

        $normalized = [];

        foreach ($links as $link) {
            if (! is_array($link) || ! is_string($link['label'] ?? null)
                || ! is_string($link['url'] ?? null)
                || trim($link['label']) === '' || mb_strlen($link['label']) > 80) {
                throw new InvalidArgumentException('Links require a valid label and URL.');
            }

            $url = $link['url'];
            $external = (bool) ($link['external'] ?? false);

            if ($external) {
                if (! str_starts_with($url, 'https://') || filter_var($url, FILTER_VALIDATE_URL) === false
                    || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
                    throw new InvalidArgumentException('External links must use HTTPS without credentials.');
                }
            } elseif (! str_starts_with($url, '/') || str_starts_with($url, '//')
                || preg_match('/[\\\\\r\n]/', $url) === 1) {
                throw new InvalidArgumentException('Internal links must use same-origin absolute paths.');
            }

            $normalized[] = [
                'label' => $link['label'],
                'url' => $url,
                'external' => $external,
            ];
        }

        return $normalized;
    }

    public function primaryColor(string $hex): self
    {
        if (preg_match('/^#[0-9a-fA-F]{6}$/D', $hex) !== 1) {
            throw new InvalidArgumentException('Panel primary color must be a six-digit hex value.');
        }

        $copy = clone $this;
        $copy->primaryColor = strtolower($hex);

        return $copy;
    }

    public function sidebarCollapsible(bool $enabled = true): self
    {
        $copy = clone $this;
        $copy->sidebarCollapsible = $enabled;

        return $copy;
    }

    public function sidebarVariant(string $variant): self
    {
        if (! in_array($variant, ['inset', 'sidebar', 'floating'], true)) {
            throw new InvalidArgumentException('Unsupported Starter Kit sidebar variant.');
        }

        $copy = clone $this;
        $copy->sidebarVariant = $variant;

        return $copy;
    }

    /**
     * Install a theme from any compatible Composer package. Panel does not
     * depend on the theme's package or on a specific React/Vue/Svelte UI kit.
     */
    public function theme(PanelTheme $theme): self
    {
        ThemeManifest::fromTheme($theme);

        $copy = clone $this;
        $copy->theme = $theme;

        return $copy;
    }

    /** @param  list<NavigationSlot>  $slots */
    public function shellSlots(array $slots): self
    {
        if (! array_is_list($slots)) {
            throw new InvalidArgumentException('Shell slots must be an ordered list.');
        }

        $names = [];

        foreach ($slots as $slot) {
            if (! $slot instanceof NavigationSlot || isset($names[$slot->name()])) {
                throw new InvalidArgumentException('Invalid or duplicate shell navigation slot.');
            }

            $names[$slot->name()] = true;

            foreach ($slot->getComponents() as $child) {
                if ($child instanceof NavigationItem) {
                    continue;
                }

                if (! $child instanceof NavigationGroup) {
                    throw new InvalidArgumentException('A navigation slot supports NavigationItem and NavigationGroup children.');
                }

                foreach ($child->getComponents() as $item) {
                    if (! $item instanceof NavigationItem) {
                        throw new InvalidArgumentException('Navigation groups currently support NavigationItem children only.');
                    }
                }
            }
        }

        $copy = clone $this;
        $copy->shellSlots = $slots;

        return $copy;
    }

    /** @return list<NavigationSlot> */
    public function getShellSlots(): array
    {
        return $this->shellSlots;
    }

    /** @param list<NavigationItem> $items */
    public function navigationItems(array $items): self
    {
        if (! array_is_list($items)) {
            throw new InvalidArgumentException('Panel navigation items must be a list.');
        }

        foreach ($items as $item) {
            if (! $item instanceof NavigationItem) {
                throw new InvalidArgumentException('Expected NavigationItem instances.');
            }
        }

        $copy = clone $this;
        $copy->navigationItems = $items;

        return $copy;
    }

    /**
     * @param  list<array{label: string, collapsible?: bool, collapsedByDefault?: bool, sort?: int}>  $groups
     */
    public function navigationGroups(array $groups): self
    {
        if (! array_is_list($groups)) {
            throw new InvalidArgumentException('Navigation groups must be a list.');
        }

        $seen = [];
        $normalized = [];

        foreach ($groups as $group) {
            if (! is_array($group) || ! is_string($group['label'] ?? null)
                || trim($group['label']) === '' || mb_strlen($group['label']) > 80
                || preg_match('/[<>\\x00-\\x1F\\x7F]/u', $group['label']) !== 0) {
                throw new InvalidArgumentException('Invalid navigation group label.');
            }

            $label = $group['label'];

            if (isset($seen[$label])) {
                throw new InvalidArgumentException('Duplicate navigation group.');
            }

            $seen[$label] = true;

            foreach (['collapsible', 'collapsedByDefault'] as $key) {
                if (array_key_exists($key, $group) && ! is_bool($group[$key])) {
                    throw new InvalidArgumentException('Navigation group flags must be boolean.');
                }
            }

            if (array_key_exists('sort', $group) && ! is_int($group['sort'])) {
                throw new InvalidArgumentException('Navigation group sort must be an integer.');
            }

            $collapsible = $group['collapsible'] ?? false;
            $collapsed = $group['collapsedByDefault'] ?? false;

            if ($collapsed && ! $collapsible) {
                throw new InvalidArgumentException('Only collapsible groups may start collapsed.');
            }

            $normalized[] = [
                'label' => $label,
                'collapsible' => $collapsible,
                'collapsedByDefault' => $collapsed,
                'sort' => $group['sort'] ?? 0,
            ];
        }

        usort($normalized, static fn (array $a, array $b): int => ($a['sort'] <=> $b['sort'])
            ?: ($a['label'] <=> $b['label']));

        $copy = clone $this;
        $copy->navigationGroups = $normalized;

        return $copy;
    }

    public function themeMode(string $mode): self
    {
        if (! in_array($mode, ['light', 'dark', 'system'], true)) {
            throw new InvalidArgumentException('Theme mode must be light, dark, or system.');
        }

        $copy = clone $this;
        $copy->themeMode = $mode;

        return $copy;
    }

    public function fontFamily(string $font): self
    {
        if (preg_match('/^[A-Za-z0-9 ,_-]{1,80}$/D', $font) !== 1) {
            throw new InvalidArgumentException('Invalid font family token.');
        }

        $copy = clone $this;
        $copy->fontFamily = $font;

        return $copy;
    }

    public function borderRadius(string $radius): self
    {
        if (! in_array($radius, ['none', 'sm', 'md', 'lg', 'xl'], true)) {
            throw new InvalidArgumentException('Unsupported border radius.');
        }

        $copy = clone $this;
        $copy->radius = $radius;

        return $copy;
    }

    public function contentWidth(string $width): self
    {
        if (! in_array($width, ['sm', 'md', 'lg', 'xl', '2xl', 'full'], true)) {
            throw new InvalidArgumentException('Unsupported content width.');
        }

        $copy = clone $this;
        $copy->contentWidth = $width;

        return $copy;
    }

    public function sidebarWidth(string $width): self
    {
        if (preg_match('/^(?:1[4-9]|2[0-8])rem$/D', $width) !== 1) {
            throw new InvalidArgumentException('Sidebar width must be between 14rem and 28rem.');
        }

        $copy = clone $this;
        $copy->sidebarWidth = $width;

        return $copy;
    }

    /**
     * Opt in only when multiple languages are supported.
     * With no configuration, a Panel renders in Laravel's default locale.
     *
     * @param  list<string>  $codes
     */
    public function locales(array $codes): self
    {
        $copy = clone $this;
        $copy->locales = LocaleContext::validate($codes);

        return $copy;
    }

    public function defaultLocale(string $code): self
    {
        LocaleContext::validate([$code]);

        $copy = clone $this;
        $copy->defaultLocale = $code;

        return $copy;
    }

    public function layout(string $layout): self
    {
        if (! in_array($layout, ['sidebar', 'top', 'full', 'workspace'], true)) {
            throw new InvalidArgumentException('Panel layout must be sidebar, top or full.');
        }

        $copy = clone $this;
        $copy->layout = $layout;

        return $copy;
    }

    public function navigationPosition(string $position): self
    {
        if (! in_array($position, ['sidebar', 'top'], true)) {
            throw new InvalidArgumentException('Navigation position must be sidebar or top.');
        }

        $copy = clone $this;
        $copy->navigationPosition = $position;

        return $copy;
    }

    public function topbar(bool $visible = true): self
    {
        $copy = clone $this;
        $copy->topbar = $visible;

        return $copy;
    }

    public function breadcrumbs(bool $visible = true): self
    {
        $copy = clone $this;
        $copy->breadcrumbs = $visible;

        return $copy;
    }

    public function stickyHeader(bool $enabled = true): self
    {
        $copy = clone $this;
        $copy->stickyHeader = $enabled;

        return $copy;
    }

    public function mobileNavigation(bool $enabled = true): self
    {
        $copy = clone $this;
        $copy->mobileNavigation = $enabled;

        return $copy;
    }

    /** @param list<PanelPlugin> $plugins */
    public function plugins(array $plugins): self
    {
        if (! array_is_list($plugins)) {
            throw new InvalidArgumentException('Panel plugins must be a list.');
        }

        foreach ($plugins as $plugin) {
            if (! $plugin instanceof PanelPlugin) {
                throw new InvalidArgumentException('Expected PanelPlugin instances.');
            }
        }

        $copy = clone $this;
        $copy->plugins = $plugins;

        return $copy;
    }

    /** @return list<PanelPlugin> */
    public function getPlugins(): array
    {
        return $this->plugins;
    }

    /** @param list<class-string<DashboardWidget>> $widgets */
    public function dashboardWidgets(array $widgets): self
    {
        foreach ($widgets as $widget) {
            if (! is_string($widget) || ! is_subclass_of($widget, DashboardWidget::class)) {
                throw new InvalidArgumentException('Dashboard widgets must extend DashboardWidget.');
            }
        }

        $copy = clone $this;
        $copy->dashboardWidgets = $widgets;

        return $copy;
    }

    public function discoverWidgets(string $in, string $for): self
    {
        if (trim($in) === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/D', trim($for, '\\')) !== 1) {
            throw new InvalidArgumentException('Widget discovery requires a directory and namespace.');
        }

        $copy = clone $this;
        $copy->widgetDirectories[] = ['in' => $in, 'for' => trim($for, '\\')];

        return $copy;
    }

    /** @return list<NavigationItem> */
    public function getNavigationItems(): array
    {
        return $this->navigationItems;
    }

    /**
     * Internal route and shell metadata; all values originate in this Panel,
     * never in config/lavertia.php.
     *
     * @return array<string, mixed>
     */
    public function configuration(): array
    {
        if ($this->defaultLocale !== null && $this->locales !== []
            && ! in_array($this->defaultLocale, $this->locales, true)) {
            throw new InvalidArgumentException('Default locale must belong to the Panel supported locales.');
        }

        return [
            'path' => $this->path,
            'middleware' => $this->middleware,
            'pages' => $this->pages,
            'discover_pages' => $this->pageDirectories,
            'brand_name' => $this->brandName,
            'brand_subtitle' => $this->brandSubtitle,
            'brand_logo' => $this->brandLogo,
            'brand_logo_display' => $this->brandLogoDisplay,
            'brand_logo_placement' => $this->brandLogoPlacement,
            'tenant_switcher' => $this->tenantSwitcher,
            'tenant_switcher_placement' => $this->tenantSwitcherPlacement,
            'global_search' => $this->globalSearch,
            'notifications' => $this->notifications,
            'footer_links' => $this->footerLinks,
            'user_menu_links' => $this->userMenuLinks,
            'user_menu_settings' => $this->userMenuSettings,
            'user_menu_logout' => $this->userMenuLogout,
            'primary_color' => $this->primaryColor,
            'sidebar_collapsible' => $this->sidebarCollapsible,
            'sidebar_variant' => $this->sidebarVariant,
            'theme_mode' => $this->themeMode,
            'font_family' => $this->fontFamily,
            'border_radius' => $this->radius,
            'content_width' => $this->contentWidth,
            'sidebar_width' => $this->sidebarWidth,
            'navigation_position' => $this->navigationPosition,
            'layout' => $this->layout ?? ($this->navigationPosition === 'top' ? 'top' : 'sidebar'),
            'locales' => $this->locales,
            'default_locale' => $this->defaultLocale,
            'navigation_groups' => $this->navigationGroups,
            'shell_slots' => $this->shellSlots,
            'theme' => $this->theme === null ? ThemeManifest::default() : ThemeManifest::fromTheme($this->theme),
            'topbar' => $this->topbar,
            'breadcrumbs' => $this->breadcrumbs,
            'sticky_header' => $this->stickyHeader,
            'mobile_navigation' => $this->mobileNavigation,
            'dashboard_widgets' => $this->dashboardWidgets,
            'discover_widgets' => $this->widgetDirectories,
        ];
    }

    public function path(string $path): self
    {
        if (preg_match('#^[A-Za-z0-9][A-Za-z0-9/_-]*$#D', $path) !== 1 || str_contains($path, '//')) {
            throw new InvalidArgumentException('Invalid panel path.');
        }

        $copy = clone $this;
        $copy->path = $path;
        $copy->explicitPath = true;

        return $copy;
    }

    /** @param list<string> $middleware */
    public function middleware(array $middleware): self
    {
        if (! array_is_list($middleware) || $middleware === []) {
            throw new InvalidArgumentException('Panel middleware must be a non-empty list.');
        }

        $copy = clone $this;
        $copy->middleware = $middleware;
        $copy->explicitMiddleware = true;

        return $copy;
    }

    /** @param list<class-string<Page>> $pages */
    public function pages(array $pages): self
    {
        if (! array_is_list($pages)) {
            throw new InvalidArgumentException('Panel pages must be a list.');
        }

        foreach ($pages as $page) {
            if (! is_string($page) || ! is_subclass_of($page, Page::class)) {
                throw new InvalidArgumentException('Panel pages must extend Lavertia Page.');
            }
        }

        $copy = clone $this;
        $copy->pages = $pages;

        return $copy;
    }

    /**
     * Discover PSR-4 autoloadable pages in a directory, including Laravel modules.
     *
     * @param  string  $in  Absolute directory containing Page subclasses
     * @param  string  $for  PSR-4 namespace corresponding to that directory
     */
    public function discoverPages(string $in, string $for): self
    {
        $namespace = trim($for, '\\');

        if (trim($in) === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D', $namespace) !== 1) {
            throw new InvalidArgumentException('Page discovery requires a directory and valid PHP namespace.');
        }

        $copy = clone $this;
        $copy->pageDirectories[] = ['in' => $in, 'for' => $namespace];

        return $copy;
    }

    /** @return list<class-string<Page>> */
    private function discoveredPages(): array
    {
        $files = new Filesystem;
        $discovered = [];

        foreach ($this->pageDirectories as $directory) {
            if (! $files->isDirectory($directory['in'])) {
                continue;
            }

            foreach ($files->allFiles($directory['in']) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative = substr($file->getRelativePathname(), 0, -4);
                $class = $directory['for'].'\\'.str_replace(['/', '\\'], '\\', $relative);

                if (! class_exists($class) || ! is_subclass_of($class, Page::class)) {
                    continue;
                }

                if ((new ReflectionClass($class))->isAbstract()) {
                    continue;
                }

                $discovered[$class] = $class;
            }
        }

        ksort($discovered, SORT_STRING);

        return array_values($discovered);
    }

    /** @return list<class-string<Page>> */
    public function pageClasses(): array
    {
        return array_values(array_unique(array_merge($this->pages, $this->discoveredPages())));
    }

    public function registerRoutes(): void
    {
        // Always resolve Page locale after the host's web/session middleware.
        $middleware = in_array(ResolveLocale::class, $this->middleware, true)
            ? $this->middleware
            : [...$this->middleware, ResolveLocale::class];

        Route::middleware($middleware)
            ->prefix($this->path)
            ->name('lavertia.'.$this->id.'.')
            ->group(function (): void {
                $slugs = [];

                foreach ($this->pageClasses() as $page) {
                    $slug = $page::slug();

                    if (preg_match('#^[a-z0-9][a-z0-9/-]*$#D', $slug) !== 1
                        || str_contains($slug, '//') || isset($slugs[$slug])) {
                        throw new InvalidArgumentException('Duplicate or unsafe page slug.');
                    }

                    $slugs[$slug] = true;

                    Route::get($slug, $page)->name(str_replace('/', '.', $slug));
                }
            });

        // Laravel may not refresh its route-name lookup when Pages are mounted
        // dynamically during provider boot (including module registration).
        Route::getRoutes()->refreshNameLookups();
    }
}
