# Lavertia Resources — v0.3 development alpha

Lavertia Resources connect existing Eloquent models to **Laravel Policy-protected** list, create, view, edit, update and delete routes. They reuse canonical Lavertia Tables/Forms and the existing React, Vue and Svelte Inertia renderer; Filament and Livewire are not dependencies.

This initial slice requires explicit implementations of persistence and tenant scoping. It does **not** automatically generate migrations, policies, or tenant-aware writes.

## 1. A Module-owned Eloquent model

Create the StockItem model inside the module that owns inventory, with an explicit database table and fillable attributes. Create a migration for the table (e.g. inventory_stock_items) and add an index on the tenant/organization or owner key. The example below stores one current-user item; a multi-tenant application must scope by the **active authorized tenant**, not trust any client-provided tenant_id.

## 2. Define a Resource

~~~php
<?php

declare(strict_types=1);

namespace Modules\Inventory\Lavertia\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lavertia\Forms\Components\TextInput;
use Lavertia\Panels\Resources\Resource;
use Lavertia\Tables\Components\TextColumn;
use Modules\Inventory\Models\StockItem;

final class StockResource extends Resource
{
    public static function model(): string
    {
        return StockItem::class;
    }

    public static function slug(): string
    {
        return 'stocks';
    }

    public static function title(): string
    {
        return 'Stock items';
    }

    public function query(): Builder
    {
        return StockItem::query()->where('user_id', auth()->id());
    }

    public function columns(): array
    {
        return [
            TextColumn::make('id')->label('ID')->sortable(),
            TextColumn::make('name')->label('Name')->searchable(),
        ];
    }

    public function formFields(?Model $record): array
    {
        return [
            TextInput::make('name')->label('Name')->required()
                ->value((string) ($record?->getAttribute('name') ?? '')),
        ];
    }

    public function rules(?Model $record): array
    {
        return ['name' => ['required', 'string', 'max:80']];
    }

    public function createRecord(array $validated): Model
    {
        return StockItem::query()->create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
        ]);
    }

    public function updateRecord(Model $record, array $validated): void
    {
        $record->update(['name' => $validated['name']]);
    }

    public function deleteRecord(Model $record): void
    {
        $record->delete();
    }
}
~~~

The Resource's query **must** be scoped. No record is ever fetched using unrestricted route model binding. A record outside the scope returns 404, including for update/delete. Laravel Policies apply to every read and write. Create is transactional and rolls back if the created record cannot be retrieved through the same scoped query.

Always write your own policy with viewAny, view, create, update and delete; register it using Laravel's policy discovery or Gate::policy. A missing or denying policy must result in 403, **not** unrestricted CRUD. Do not put tenant/organization IDs into client-editable form rules.

## 3. Register inside an existing Panel

Choose either a module Service Provider:

~~~php
public function boot(\Lavertia\Panels\PanelRegistry $panels): void
{
    $panels->discoverResources(
        panelId: 'admin',
        in: dirname(__DIR__).'/Lavertia/Resources',
        for: 'Modules\\Inventory\\Lavertia\\Resources',
    );
}
~~~

or a class list in config/lavertia.php:

~~~php
'panels' => [
    'admin' => [
        'path' => 'admin',
        'middleware' => ['web', 'auth'],
        'pages' => [],
        'resources' => [\Modules\Inventory\Lavertia\Resources\StockResource::class],
        'discover_resources' => [],
    ],
],
~~~

Only class strings and arrays are stored in config. This works with php artisan config:cache and route:cache. Module provider boot order is supported. No internachi/modular dependency is required unless your application uses its Composer module structure.

## 4. Routes and frontend behavior

For StockResource in the admin Panel, Laravel registers:

| HTTP | Path | Purpose | Policy |
| --- | --- | --- | --- |
| GET | /admin/resources/stocks | List (scoped Eloquent Table) | viewAny |
| GET | /admin/resources/stocks/create | Create form | create |
| POST | /admin/resources/stocks | Save validated record | create |
| GET | /admin/resources/stocks/{record} | Read authorized record (Infolist) | view |
| GET | /admin/resources/stocks/{record}/edit | Edit form | update |
| PUT | /admin/resources/stocks/{record} | Save validated changes | update |
| DELETE | /admin/resources/stocks/{record} | Delete record | delete |

All routes retain your Panel middleware and Laravel session-based CSRF validation. Forms use Inertia POST/PUT, while the delete endpoint expects a genuine DELETE request. The alpha UI includes Create and Edit links and a Policy-gated Delete button on the record View page, with explicit browser confirmation and an Inertia DELETE request. Per-row Table View/Edit links and bulk actions remain future enhancements; do not use a GET link to delete data.

Navigation is generated automatically for the Resource index and hidden unless its model policy grants viewAny. The same panel's Resource URL never leaks into another Panel.

## 5. Verification

~~~bash
composer dump-autoload
php artisan optimize:clear
php artisan route:list --path=admin/resources
php artisan config:cache
php artisan route:cache
php artisan test
npm run types:check
npm run build
~~~

CI includes: SQLite/Testbench auth/Policy/tenant tests, a real Laravel 13 + InterNACHI/modular module install with model/migration/policy, authenticated CRUD before/after route caching, and React/Vue/Svelte installed adapter builds.

**Release:** development alpha only. Resources do not implement Filament API compatibility, Relation Managers, automated tenancy, file upload, bulk actions, or stable backward compatibility guarantees.
