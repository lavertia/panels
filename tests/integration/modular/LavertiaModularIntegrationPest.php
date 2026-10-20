<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Inventory\Lavertia\Pages\InventoryOverview;
use Modules\Inventory\Models\StockItem;

beforeEach(function (): void {
    $this->withoutVite();
    // Exercise authorization and validation without issuing a browser CSRF cookie.
    $this->withoutMiddleware(PreventRequestForgery::class);
});

it('registers module pages behind existing panel middleware', function (): void {
    $route = Route::getRoutes()->getByName('lavertia.admin.inventory.overview');

    expect($route)->not->toBeNull();
    expect($route->uri())->toBe('admin/inventory/overview');
    expect($route->getActionName())->toBe(InventoryOverview::class);
    expect($route->gatherMiddleware())->toContain('web', 'auth');

    expect(Route::getRoutes()->getByName('lavertia.admin.inventory.reports.daily-stock'))
        ->not->toBeNull();
});

it('keeps module navigation scoped to the owning Panel', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/admin/inventory/overview')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('lavertia/page')
            ->where('lavertia.navigation.0.label', 'Inventory')
            ->where('lavertia.navigation.0.group', 'Operations')
            ->where('lavertia.navigation.0.active', true)
            ->where('lavertia.navigation.0.url', '/admin/inventory/overview')
            ->etc());
});

it('renders only visible module dashboard widgets in a dedicated Inventory Panel', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/inventory/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('lavertia/page')
            ->where('lavertia.navigation.0.label', 'Inventory dashboard')
            ->where('lavertia.navigation.0.active', true)
            ->where('lavertia.tree.components.0.type', 'stats-grid')
            ->missing('lavertia.tree.components.1')
            ->etc());
});

it('guards the modular Inventory dashboard from guests', function (): void {
    $this->get('/inventory/dashboard')->assertRedirect(route('login'));
});

it('protects module pages from unauthenticated visitors', function (): void {
    $this->get('/admin/inventory/overview')
        ->assertRedirect(route('login'));
});

it('renders module-owned widgets for signed-in users', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/admin/inventory/overview')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('lavertia/page')
            ->where('lavertia.version', 1)
            ->where('lavertia.tree.components.0.type', 'text')
            ->etc());
});

it('registers module Resource CRUD routes with inherited auth', function (): void {
    $index = Route::getRoutes()->getByName('lavertia.admin.resources.stocks');
    $store = Route::getRoutes()->getByName('lavertia.admin.resources.stocks.store');
    $update = Route::getRoutes()->getByName('lavertia.admin.resources.stocks.update');

    expect($index)->not->toBeNull();
    expect($index->gatherMiddleware())->toContain('web', 'auth');
    expect($store->methods())->toContain('POST');
    expect($update->methods())->toContain('PUT');
});

it('keeps Resource routes behind host authentication', function (): void {
    $this->get('/admin/resources/stocks')->assertRedirect(route('login'));
    $this->post('/admin/resources/stocks', ['name' => 'Guest record'])->assertRedirect(route('login'));
});

it('runs scoped Resource CRUD and hides another users records', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    StockItem::query()->create(['user_id' => $other->id, 'name' => 'Private item']);

    $this->actingAs($owner)
        ->post('/admin/resources/stocks', ['name' => 'Created item', 'user_id' => $other->id])
        ->assertRedirect();

    $mine = StockItem::query()->where('user_id', $owner->id)->firstOrFail();
    $otherRecord = StockItem::query()->where('user_id', $other->id)->firstOrFail();

    expect($mine->name)->toBe('Created item');

    $this->actingAs($owner)
        ->get('/admin/resources/stocks')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('lavertia/page')
            ->where('lavertia.tree.components.1.type', 'table')
            ->where('lavertia.tree.components.1.props.pagination.total', 1)
            ->etc());

    $this->get('/admin/resources/stocks/'.$otherRecord->id)->assertNotFound();
    $this->put('/admin/resources/stocks/'.$otherRecord->id, ['name' => 'Attack'])->assertNotFound();
    $this->delete('/admin/resources/stocks/'.$otherRecord->id)->assertNotFound();
    expect($otherRecord->fresh()->name)->toBe('Private item');

    $this->put('/admin/resources/stocks/'.$mine->id, ['name' => 'Updated item'])
        ->assertRedirect();
    expect($mine->fresh()->name)->toBe('Updated item');

    $this->delete('/admin/resources/stocks/'.$mine->id)->assertRedirect();
    expect(StockItem::query()->whereKey($mine->id)->exists())->toBeFalse();
});
