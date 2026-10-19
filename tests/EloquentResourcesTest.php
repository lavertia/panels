<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Lavertia\Forms\Components\TextInput;
use Lavertia\Panels\PanelRegistry;
use Lavertia\Panels\PanelsServiceProvider;
use Lavertia\Panels\Resources\Resource;
use Lavertia\Support\Contracts\FieldComponent;
use Lavertia\Support\Contracts\TableColumnComponent;
use Lavertia\Tables\Components\TextColumn;
use Orchestra\Testbench\TestCase;

final class EloquentResourcesTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        // This module boots after Panels to exercise late registration.
        return [PanelsServiceProvider::class, FixtureStockModuleProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('lavertia.panels', [
            'admin' => [
                'path' => 'admin',
                'middleware' => ['web', 'auth'],
                'pages' => [],
                'resources' => [StockItemResource::class],
            ],
            'branch' => [
                'path' => 'branch',
                'middleware' => ['web', 'auth'],
                'pages' => [],
            ],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('inventory_stock_items', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('owner_id')->index();
            $table->string('name');
        });

        Gate::policy(StockItem::class, StockItemPolicy::class);
        // The hosting Laravel Starter Kit owns the login route, not Panels.
        Route::get('/login', static fn (): string => 'Login')->name('login');
        Route::getRoutes()->refreshNameLookups();
    }

    private function authenticate(int $userId, bool $editor = true): void
    {
        $this->be(new GenericUser(['id' => $userId, 'name' => 'Staff', 'editor' => $editor]));
    }

    public function test_module_registers_policy_scoped_routes_once_and_keeps_panel_isolation(): void
    {
        $names = [
            'lavertia.admin.resources.stock-items' => ['GET', 'admin/resources/stock-items'],
            'lavertia.admin.resources.stock-items.create' => ['GET', 'admin/resources/stock-items/create'],
            'lavertia.admin.resources.stock-items.store' => ['POST', 'admin/resources/stock-items'],
            'lavertia.admin.resources.stock-items.show' => ['GET', 'admin/resources/stock-items/{record}'],
            'lavertia.admin.resources.stock-items.edit' => ['GET', 'admin/resources/stock-items/{record}/edit'],
            'lavertia.admin.resources.stock-items.update' => ['PUT', 'admin/resources/stock-items/{record}'],
            'lavertia.admin.resources.stock-items.destroy' => ['DELETE', 'admin/resources/stock-items/{record}'],
        ];

        foreach ($names as $name => [$method, $uri]) {
            $route = Route::getRoutes()->getByName($name);

            self::assertNotNull($route, $name);
            self::assertSame($uri, $route->uri());
            self::assertContains($method, $route->methods());
            self::assertContains('auth', $route->gatherMiddleware());
            self::assertSame(StockItemResource::class, $route->defaults['lavertiaResource']);
        }

        self::assertNull(Route::getRoutes()->getByName('lavertia.branch.resources.stock-items'));
        self::assertSame([StockItemResource::class], array_values(app(PanelRegistry::class)->resourceClasses('admin')));
    }

    public function test_guests_are_redirected_and_navigation_requires_view_any_policy(): void
    {
        $this->get('/admin/resources/stock-items')->assertRedirect();

        self::assertSame([], app(PanelRegistry::class)->navigation('admin'));

        $this->authenticate(1, false);
        self::assertSame([], app(PanelRegistry::class)->navigation('admin'));

        $this->authenticate(1, true);
        $items = app(PanelRegistry::class)->navigation('admin', 'lavertia.admin.resources.stock-items');

        self::assertCount(1, $items);
        self::assertSame('/admin/resources/stock-items', $items[0]['url']);
        self::assertTrue($items[0]['active']);
    }

    public function test_list_and_show_use_scoped_query_and_prevent_cross_tenant_ids(): void
    {
        $this->authenticate(7);
        $mine = StockItem::query()->create(['owner_id' => 7, 'name' => 'My Stock']);
        $others = StockItem::query()->create(['owner_id' => 8, 'name' => 'Other Stock']);

        $this->get('/admin/resources/stock-items', ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('props.lavertia.tree.components.1.props.rows.0.name', 'My Stock')
            ->assertJsonPath('props.lavertia.tree.components.1.props.pagination.total', 1);

        $this->get('/admin/resources/stock-items/'.$mine->id, ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('props.lavertia.tree.components.1.type', 'infolist')
            ->assertJsonPath('props.lavertia.tree.components.0.components.2.props.method', 'delete')
            ->assertJsonPath('props.lavertia.tree.components.0.components.2.props.confirm', 'Delete this record? This cannot be undone.');

        $this->get('/admin/resources/stock-items/'.$others->id, ['X-Inertia' => 'true'])->assertNotFound();
        $this->get('/admin/resources/stock-items/'.$others->id.'/edit', ['X-Inertia' => 'true'])->assertNotFound();
        $this->put('/admin/resources/stock-items/'.$others->id, ['name' => 'Compromised'])->assertNotFound();
        $this->delete('/admin/resources/stock-items/'.$others->id)->assertNotFound();
        self::assertSame('Other Stock', $others->fresh()->name);
    }

    public function test_create_validates_fields_and_assigns_tenant_from_server(): void
    {
        $this->authenticate(7);

        $this->post('/admin/resources/stock-items', ['name' => ''])->assertSessionHasErrors('name');

        $this->post('/admin/resources/stock-items', ['name' => 'Created', 'owner_id' => 999])
            ->assertRedirect();

        self::assertDatabaseHas('inventory_stock_items', ['name' => 'Created', 'owner_id' => 7]);
        self::assertDatabaseMissing('inventory_stock_items', ['owner_id' => 999]);
    }

    public function test_edit_update_and_delete_require_policy_and_scoped_record(): void
    {
        $this->authenticate(7);
        $record = StockItem::query()->create(['owner_id' => 7, 'name' => 'Before']);

        $this->get('/admin/resources/stock-items/'.$record->id.'/edit', ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('props.lavertia.tree.components.1.type', 'form');

        $this->put('/admin/resources/stock-items/'.$record->id, ['name' => 'After', 'owner_id' => 99])
            ->assertRedirect();
        self::assertSame('After', $record->fresh()->name);
        self::assertSame(7, $record->fresh()->owner_id);

        $this->authenticate(7, false);
        $this->put('/admin/resources/stock-items/'.$record->id, ['name' => 'Unauthorized'])->assertForbidden();
        $this->delete('/admin/resources/stock-items/'.$record->id)->assertForbidden();
        self::assertSame('After', $record->fresh()->name);

        $this->authenticate(7, true);
        $this->delete('/admin/resources/stock-items/'.$record->id)->assertRedirect();
        self::assertDatabaseMissing('inventory_stock_items', ['id' => $record->id]);
    }

    public function test_duplicate_provider_resource_registration_is_idempotent(): void
    {
        $registry = app(PanelRegistry::class);
        $registry->resource('admin', StockItemResource::class);
        $registry->resource('admin', StockItemResource::class);

        Route::getRoutes()->refreshNameLookups();

        self::assertCount(1, array_filter(
            Route::getRoutes()->getRoutes(),
            static fn ($route): bool => $route->getName() === 'lavertia.admin.resources.stock-items',
        ));
    }
}

final class FixtureStockModuleProvider extends ServiceProvider
{
    public function boot(PanelRegistry $panels): void
    {
        $panels->resource('admin', StockItemResource::class);
        $panels->resource('admin', StockItemResource::class);
    }
}

final class StockItem extends Model
{
    public $timestamps = false;

    protected $table = 'inventory_stock_items';

    protected $fillable = ['owner_id', 'name'];
}

final class StockItemPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return (bool) $user->editor;
    }

    public function create(Authenticatable $user): bool
    {
        return (bool) $user->editor;
    }

    public function view(Authenticatable $user, StockItem $item): bool
    {
        return (int) $user->getAuthIdentifier() === $item->owner_id;
    }

    public function update(Authenticatable $user, StockItem $item): bool
    {
        return (bool) $user->editor && $this->view($user, $item);
    }

    public function delete(Authenticatable $user, StockItem $item): bool
    {
        return (bool) $user->editor && $this->view($user, $item);
    }
}

final class StockItemResource extends Resource
{
    public static function model(): string
    {
        return StockItem::class;
    }

    public static function slug(): string
    {
        return 'stock-items';
    }

    public static function title(): string
    {
        return 'Stock items';
    }

    public function query(): Builder
    {
        return StockItem::query()->where('owner_id', auth()->id());
    }

    /** @return list<TableColumnComponent> */
    public function columns(): array
    {
        return [
            TextColumn::make('id')->label('ID')->sortable(),
            TextColumn::make('name')->label('Name')->searchable()->sortable(),
        ];
    }

    /** @return list<FieldComponent> */
    public function formFields(?Model $record): array
    {
        return [
            TextInput::make('name')->label('Name')->required()
                ->value((string) ($record?->getAttribute('name') ?? '')),
        ];
    }

    public function rules(?Model $record): array
    {
        return ['name' => ['required', 'string', 'max:50']];
    }

    public function createRecord(array $validated): Model
    {
        return StockItem::query()->create([
            'owner_id' => auth()->id(),
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
