<?php

declare(strict_types=1);

namespace Lavertia\Panels\Http\Controllers;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use InvalidArgumentException;
use Lavertia\Forms\Components\Form;
use Lavertia\Panels\Components\Action;
use Lavertia\Panels\Components\Infolist;
use Lavertia\Panels\Components\PageHeader;
use Lavertia\Panels\Components\TextEntry;
use Lavertia\Panels\PanelRegistry;
use Lavertia\Panels\Resources\Resource;
use Lavertia\Support\Components\Column;
use Lavertia\Support\Contracts\Component;
use Lavertia\Support\Serialization\ComponentProtocol;
use Lavertia\Tables\Components\Table;
use Inertia\Inertia;

final class ResourceController
{
    public function index(Request $request): Response
    {
        $resource = $this->resource($request);
        Gate::authorize('viewAny', $resource::model());

        $name = $this->routeName($request);
        $actions = [];

        if (Gate::allows('create', $resource::model())) {
            $actions[] = Action::make('create')->label('Create')->appearance('primary')
                ->url(route($name.'.create', [], false));
        }

        return $this->render($request, $resource::title(), [
            PageHeader::make($resource::title())->components($actions),
            Table::make('resource-'.$resource::slug())
                ->label($resource::title())
                ->searchable()
                ->columns($resource->tableColumns())
                ->eloquent(
                    $resource->scopedQuery(),
                    static fn (): bool => Gate::allows('viewAny', $resource::model()),
                ),
        ]);
    }

    public function create(Request $request): Response
    {
        $resource = $this->resource($request);
        Gate::authorize('create', $resource::model());

        return $this->render($request, 'Create '.$resource::title(), [
            PageHeader::make('Create '.$resource::title()),
            Form::make('resource-'.$resource::slug())
                ->label($resource::title())
                ->action(route($this->routeName($request), [], false))
                ->submitLabel('Create')
                ->components($resource->fields(null)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $resource = $this->resource($request);
        Gate::authorize('create', $resource::model());

        $validated = $request->validate($resource->rules(null));

        // Use the resource's model connection, allowing apps with multiple DBs.
        $model = (new ($resource::model()))->getConnection()->transaction(
            static fn (): Model => $resource->createRecord($validated),
        );

        if (! $model instanceof ($resource::model())) {
            throw new InvalidArgumentException('Resource createRecord returned an unexpected model.');
        }

        // The created model must be accessible through the same scoped query.
        // No cross-tenant ID is ever returned to the browser.
        $resource->findRecord((string) $model->getKey());

        return to_route($this->routeName($request).'.show', ['record' => $model->getRouteKey()]);
    }

    public function show(Request $request): Response
    {
        $resource = $this->resource($request);
        $record = $resource->findRecord((string) $request->route('record'));
        Gate::authorize('view', $record);

        $items = [];

        foreach ($resource->tableColumns() as $column) {
            $name = $column->getColumnName();
            $value = $record->getAttribute($name);

            if ($value instanceof DateTimeInterface) {
                $value = $value->format(DATE_ATOM);
            }

            if ($value !== null && ! is_scalar($value)) {
                throw new InvalidArgumentException('Resource entries must be scalar.');
            }

            $items[] = TextEntry::make($name)->label((string) ($column->getProps()['label'] ?? $name))
                ->value($value);
        }

        $actions = [Action::make('back')->label('Back')
            ->url(route($this->routeName($request), [], false))];

        if (Gate::allows('update', $record)) {
            $actions[] = Action::make('edit')->label('Edit')->appearance('primary')
                ->url(route($this->routeName($request).'.edit', ['record' => $record->getRouteKey()], false));
        }

        return $this->render($request, $resource::title(), [
            PageHeader::make($resource::title())->components($actions),
            Infolist::make('record')->label($resource::title())->components($items),
        ]);
    }

    public function edit(Request $request): Response
    {
        $resource = $this->resource($request);
        $record = $resource->findRecord((string) $request->route('record'));
        Gate::authorize('update', $record);

        return $this->render($request, 'Edit '.$resource::title(), [
            PageHeader::make('Edit '.$resource::title()),
            Form::make('resource-'.$resource::slug())
                ->label($resource::title())
                ->action(route($this->routeName($request).'.update', ['record' => $record->getRouteKey()], false))
                ->method('put')
                ->submitLabel('Save')
                ->components($resource->fields($record)),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $resource = $this->resource($request);
        $record = $resource->findRecord((string) $request->route('record'));
        Gate::authorize('update', $record);
        $validated = $request->validate($resource->rules($record));

        $record->getConnection()->transaction(
            static fn () => $resource->updateRecord($record, $validated),
        );

        return to_route($this->routeName($request).'.show', ['record' => $record->getRouteKey()]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $resource = $this->resource($request);
        $record = $resource->findRecord((string) $request->route('record'));
        Gate::authorize('delete', $record);

        $record->getConnection()->transaction(
            static fn () => $resource->deleteRecord($record),
        );

        return to_route($this->routeName($request));
    }

    private function resource(Request $request): Resource
    {
        $class = $request->route('lavertiaResource');

        if (! is_string($class) || ! is_subclass_of($class, Resource::class)) {
            abort(404);
        }

        return app($class);
    }

    private function routeName(Request $request): string
    {
        $panel = $request->route('lavertiaPanel');
        $class = $request->route('lavertiaResource');

        if (! is_string($panel) || ! is_string($class) || ! is_subclass_of($class, Resource::class)) {
            abort(404);
        }

        return 'lavertia.'.$panel.'.resources.'.$class::slug();
    }

    /** @param list<Component> $components */
    private function render(Request $request, string $title, array $components): Response
    {
        $locale = app()->getLocale();
        $panel = $request->route('lavertiaPanel');

        return Inertia::render('lavertia/page', [
            'lavertia' => [
                'version' => ComponentProtocol::VERSION,
                'title' => $title,
                'locale' => $locale,
                'direction' => in_array($locale, ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr',
                'tree' => Column::make($components)->toArray(),
                'navigation' => is_string($panel)
                    ? app(PanelRegistry::class)->navigation($panel, $this->routeName($request))
                    : [],
            ],
        ]);
    }
}
