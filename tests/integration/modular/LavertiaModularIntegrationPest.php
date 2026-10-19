<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Inventory\Lavertia\Pages\InventoryOverview;

beforeEach(function (): void {
    $this->withoutVite();
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
