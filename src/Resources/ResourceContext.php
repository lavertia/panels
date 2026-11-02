<?php

declare(strict_types=1);

namespace Lavertia\Panels\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ResourceContext
{
    public const RECORD_ATTRIBUTE = 'lavertia.resource.record';

    public static function authorize(ResourcePageContract $page): void
    {
        $resource = $page::resource();
        $metadata = request()->route()?->defaults[ResourceRoutes::REGISTRATION_KEY] ?? null;

        if ($metadata !== null && (! is_array($metadata)
            || ($metadata['resource'] ?? null) !== $resource
            || ($metadata['page'] ?? null) !== $page::class
            || ! is_string($metadata['action'] ?? null))) {
            throw new InvalidArgumentException('Resource registration does not match the current Page.');
        }

        // Authorization follows the registered route, NOT the Page class:
        // one class may serve multiple named pages with different policies.
        $action = $metadata['action'] ?? $page::resourceAction();

        if (! is_subclass_of($resource, Resource::class)
            || ! in_array($action, ['list', 'create', 'view', 'edit'], true)) {
            throw new InvalidArgumentException('Invalid Resource Page contract.');
        }

        $model = $resource::model();

        if (! is_subclass_of($model, Model::class)) {
            throw new InvalidArgumentException('Resource model must extend Eloquent Model.');
        }

        if ($action === 'list') {
            Gate::authorize('viewAny', $model);

            return;
        }

        if ($action === 'create') {
            Gate::authorize('create', $model);

            return;
        }

        $key = request()->route('record');

        if ((! is_string($key) && ! is_int($key))
            || strlen((string) $key) > 128 || (string) $key === '') {
            throw new NotFoundHttpException;
        }

        $query = $resource::query();

        if (! $query instanceof Builder || $query->getModel()::class !== $model) {
            throw new InvalidArgumentException('Resource query must return the configured Eloquent Model.');
        }

        // Scoped query produces a 404 for records belonging to another tenant.
        $record = $query->whereKey($key)->firstOrFail();

        Gate::authorize($action === 'edit' ? 'update' : 'view', $record);

        // No entire Model crosses the Inertia protocol. Page components
        // explicitly choose safe fields through $this->record().
        request()->attributes->set(self::RECORD_ATTRIBUTE, $record);
    }
}
