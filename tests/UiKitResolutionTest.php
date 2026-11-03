<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use InvalidArgumentException;
use Lavertia\Panels\Themes\UiKitResolver;
use Orchestra\Testbench\TestCase;

final class UiKitResolutionTest extends TestCase
{
    public function test_the_built_in_ui_is_selected_by_default(): void
    {
        self::assertSame(['id' => 'default'], app(UiKitResolver::class)->forPanel(null));
    }

    public function test_global_default_is_inherited_by_panels_and_standalone_pages(): void
    {
        config()->set('lavertia.ui.kit', 'shadcn');

        self::assertSame(['id' => 'shadcn'], app(UiKitResolver::class)->forPanel(null));
        self::assertSame(['id' => 'shadcn'], app(UiKitResolver::class)->forPanel('admin'));
    }

    public function test_panel_and_page_overrides_have_documented_precedence(): void
    {
        config()->set('lavertia.ui.kit', 'shadcn');
        config()->set('lavertia.panels.admin.ui_kit', 'headless');

        self::assertSame(['id' => 'headless'], app(UiKitResolver::class)->forPanel('admin'));
        self::assertSame(['id' => 'vendor.marketing'], app(UiKitResolver::class)->forPanel('admin', 'vendor.marketing'));
        self::assertSame(['id' => 'shadcn'], app(UiKitResolver::class)->forPanel(null));
    }

    public function test_invalid_kit_identifiers_are_rejected_before_serialization(): void
    {
        config()->set('lavertia.ui.kit', 'https://bad.example/script');

        $this->expectException(InvalidArgumentException::class);
        app(UiKitResolver::class)->forPanel(null);
    }

    public function test_invalid_explicit_panel_override_is_not_silently_ignored(): void
    {
        config()->set('lavertia.panels.admin.ui_kit', '');

        $this->expectException(InvalidArgumentException::class);
        app(UiKitResolver::class)->forPanel('admin');
    }
}
