<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use Lavertia\Panels\Components\Action;
use Orchestra\Testbench\TestCase;

final class ActionNavigationTest extends TestCase
{
    public function test_relative_action_can_request_a_native_new_tab(): void
    {
        $action = Action::make('preview')
            ->label('Open preview')
            ->url('/lavertia-tables-lab.html')
            ->openUrlInNewTab();

        self::assertSame('/lavertia-tables-lab.html', $action->getProps()['href']);
        self::assertTrue($action->getProps()['openInNewTab']);
    }

    public function test_new_tab_option_defaults_to_disabled(): void
    {
        $action = Action::make('dashboard')->url('/admin');

        self::assertArrayNotHasKey('openInNewTab', $action->getProps());
    }

    public function test_new_tab_option_can_be_disabled_explicitly(): void
    {
        $action = Action::make('dashboard')
            ->url('/admin')
            ->openUrlInNewTab()
            ->openUrlInNewTab(false);

        self::assertFalse($action->getProps()['openInNewTab']);
    }
}
