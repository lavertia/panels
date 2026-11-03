<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use InvalidArgumentException;
use Lavertia\Panels\Themes\PanelTheme;
use Lavertia\Panels\Themes\ThemeResolver;
use Orchestra\Testbench\TestCase;

final class ThemeResolutionTest extends TestCase
{
    public function test_default_theme_is_safe_and_deterministic(): void
    {
        $resolved = app(ThemeResolver::class)->forPanel('unknown');

        self::assertSame('default', $resolved['id']);
        self::assertSame('#b45309', $resolved['tokens']['primary']);
        self::assertCount(5, $resolved['tokens']);
    }

    public function test_theme_is_resolved_from_per_panel_configuration(): void
    {
        config()->set('lavertia.panels.admin.theme', ValidFixtureTheme::class);

        $resolved = app(ThemeResolver::class)->forPanel('admin');

        self::assertSame('test-theme', $resolved['id']);
        self::assertSame('#112233', $resolved['tokens']['primary']);
        self::assertSame('default', app(ThemeResolver::class)->forPanel('other')['id']);
    }

    public function test_standalone_pages_inherit_the_application_theme(): void
    {
        config()->set('lavertia.ui.theme', ValidFixtureTheme::class);

        self::assertSame('test-theme', app(ThemeResolver::class)->forPanel(null)['id']);
        self::assertSame('test-theme', app(ThemeResolver::class)->forPanel('admin')['id']);
    }

    public function test_invalid_theme_class_is_rejected(): void
    {
        config()->set('lavertia.panels.admin.theme', \stdClass::class);
        $this->expectException(InvalidArgumentException::class);

        app(ThemeResolver::class)->forPanel('admin');
    }

    public function test_css_injection_token_is_rejected(): void
    {
        config()->set('lavertia.panels.admin.theme', InvalidFixtureTheme::class);
        $this->expectException(InvalidArgumentException::class);

        app(ThemeResolver::class)->forPanel('admin');
    }
}

final class ValidFixtureTheme implements PanelTheme
{
    public function id(): string
    {
        return 'test-theme';
    }

    public function tokens(): array
    {
        return [
            'primary' => '#112233',
            'primary-foreground' => '#ffffff',
            'sidebar-primary' => '#112233',
            'sidebar-primary-foreground' => '#ffffff',
            'ring' => '#445566',
        ];
    }
}

final class InvalidFixtureTheme implements PanelTheme
{
    public function id(): string
    {
        return 'invalid';
    }

    public function tokens(): array
    {
        return [
            ...(new ValidFixtureTheme)->tokens(),
            'primary' => 'url(javascript:alert(1))',
        ];
    }
}
