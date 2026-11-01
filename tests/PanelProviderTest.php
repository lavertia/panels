<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;
use Lavertia\Panels\Components\Action;
use Lavertia\Panels\Components\PageHeader;
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

    public function test_navigation_icon_and_badge_are_validated_and_serialized(): void
    {
        $item = NavigationItem::make('provider-example', 'Example')
            ->icon('receipt')
            ->badge('New');

        $link = $item->forPanel('admin', 'lavertia.admin.provider-example');

        self::assertNotNull($link);
        self::assertSame('receipt', $link['icon']);
        self::assertSame('New', $link['badge']);
        self::assertTrue($link['active']);
        self::assertJson(json_encode($link, JSON_THROW_ON_ERROR));

        $legacy = NavigationItem::make('provider-example', 'Example')
            ->forPanel('admin', 'lavertia.admin.provider-example');

        self::assertNull($legacy['icon']);
        self::assertNull($legacy['badge']);

        $fromArray = NavigationItem::fromArray([
            'slug' => 'provider-example',
            'label' => 'Example',
            'icon' => 'receipt',
            'badge' => 'New',
        ]);

        self::assertTrue($item->sameConfiguration($fromArray));
        self::assertFalse($item->sameConfiguration(NavigationItem::make('provider-example', 'Example')));

        foreach ([
            fn () => $item->icon('alert<script>'),
            fn () => $item->icon('unknown-icon'),
            fn () => $item->badge('<img>'),
            fn () => $item->badge(''),
            fn () => $item->badge(str_repeat('A', 21)),
            fn () => NavigationItem::fromArray(['slug' => 'provider-example', 'label' => 'Example', 'icon' => 100]),
            fn () => NavigationItem::fromArray(['slug' => 'provider-example', 'label' => 'Example', 'badge' => []]),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Unsafe navigation metadata was accepted.');
            } catch (InvalidArgumentException) {
                // Expected.
            }
        }
    }

    public function test_collapsible_groups_sort_and_reject_unsafe_configuration(): void
    {
        $groups = Panel::make('admin')->navigationGroups([
            ['label' => 'Billing', 'sort' => 20, 'collapsible' => true, 'collapsedByDefault' => true],
            ['label' => 'Management', 'sort' => 10],
        ])->configuration()['navigation_groups'];

        self::assertSame('Management', $groups[0]['label']);
        self::assertSame('Billing', $groups[1]['label']);
        self::assertTrue($groups[1]['collapsedByDefault']);
        self::assertJson(json_encode($groups, JSON_THROW_ON_ERROR));

        foreach ([
            fn () => Panel::make()->navigationGroups([['label' => 'Duplicate'], ['label' => 'Duplicate']]),
            fn () => Panel::make()->navigationGroups([['label' => 'Bad', 'collapsedByDefault' => true]]),
            fn () => Panel::make()->navigationGroups([['label' => '<script>']]),
            fn () => Panel::make()->navigationGroups([['label' => 'Bad', 'collapsible' => 'yes']]),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Invalid group configuration was accepted.');
            } catch (InvalidArgumentException) {
                // Expected.
            }
        }
    }

    public function test_child_navigation_requires_an_authorized_registered_parent(): void
    {
        $registry = app(PanelRegistry::class);

        Route::get('admin/child-example', ProviderExamplePage::class)
            ->name('lavertia.admin.child-example');
        Route::get('admin/private-parent', ProviderExamplePage::class)
            ->name('lavertia.admin.private-parent');
        Route::get('admin/private-child', ProviderExamplePage::class)
            ->name('lavertia.admin.private-child');
        Route::getRoutes()->refreshNameLookups();

        Gate::define('private-navigation', static fn (): bool => false);

        $registry->navigationItem('admin',
            NavigationItem::make('child-example', 'Child')->parent('provider-example')->sort(10));
        $registry->navigationItem('admin',
            NavigationItem::make('private-parent', 'Private')->ability('private-navigation'));
        $registry->navigationItem('admin',
            NavigationItem::make('private-child', 'Private child')->parent('private-parent'));

        $links = $registry->navigation('admin', 'lavertia.admin.child-example');

        self::assertCount(2, $links);
        self::assertSame('provider-example', $links[0]['slug']);
        self::assertTrue($links[0]['descendantActive']);
        self::assertSame('provider-example', $links[1]['parentSlug']);
        self::assertTrue($links[1]['active']);
        self::assertSame('child-example', $links[1]['slug']);

        foreach ([
            fn () => NavigationItem::make('child', 'Child')->parent('child'),
            fn () => NavigationItem::make('child', 'Child')->parent('//evil'),
            fn () => NavigationItem::fromArray(['slug' => 'child', 'label' => 'Child', 'parent' => 123]),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Invalid parent slug was accepted.');
            } catch (InvalidArgumentException) {
                // Expected.
            }
        }
    }

    public function test_page_header_tabs_breadcrumbs_and_actions_serialize_safely(): void
    {
        $header = PageHeader::make('Reports')
            ->breadcrumbs([
                ['label' => 'Home', 'url' => '/admin/dashboard'],
                ['label' => 'Reports'],
            ])
            ->tabs([
                ['label' => 'Overview', 'url' => '/admin/reports', 'active' => true],
                ['label' => 'Billing', 'url' => '/admin/billing/reports'],
            ])
            ->actions([
                Action::make('billing-reports')->label('Billing reports')->url('/admin/billing/reports'),
            ]);

        $data = $header->toArray();

        self::assertSame('/admin/dashboard', $data['props']['breadcrumbs'][0]['url']);
        self::assertNull($data['props']['breadcrumbs'][1]['url']);
        self::assertTrue($data['props']['tabs'][0]['active']);
        self::assertCount(1, $data['components']);
        self::assertJson(json_encode($data, JSON_THROW_ON_ERROR));

        foreach ([
            fn () => PageHeader::make('Bad')->tabs([['label' => 'X', 'url' => '//evil.test']]),
            fn () => PageHeader::make('Bad')->tabs([['label' => 'X', 'url' => 'javascript:alert(1)']]),
            fn () => PageHeader::make('Bad')->tabs([
                ['label' => 'A', 'url' => '/a', 'active' => true],
                ['label' => 'B', 'url' => '/b', 'active' => true],
            ]),
            fn () => PageHeader::make('Bad')->breadcrumbs([['label' => '<script>']]),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Unsafe Page Header configuration was accepted.');
            } catch (InvalidArgumentException) {
                // Expected.
            }
        }
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
