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
    public const REGISTRATION_KEY = 'lavertia_resource_registration';

    /** @var list<string> */
    public const ACTIONS = ['list', 'create', 'edit', 'view'];

    /** @var array<string, string> */
    private const PATHS = [
        'list' => '/',
        'create' => '/create',
        'edit' => '/{record}/edit',
        'view' => '/{record}',
    ];

    /** @param class-string<LavertiaResource> $resource */
    public static function validate(string $resource): void
    {
        self::definitions($resource);
    }

    /**
     * Validate and normalize legacy page strings and fluent definitions.
     * Every registration has its own key; the Page class need not be unique.
     *
     * @param  class-string<LavertiaResource>  $resource
     * @return array<string, array{page: class-string<Page>, path: string, action: string, parameters: array<string, scalar|null>}>
     */
    public static function definitions(string $resource): array
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

        $resolved = [];
        $routeShapes = [];

        foreach ($pages as $key => $value) {
            if (! is_string($key) || preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $key) !== 1) {
                throw new InvalidArgumentException('Resource Page route keys must be safe, unique identifiers.');
            }

            $legacy = is_string($value);

            if ($legacy) {
                if (! isset(self::PATHS[$key])) {
                    throw new InvalidArgumentException('Custom Resource Page keys require a route definition.');
                }

                $value = ResourcePageDefinition::make($value)
                    ->route(self::PATHS[$key])
                    ->action($key);
            }

            if (is_array($value)) {
                $value = ResourcePageDefinition::fromArray($value);
            }

            if (! $value instanceof ResourcePageDefinition) {
                throw new InvalidArgumentException('Resource Page must be a Page class or valid registration definition.');
            }

            $definition = $value->resolve($key);
            $page = $definition['page'];

            if ($page::resource() !== $resource
                || ($legacy && $page::resourceAction() !== $key)) {
                throw new InvalidArgumentException('Resource Page must belong to the registering Resource and action.');
            }

            $shape = preg_replace('/\{[a-z][a-z0-9_]*\}/', '{}', $definition['path']);

            if (isset($routeShapes[$shape])) {
                throw new InvalidArgumentException('Resource Page URL conflicts with another registered Page.');
            }

            $routeShapes[$shape] = $key;
            $resolved[$key] = $definition;
        }

        return $resolved;
    }

    /**
     * Register Page routes with fixed server-owned metadata. Route literals
     * and longer paths precede catch-all record placeholders.
     *
     * @param  class-string<LavertiaResource>  $resource
     * @param  list<string>  $middleware
     */
    public static function mount(string $panelId, string $prefix, array $middleware, string $resource): void
    {
        $definitions = self::definitions($resource);

        uasort($definitions, static function (array $a, array $b): int {
            $dynamic = substr_count($a['path'], '{') <=> substr_count($b['path'], '{');

            if ($dynamic !== 0) {
                return $dynamic;
            }

            return substr_count($b['path'], '/') <=> substr_count($a['path'], '/');
        });

        Route::middleware($middleware)
            ->prefix(trim($prefix, '/').'/'.$resource::slug())
            ->name('lavertia.'.$panelId.'.resources.'.$resource::slug().'.')
            ->group(static function () use ($resource, $definitions): void {
                foreach ($definitions as $key => $definition) {
                    Route::get(ltrim($definition['path'], '/'), $definition['page'])
                        ->name($key)
                        ->defaults(self::REGISTRATION_KEY, [
                            'resource' => $resource,
                            'page' => $definition['page'],
                            'key' => $key,
                            'action' => $definition['action'],
                            'parameters' => $definition['parameters'],
                        ]);
                }
            });
    }
}
