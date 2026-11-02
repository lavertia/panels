<?php

declare(strict_types=1);

namespace Lavertia\Panels\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Lavertia\Panels\Pages\Page;
use Lavertia\Panels\PanelRegistry;

/**
 * Resource metadata may be defined as static properties OR overridable static
 * methods. No request-specific Eloquent builder may live in static state.
 */
abstract class Resource
{
    protected static ?string $slug = null;

    /** @var class-string<Model>|null */
    protected static ?string $model = null;

    /** @var array<string, class-string<Page>|ResourcePageDefinition> */
    protected static array $pages = [];

    /** @var class-string<ResourceQueryProvider>|null */
    protected static ?string $queryProvider = null;

    public static function slug(): string
    {
        return static::$slug ?? throw new InvalidArgumentException('Resource must define a slug property or slug() method.');
    }

    /** @return class-string<Model> */
    public static function model(): string
    {
        return static::$model ?? throw new InvalidArgumentException('Resource must define a model property or model() method.');
    }

    /**
     * Must be tenant/permission-scoped by the application. Either override
     * query() or use a container-resolved ResourceQueryProvider per request.
     *
     * @return Builder<Model>
     */
    public static function query(): Builder
    {
        $provider = static::$queryProvider;

        if (! is_string($provider) || ! is_subclass_of($provider, ResourceQueryProvider::class)) {
            throw new InvalidArgumentException('Resource requires query() or a ResourceQueryProvider; no unscoped fallback is allowed.');
        }

        $builder = app($provider)->query();

        if ($builder->getModel()::class !== static::model()) {
            throw new InvalidArgumentException('Resource query provider returned a different Eloquent model.');
        }

        return $builder;
    }

    /**
     * Reuse the same Page subclass under multiple keys using
     * Page::route('/...')->action('list')->parameters([...]).
     *
     * @return array<string, class-string<Page>|ResourcePageDefinition>
     */
    public static function pages(): array
    {
        return static::$pages;
    }

    /**
     * Generate a URL for a registered named Resource Page.
     * Pass the Panel ID explicitly when outside a mounted Panel.
     *
     * @param array<string, mixed> $parameters
     */
    public static function getUrl(string $page = 'list', array $parameters = [], ?string $panelId = null): string
    {
        $panelId ??= app(PanelRegistry::class)->panelForRoute(request()->route()?->getName());

        if (! is_string($panelId) || $panelId === '') {
            throw new InvalidArgumentException('Pass an explicit Panel ID when generating Resource URLs outside a Panel.');
        }

        $name = 'lavertia.'.$panelId.'.resources.'.static::slug().'.'.$page;
        $route = Route::getRoutes()->getByName($name);

        if ($route === null || ($route->defaults[ResourceRoutes::REGISTRATION_KEY]['resource'] ?? null) !== static::class
            || ($route->defaults[ResourceRoutes::REGISTRATION_KEY]['key'] ?? null) !== $page) {
            throw new InvalidArgumentException('Resource Page route is not registered in the requested Panel.');
        }

        return route($name, $parameters, false);
    }
}
