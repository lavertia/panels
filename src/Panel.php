<?php

declare(strict_types=1);

namespace Lavertia\Panels;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Lavertia\Panels\Pages\Page;
use Lavertia\Panels\Navigation\NavigationItem;
use ReflectionClass;

final class Panel
{
    private string $path;

    private bool $explicitPath = false;

    private bool $explicitMiddleware = false;

    private ?string $brandName = null;

    private ?string $brandSubtitle = null;

    private ?string $brandLogo = null;

    private ?string $primaryColor = null;

    private ?bool $sidebarCollapsible = null;

    private ?string $sidebarVariant = null;

    /** @var list<NavigationItem> */
    private array $navigationItems = [];


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

    /** @return list<NavigationItem> */
    public function getNavigationItems(): array
    {
        return $this->navigationItems;
    }

    /**
     * Provider-owned settings are merged with the existing config Panel.
     * Explicit settings override config; registered pages are additive.
     *
     * @return array<string, mixed>
     */
    public function configuration(): array
    {
        $config = [
            'pages' => $this->pages,
            'discover_pages' => $this->pageDirectories,
        ];

        if ($this->explicitPath) {
            $config['path'] = $this->path;
        }

        if ($this->explicitMiddleware) {
            $config['middleware'] = $this->middleware;
        }

        foreach ([
            'brand_name' => $this->brandName,
            'brand_subtitle' => $this->brandSubtitle,
            'brand_logo' => $this->brandLogo,
            'primary_color' => $this->primaryColor,
            'sidebar_collapsible' => $this->sidebarCollapsible,
            'sidebar_variant' => $this->sidebarVariant,
        ] as $key => $value) {
            if ($value !== null) {
                $config[$key] = $value;
            }
        }

        return $config;
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
        Route::middleware($this->middleware)
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
