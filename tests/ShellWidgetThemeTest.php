<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use InvalidArgumentException;
use Lavertia\Panels\Navigation\NavigationGroup;
use Lavertia\Panels\Navigation\NavigationItem;
use Lavertia\Panels\Navigation\NavigationSlot;
use Lavertia\Panels\Pages\Page;
use Lavertia\Panels\Panel;
use Lavertia\Panels\PanelProvider;
use Lavertia\Panels\PanelRegistry;
use Lavertia\Panels\PanelsServiceProvider;
use Lavertia\Panels\Themes\PanelTheme;
use Orchestra\Testbench\TestCase;

final class ShellWidgetThemeTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PanelsServiceProvider::class, ShellExamplePanelProvider::class];
    }

    public function test_slot_widgets_are_authorized_reusable_components(): void
    {
        $registry = app(PanelRegistry::class);
        $appearance = $registry->appearance('admin');

        self::assertNotNull($appearance);
        self::assertSame('example', $appearance['theme']['id']);
        self::assertSame('#b45309', $appearance['theme']['tokens']['primary']);
        self::assertCount(2, $appearance['shellSlots']);

        $top = $appearance['shellSlots'][0];
        $footer = $appearance['shellSlots'][1];

        self::assertSame('navigation-slot', $top['type']);
        self::assertSame('topbar', $top['props']->position);
        self::assertSame('footer', $footer['props']->position);
        self::assertSame('navigation-group', $top['components'][0]['type']);

        $widget = $top['components'][0]['components'][0];

        self::assertSame('navigation-item', $widget['type']);
        self::assertTrue($widget['props']->visible);
        self::assertSame('/admin/widget-example', $widget['props']->url);

        // Shell-only Page links are not duplicated in the default Sidebar.
        self::assertSame([], $registry->navigation('admin'));
        self::assertCount(1, $registry->navigation('admin', null, true));
        self::assertJson(json_encode($appearance, JSON_THROW_ON_ERROR));
    }

    public function test_invalid_slots_and_themes_are_rejected(): void
    {
        $link = NavigationItem::make('widget-example', 'Example');
        $slot = NavigationSlot::make('top')->position('topbar')->components([$link]);

        $badTheme = new class implements PanelTheme
        {
            public function id(): string
            {
                return 'danger';
            }

            public function tokens(): array
            {
                return ['primary' => 'url(javascript:alert(1))'];
            }
        };

        $rejected = 0;

        foreach ([
            fn () => Panel::make()->theme($badTheme),
            fn () => Panel::make()->shellSlots([$slot, $slot]),
            fn () => Panel::make()->shellSlots([NavigationSlot::make('bad')->components([
                \Lavertia\Panels\Components\PageHeader::make('Bad child'),
            ])]),
            fn () => NavigationSlot::make('x')->position('javascript:alert(1)'),
            fn () => NavigationGroup::make('<script>'),
        ] as $bad) {
            try {
                $bad();
                self::fail('Unsafe shell configuration was accepted.');
            } catch (InvalidArgumentException) {
                $rejected++;
            }
        }

        self::assertSame(5, $rejected);
    }
}

final class ShellExamplePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $item = NavigationItem::make('widget-example', 'Example')->icon('house');
        $group = NavigationGroup::make('Work')->components([$item]);

        return $panel->id('admin')
            ->middleware(['web'])
            ->theme(new class implements PanelTheme
            {
                public function id(): string
                {
                    return 'example';
                }

                public function tokens(): array
                {
                    return ['primary' => '#b45309'];
                }
            })
            ->pages([ShellExamplePage::class])
            ->shellSlots([
                NavigationSlot::make('primary')->position('topbar')->components([$group]),
                NavigationSlot::make('quick')->position('footer')->components([$group]),
            ]);
    }
}

final class ShellExamplePage extends Page
{
    public static function slug(): string
    {
        return 'widget-example';
    }

    protected function components(): array
    {
        return [];
    }
}
