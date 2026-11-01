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
use Lavertia\Panels\Plugins\PanelPlugin;
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
        self::assertSame('dark', $appearance['themeMode']);
        self::assertSame('top', $appearance['navigationPosition']);
        self::assertSame('20rem', $appearance['sidebarWidth']);
        self::assertFalse($appearance['breadcrumbs']);
        self::assertTrue($appearance['stickyHeader']);
        self::assertSame('lg', $appearance['borderRadius']);
        self::assertSame('2xl', $appearance['contentWidth']);
        self::assertFalse($appearance['mobileNavigation']);
        self::assertSame('both', $appearance['brandLogoPlacement']);
        self::assertSame('full', $appearance['brandLogoDisplay']);
        self::assertSame('topbar', $appearance['tenantSwitcherPlacement']);
        self::assertTrue($appearance['globalSearch']);
        self::assertTrue($appearance['notifications']);
        self::assertSame('Help', $appearance['footerLinks'][0]['label']);
        self::assertSame('Account', $appearance['userMenuLinks'][0]['label']);
        self::assertNull(config('lavertia.panels.admin'));

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

        $rejected = 0;

        foreach ([
            fn () => Panel::make()->primaryColor('red'),
            fn () => Panel::make()->brandLogo('//untrusted.example/logo.svg'),
            fn () => Panel::make()->brandLogo('/../secret.svg'),
            fn () => Panel::make()->brandLogo('javascript:alert(1)'),
            fn () => Panel::make()->sidebarVariant('invalid'),
            fn () => Panel::make()->id('../admin'),
            fn () => Panel::make()->themeMode('auto-exec'),
            fn () => Panel::make()->sidebarWidth('100vw'),
            fn () => Panel::make()->contentWidth('<iframe>'),
            fn () => Panel::make()->navigationPosition('hidden'),
            fn () => Panel::make()->brandLogoDisplay('large-script'),
            fn () => Panel::make()->brandLogoPlacement('footer'),
            fn () => Panel::make()->tenantSwitcherPlacement('hidden'),
            fn () => Panel::make()->footerLinks([['label' => 'Bad', 'url' => 'javascript:alert(1)']]),
            fn () => Panel::make()->userMenuLinks([['label' => 'Bad', 'url' => '//evil.test']]),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Expected an invalid Panel option to be rejected.');
            } catch (InvalidArgumentException) {
                $rejected++;
            }
        }

        self::assertSame(15, $rejected);
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
            ->themeMode('dark')
            ->navigationPosition('top')
            ->sidebarWidth('20rem')
            ->breadcrumbs(false)
            ->stickyHeader()
            ->borderRadius('lg')
            ->contentWidth('2xl')
            ->mobileNavigation(false)
            ->brandLogoPlacement('both')
            ->brandLogoDisplay('full')
            ->tenantSwitcherPlacement('topbar')
            ->globalSearch()
            ->notifications()
            ->footerLinks([['label' => 'Help', 'url' => 'https://example.com/help', 'external' => true]])
            ->userMenuLinks([['label' => 'Account', 'url' => '/account']])
            ->plugins([new ExamplePanelPlugin])
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

final class ExamplePanelPlugin implements PanelPlugin
{
    public function register(PanelRegistry $registry, string $panelId): void
    {
        $registry->navigationItem($panelId, NavigationItem::make('provider-example', 'Example'));
    }
}
