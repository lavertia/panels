<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Lavertia\Panels\Navigation\NavigationItem;
use Lavertia\Panels\Pages\Page;
use Lavertia\Panels\Panel;
use Lavertia\Panels\PanelProvider;
use Lavertia\Panels\PanelRegistry;
use Lavertia\Panels\PanelsServiceProvider;
use Orchestra\Testbench\TestCase;

final class PanelProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PanelsServiceProvider::class, ExampleAdminPanelProvider::class];
    }

    public function test_provider_configures_routes_and_starter_kit_appearance(): void
    {
        $registry = app(PanelRegistry::class);
        $appearance = $registry->appearance('admin');

        self::assertNotNull($appearance);
        self::assertSame('Example Admin', $appearance['brandName']);
        self::assertSame('Workspace', $appearance['brandSubtitle']);
        self::assertSame('/images/brand.svg', $appearance['brandLogo']);
        self::assertSame('#3377cc', $appearance['primaryColor']);
        self::assertFalse($appearance['sidebarCollapsible']);
        self::assertSame('floating', $appearance['sidebarVariant']);

        $route = Route::getRoutes()->getByName('lavertia.admin.provider-example');

        self::assertNotNull($route);
        self::assertSame('admin/provider-example', $route->uri());
        self::assertContains('auth', $route->gatherMiddleware());

        $links = $registry->navigation('admin', 'lavertia.admin.provider-example');

        self::assertCount(1, $links);
        self::assertSame('/admin/provider-example', $links[0]['url']);
        self::assertTrue($links[0]['active']);
    }

    public function test_provider_settings_are_serializable_and_validated(): void
    {
        $appearance = app(PanelRegistry::class)->appearance('admin');

        self::assertJson(json_encode($appearance, JSON_THROW_ON_ERROR));

        foreach ([
            fn () => Panel::make()->primaryColor('red'),
            fn () => Panel::make()->brandLogo('//untrusted.example/logo.svg'),
            fn () => Panel::make()->brandLogo('/../secret.svg'),
            fn () => Panel::make()->brandLogo('javascript:alert(1)'),
            fn () => Panel::make()->sidebarVariant('invalid'),
            fn () => Panel::make()->id('../admin'),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Expected an invalid Panel option to be rejected.');
            } catch (InvalidArgumentException) {
                // Expected.
            }
        }
    }
}

final class ExampleAdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->brandName('Example Admin')
            ->brandSubtitle('Workspace')
            ->brandLogo('/images/brand.svg')
            ->primaryColor('#3377cc')
            ->sidebarVariant('floating')
            ->sidebarCollapsible(false)
            ->pages([ProviderExamplePage::class])
            ->navigationItems([
                NavigationItem::make('provider-example', 'Example'),
            ]);
    }
}

final class ProviderExamplePage extends Page
{
    public static function slug(): string
    {
        return 'provider-example';
    }

    protected function components(): array
    {
        return [];
    }
}
