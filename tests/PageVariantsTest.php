<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use InvalidArgumentException;
use Illuminate\Support\Facades\Route;
use Lavertia\Panels\Components\Section;
use Lavertia\Panels\Navigation\NavigationItem;
use Lavertia\Panels\Pages\CardPage;
use Lavertia\Panels\Pages\FlatPage;
use Lavertia\Panels\Pages\Page;
use Lavertia\Panels\Pages\SplitPage;
use Lavertia\Panels\Panel;
use Lavertia\Panels\PanelProvider;
use Lavertia\Panels\PanelRegistry;
use Lavertia\Panels\PanelsServiceProvider;
use Orchestra\Testbench\TestCase;

final class PageVariantsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PanelsServiceProvider::class, PageVariantsPanelProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');

        Route::get('standalone/about', PublicExamplePage::class)->name('public.about');
        Route::get('standalone/split', ExampleSplitPage::class)->name('public.split');
        Route::get('standalone/blocked', ForbiddenExamplePage::class)->name('public.blocked');
    }

    public function test_card_inherits_page_lifecycle_and_safely_defaults_to_noindex(): void
    {
        $this->withHeader('X-Inertia', 'true')
            ->get('/demo/card')
            ->assertOk()
            ->assertJsonPath('component', 'lavertia/page')
            ->assertJsonPath('props.lavertia.presentation.variant', 'card')
            ->assertJsonPath('props.lavertia.presentation.maxWidth', 'md')
            ->assertJsonPath('props.lavertia.seo.robots', 'noindex,nofollow')
            ->assertJsonPath('props.lavertia.tree.components.0.props.title', 'Mounted component');
    }

    public function test_split_page_renders_named_component_regions_outside_panel(): void
    {
        $this->withHeader('X-Inertia', 'true')
            ->get('/standalone/split')
            ->assertOk()
            ->assertJsonPath('component', 'lavertia/standalone')
            ->assertJsonPath('props.lavertia.presentation.variant', 'split')
            ->assertJsonPath('props.lavertia.presentation.ratio', 'start-wide')
            ->assertJsonPath('props.lavertia.regions.left.components.0.props.title', 'Left column')
            ->assertJsonPath('props.lavertia.regions.right.components.0.props.title', 'Right column')
            ->assertJsonPath('props.lavertia.seo.robots', 'noindex,nofollow');
    }

    public function test_public_page_opt_in_seo_and_never_inherits_a_panel(): void
    {
        $this->withHeader('X-Inertia', 'true')
            ->get('/standalone/about')
            ->assertOk()
            ->assertJsonPath('component', 'lavertia/standalone')
            ->assertJsonPath('props.lavertia.presentation.variant', 'flat')
            ->assertJsonPath('props.lavertia.seo.title', 'Public article')
            ->assertJsonPath('props.lavertia.seo.robots', 'index,follow');

        self::assertNull(app(PanelRegistry::class)->panelForRoute('public.about'));
    }

    public function test_can_view_denies_route_and_navigation(): void
    {
        $this->get('/standalone/blocked')->assertForbidden();
        $this->get('/demo/secret')->assertForbidden();

        $links = app(PanelRegistry::class)->navigation('demo');

        self::assertSame([], array_filter($links, static fn (array $link): bool => $link['slug'] === 'secret'));
    }

    public function test_invalid_page_presentation_and_seo_are_rejected(): void
    {
        foreach ([InvalidPresentationPage::class, InvalidSeoPage::class] as $class) {
            Route::get('standalone/invalid-'.$class::slug(), $class);

            $this->expectException(InvalidArgumentException::class);
            $this->withHeader('X-Inertia', 'true')
                ->get('/standalone/invalid-'.$class::slug());
        }
    }
}

final class PageVariantsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->id('demo')
            ->middleware(['web'])
            ->pages([ExampleCardPage::class, ForbiddenExamplePage::class])
            ->navigationItems([
                NavigationItem::make('secret', 'Secret'),
            ]);
    }
}

final class ExampleCardPage extends CardPage
{
    private bool $mounted = false;

    public static function slug(): string
    {
        return 'card';
    }

    protected function mount(): void
    {
        $this->mounted = true;
    }

    protected function components(): array
    {
        return [Section::make($this->mounted ? 'Mounted component' : 'Unmounted')];
    }
}

final class ExampleSplitPage extends SplitPage
{
    protected function splitRatio(): string
    {
        return 'start-wide';
    }

    protected function leftComponents(): array
    {
        return [Section::make('Left column')];
    }

    protected function rightComponents(): array
    {
        return [Section::make('Right column')];
    }
}

final class PublicExamplePage extends FlatPage
{
    protected function seo(): array
    {
        return [
            'title' => 'Public article',
            'description' => 'Public demo',
            'robots' => 'index,follow',
            'canonical' => '/standalone/about',
        ];
    }

    protected function components(): array
    {
        return [];
    }
}

final class ForbiddenExamplePage extends Page
{
    public static function slug(): string
    {
        return 'secret';
    }

    public static function canView(): bool
    {
        return false;
    }

    protected function components(): array
    {
        return [];
    }
}

final class InvalidPresentationPage extends FlatPage
{
    protected function presentation(): array
    {
        return ['variant' => 'iframe', 'maxWidth' => 'lg'];
    }

    protected function components(): array
    {
        return [];
    }
}

final class InvalidSeoPage extends FlatPage
{
    protected function seo(): array
    {
        return [
            'title' => 'Unsafe',
            'robots' => 'index,follow',
            'description' => null,
            'canonical' => '//evil.example/',
        ];
    }

    protected function components(): array
    {
        return [];
    }
}
