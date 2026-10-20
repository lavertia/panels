<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use InvalidArgumentException;
use Lavertia\Panels\Components\Action;
use PHPUnit\Framework\TestCase;

final class ClientActionDescriptorTest extends TestCase
{
    public function test_named_client_action_serializes_only_json_safe_metadata(): void
    {
        $node = Action::make('printReceipt')
            ->label('Print')
            ->clientAction('receipts.print', ['format' => 'a4', 'copies' => 2])
            ->toArray();

        self::assertSame('action', $node['type']);
        self::assertSame('receipts.print', $node['props']->clientAction);
        self::assertSame('a4', $node['props']->clientParams->format);
        self::assertSame(2, $node['props']->clientParams->copies);
        self::assertArrayNotHasKey('href', (array) $node['props']);
        self::assertJson(json_encode($node, JSON_THROW_ON_ERROR));
    }

    public function test_unsafe_client_actions_are_rejected(): void
    {
        $rejected = 0;

        foreach ([
            fn () => Action::make('print')->clientAction('javascript:alert(1)'),
            fn () => Action::make('print')->clientAction('bad action'),
            fn () => Action::make('print')->clientAction('receipts.print', [['bad-list']]),
            fn () => Action::make('print')->clientAction('receipts.print', ['__proto__' => 'bad']),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Expected unsafe client action descriptor to fail.');
            } catch (InvalidArgumentException) {
                $rejected++;
            }
        }

        self::assertSame(4, $rejected);
    }
}
