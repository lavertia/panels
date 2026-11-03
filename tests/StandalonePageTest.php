<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use Illuminate\Support\Facades\Route;
use Lavertia\Panels\Pages\Page;
use Lavertia\Support\Components\Text;
use Lavertia\Support\Contracts\Component;
use Orchestra\Testbench\TestCase;

final class StandalonePageTest extends TestCase
{
    public function test_page_renders_through_inertia_without_any_panel(): void
    {
        config()->set('lavertia.ui.kit', 'shadcn');
        config()->set('lavertia.panels', []);

        Route::middleware('web')
            ->get('/public-demo', StandaloneFixturePage::class)
            ->name('lavertia.marketing.demo');

        $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get('/public-demo')
            ->assertOk()
            ->assertJsonPath('component', 'lavertia/page')
            ->assertJsonPath('props.lavertia.uiKit.id', 'shadcn')
            ->assertJsonPath('props.lavertia.navigation', [])
            ->assertJsonPath('props.lavertia.tree.components.0.type', 'text');
    }

    public function test_page_may_override_the_project_ui_kit(): void
    {
        config()->set('lavertia.ui.kit', 'shadcn');

        Route::middleware('web')->get('/page-kit-demo', OverriddenKitFixturePage::class);

        $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get('/page-kit-demo')
            ->assertOk()
            ->assertJsonPath('props.lavertia.uiKit.id', 'vendor.marketing');
    }
}

final class StandaloneFixturePage extends Page
{
    /** @return list<Component> */
    protected function components(): array
    {
        return [Text::make('Public page without Panel')];
    }
}

final class OverriddenKitFixturePage extends Page
{
    protected static function uiKit(): ?string
    {
        return 'vendor.marketing';
    }

    /** @return list<Component> */
    protected function components(): array
    {
        return [Text::make('Custom UI Kit')];
    }
}
