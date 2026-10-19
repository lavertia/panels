<?php

declare(strict_types=1);

namespace Lavertia\Panels;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Lavertia\Panels\Pages\Page;
use ReflectionClass;

final class Panel
{
    private string $path;

    /** @var list<string> */
    private array $middleware = ['web', 'auth'];

    /** @var list<class-string<Page>> */
    private array $pages = [];

    /** @var list<array{in: string, for: string}> */
    private array $pageDirectories = [];

    private function __construct(private readonly string $id)
    {
        if (preg_match('/^[a-z][a-z0-9_-]*$/D', $id) !== 1) {
            throw new InvalidArgumentException('Invalid panel ID.');
        }

        $this->path = $id;
    }

    public static function make(string $id): self
    {
        return new self($id);
    }

    public function path(string $path): self
    {
        if (preg_match('#^[A-Za-z0-9][A-Za-z0-9/_-]*$#D', $path) !== 1 || str_contains($path, '//')) {
            throw new InvalidArgumentException('Invalid panel path.');
        }

        $copy = clone $this;
        $copy->path = $path;

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

    public function registerRoutes(): void
    {
        Route::middleware($this->middleware)
            ->prefix($this->path)
            ->name('lavertia.'.$this->id.'.')
            ->group(function (): void {
                $slugs = [];

                foreach (array_unique(array_merge($this->pages, $this->discoveredPages())) as $page) {
                    $slug = $page::slug();

                    if (preg_match('#^[a-z0-9][a-z0-9/-]*$#D', $slug) !== 1
                        || str_contains($slug, '//') || isset($slugs[$slug])) {
                        throw new InvalidArgumentException('Duplicate or unsafe page slug.');
                    }

                    $slugs[$slug] = true;

                    Route::get($slug, $page)->name(str_replace('/', '.', $slug));
                }
            });
    }
}
