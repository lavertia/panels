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
