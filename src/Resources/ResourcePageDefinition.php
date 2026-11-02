<?php

declare(strict_types=1);

namespace Lavertia\Panels\Resources;

use InvalidArgumentException;
use Lavertia\Panels\Pages\Page;
use ReflectionClass;

/**
 * One immutable, uniquely named Resource Page route registration.
 *
 * Parameter values are server-defined configuration, NOT URL/query inputs.
 * The same Page class may be registered under multiple names with different
 * route paths, authorization actions and configuration.
 */
final class ResourcePageDefinition
{
    private ?string $path = null;

    private ?string $ability = null;

    /** @var array<string, string|int|float|bool|null> */
    private array $parameters = [];

    /** @param class-string<Page> $page */
    private function __construct(private readonly string $page)
    {
        if (! is_subclass_of($page, Page::class)
            || ! is_subclass_of($page, ResourcePageContract::class)
            || (new ReflectionClass($page))->isAbstract()) {
            throw new InvalidArgumentException('Resource Page must be a concrete Page implementing ResourcePageContract.');
        }
    }

    /** @param class-string<Page> $page */
    public static function make(string $page): self
    {
        return new self($page);
    }

    /** Paths include placeholders such as /{record}/history/{year}. */
    public function route(string $path): self
    {
        if (strlen($path) > 180 || ! str_starts_with($path, '/')) {
            throw new InvalidArgumentException('Resource Page routes must use a short, relative path.');
        }

        $segments = $path === '/' ? [] : explode('/', substr($path, 1));
        $seen = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\{([a-z][a-z0-9_]{0,39})\}$/D', $segment, $matches) === 1) {
                if (isset($seen[$matches[1]]) || str_starts_with($matches[1], 'lavertia_')) {
                    throw new InvalidArgumentException('Invalid or repeated Resource route placeholder.');
                }

                $seen[$matches[1]] = true;

                continue;
            }

            if (preg_match('/^[a-z][a-z0-9-]*$/D', $segment) !== 1) {
                throw new InvalidArgumentException('Unsafe Resource route segment.');
            }
        }

        $copy = clone $this;
        $copy->path = $path;

        return $copy;
    }

    /** Server-side policy type, independent of the unique route name. */
    public function action(string $ability): self
    {
        if (! in_array($ability, ['list', 'create', 'view', 'edit'], true)) {
            throw new InvalidArgumentException('Unsupported Resource Page authorization action.');
        }

        $copy = clone $this;
        $copy->ability = $ability;

        return $copy;
    }

    /**
     * Fixed registration parameters, available through Page::pageParameter().
     * Never evaluated as executable code or used to bypass an Eloquent scope.
     *
     * @param array<string, string|int|float|bool|null> $parameters
     */
    public function parameters(array $parameters): self
    {
        if (count($parameters) > 32) {
            throw new InvalidArgumentException('Too many Resource Page parameters.');
        }

        foreach ($parameters as $name => $value) {
            if (! is_string($name)
                || preg_match('/^[a-z][a-z0-9_]{0,39}$/D', $name) !== 1
                || $name === 'record' || str_starts_with($name, 'lavertia_')
                || (! is_null($value) && ! is_scalar($value))
                || (is_string($value) && mb_strlen($value) > 255)
                || (is_float($value) && ! is_finite($value))) {
                throw new InvalidArgumentException('Resource Page parameters must contain safe named scalar values.');
            }
        }

        $copy = clone $this;
        $copy->parameters = $parameters;

        return $copy;
    }

    /**
     * @return array{page: class-string<Page>, path: string, action: string, parameters: array<string, scalar|null>}
     */
    public function resolve(string $key): array
    {
        $standard = [
            'list' => '/',
            'create' => '/create',
            'edit' => '/{record}/edit',
            'view' => '/{record}',
        ];

        $path = $this->path ?? $standard[$key] ?? null;
        $action = $this->ability ?? (isset($standard[$key]) ? $key : null);

        if ($path === null || $action === null) {
            throw new InvalidArgumentException('Custom Resource Page routes require route() and action().');
        }

        // Explicitly validate even the default paths.
        $path = $this->route($path)->path;

        $routeNames = [];

        if (preg_match_all('/\{([a-z][a-z0-9_]*)\}/', $path, $matches) !== false) {
            $routeNames = $matches[1];
        }

        $requiresRecord = in_array($action, ['view', 'edit'], true);

        if ($requiresRecord !== in_array('record', $routeNames, true)) {
            throw new InvalidArgumentException('Only View/Edit Pages may use a required {record} route.');
        }

        foreach (array_keys($this->parameters) as $name) {
            if (in_array($name, $routeNames, true)) {
                throw new InvalidArgumentException('Page configuration parameters cannot shadow URL route placeholders.');
            }
        }

        return [
            'page' => $this->page,
            'path' => $path,
            'action' => $action,
            'parameters' => $this->parameters,
        ];
    }
}
