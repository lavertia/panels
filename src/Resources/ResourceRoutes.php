<?php

declare(strict_types=1);

namespace Lavertia\Panels\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Lavertia\Panels\Pages\Page;
use Lavertia\Panels\Resources\Resource as LavertiaResource;
use ReflectionClass;

final class ResourceRoutes
{
    /** @var list<string> */
    public const ACTIONS = ['list', 'create', 'edit', 'view'];

    /** @var array<string, string> */
    private const PATHS = [
        'list' => '',
        'create' => 'create',
        'edit' => '{record}/edit',
        'view' => '{record}',
    ];

    /** @param class-string<LavertiaResource> $resource */
    public static function validate(string $resource): void
    {
        if (! is_subclass_of($resource, Resource::class)
            || (new ReflectionClass($resource))->isAbstract()) {
            throw new InvalidArgumentException('Panel Resources must extend concrete Resource classes.');
        }

        $slug = $resource::slug();

        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $slug) !== 1
            || in_array($slug, ['auth', 'login', 'logout', 'resources'], true)) {
            throw new InvalidArgumentException('Invalid or reserved Resource slug.');
        }

        if (! is_subclass_of($resource::model(), Model::class)) {
            throw new InvalidArgumentException('Resource::model must be an Eloquent Model.');
        }

        $pages = $resource::pages();

        if ($pages === [] || array_is_list($pages)) {
            throw new InvalidArgumentException('Resource must define an associative Page map.');
        }

        foreach ($pages as $action => $page) {
            if (! in_array($action, self::ACTIONS, true)
                || ! is_string($page)
                || ! is_subclass_of($page, Page::class)
                || ! is_subclass_of($page, ResourcePageContract::class)
                || (new ReflectionClass($page))->isAbstract()
                || $page::resource() !== $resource
                || $page::resourceAction() !== $action) {
                throw new InvalidArgumentException('Invalid Resource Page mapping.');
            }
        }
    }

    /**
     * Register verified GET routes only. POST/PATCH/DELETE are owned by
     * the host's Form Requests, Policies and domain actions.
     *
     * @param  class-string<LavertiaResource>  $resource
     * @param  list<string>  $middleware
     */
    public static function mount(string $panelId, string $prefix, array $middleware, string $resource): void
    {
        self::validate($resource);

        Route::middleware($middleware)
            ->prefix(trim($prefix, '/').'/'.$resource::slug())
            ->name('lavertia.'.$panelId.'.resources.'.$resource::slug().'.')
            ->group(function () use ($resource): void {
                $pages = $resource::pages();

                foreach (self::ACTIONS as $action) {
                    if (isset($pages[$action])) {
                        Route::get(self::PATHS[$action], $pages[$action])
                            ->name($action);
                    }
                }
            });
    }
}
