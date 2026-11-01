<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use InvalidArgumentException;
use Lavertia\Panels\Localization\LocaleContext;
use Lavertia\Panels\Pages\Page;
use Lavertia\Panels\Panel;
use Lavertia\Panels\PanelProvider;
use Lavertia\Panels\PanelRegistry;
use Lavertia\Panels\PanelsServiceProvider;
use Orchestra\Testbench\TestCase;

final class LocaleLayoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        config()->set('app.locale', 'en');
    }

    protected function getPackageProviders($app): array
    {
        return [PanelsServiceProvider::class, LocaleExamplePanelProvider::class];
    }

    public function test_one_language_is_the_default_without_additional_configuration(): void
    {
        $panel = Panel::make('simple');
        $registry = app(PanelRegistry::class);

        self::assertSame([], $panel->configuration()['locales']);
        self::assertSame(['en', 'ar', 'fr'], LocaleContext::allowed($registry, 'demo'));
        self::assertSame('rtl', LocaleContext::direction('ar-LB'));
        self::assertSame('ltr', LocaleContext::direction('fr-CA'));

        $single = Panel::make('single')->locales(['ar'])->defaultLocale('ar');
        self::assertSame(['ar'], $single->configuration()['locales']);
    }

    public function test_locale_codes_and_default_language_are_validated(): void
    {
        $rejected = 0;

        foreach ([
            fn () => Panel::make()->locales([]),
            fn () => Panel::make()->locales(['en', 'EN']),
            fn () => Panel::make()->locales(['en', 'javascript:alert(1)']),
            fn () => Panel::make()->locales(['../ar']),
            fn () => Panel::make()->defaultLocale('!'),
            fn () => Panel::make()->locales(['en'])->defaultLocale('fr')->configuration(),
            fn () => Panel::make()->layout('invalid'),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Unsupported locale or layout was accepted.');
            } catch (InvalidArgumentException) {
                $rejected++;
            }
        }

        self::assertSame(7, $rejected);
    }

    public function test_panel_language_switch_is_scoped_and_page_override_is_applied(): void
    {
        $this->withHeader('X-Inertia', 'true')
            ->get('/demo/welcome?lang=ar')
            ->assertOk()
            ->assertJsonPath('props.lavertia.locale', 'ar')
            ->assertJsonPath('props.lavertia.direction', 'rtl');

        $this->withHeader('X-Inertia', 'true')
            ->get('/demo/welcome')
            ->assertOk()
            ->assertJsonPath('props.lavertia.locale', 'ar');

        $this->withHeader('X-Inertia', 'true')
            ->get('/demo/french?lang=ar')
            ->assertOk()
            ->assertJsonPath('props.lavertia.locale', 'fr')
            ->assertJsonPath('props.lavertia.direction', 'ltr');

        $this->withHeader('X-Inertia', 'true')
            ->get('/demo/welcome')
            ->assertOk()
            ->assertJsonPath('props.lavertia.locale', 'ar');
    }

    public function test_page_full_layout_is_available_to_the_panel_renderer(): void
    {
        $registry = app(PanelRegistry::class);
        $this->withHeader('X-Inertia', 'true')->get('/demo/french')->assertOk();

        self::assertSame('full', LocaleFrenchPage::layout());
        self::assertSame(['fr'], LocaleContext::allowed($registry, 'demo', LocaleFrenchPage::class));
        self::assertSame('sidebar', $registry->panel('demo')->configuration()['layout']);
    }
}

final class LocaleExamplePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->id('demo')
            ->middleware(['web'])
            ->locales(['en', 'ar', 'fr'])
            ->defaultLocale('en')
            ->pages([LocaleWelcomePage::class, LocaleFrenchPage::class]);
    }
}

final class LocaleWelcomePage extends Page
{
    public static function slug(): string
    {
        return 'welcome';
    }

    protected function components(): array
    {
        return [];
    }
}

final class LocaleFrenchPage extends Page
{
    public static function slug(): string
    {
        return 'french';
    }

    public static function locales(): ?array
    {
        return ['fr'];
    }

    public static function layout(): ?string
    {
        return 'full';
    }

    protected function components(): array
    {
        return [];
    }
}
