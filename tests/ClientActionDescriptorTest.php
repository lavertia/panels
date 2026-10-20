<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use InvalidArgumentException;
use Lavertia\Forms\Components\Form;
use Lavertia\Forms\Components\TextInput;
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

    public function test_modal_action_serializes_form_and_save_lifecycle(): void
    {
        $node = Action::make('editUser')
            ->label('Edit user')
            ->modal(
                Form::make('editUser')
                    ->action('/users/12')
                    ->method('patch')
                    ->components([TextInput::make('name')->value('Sara')]),
            )
            ->closeOnSuccess()
            ->toArray();

        self::assertSame('action', $node['type']);
        self::assertTrue($node['props']->modal);
        self::assertTrue($node['props']->closeOnSuccess);
        self::assertSame('form', $node['components'][0]['type']);
        self::assertSame('/users/12', $node['components'][0]['props']->action);
        self::assertSame('root/editUser/name', $node['components'][0]['components'][0]['key']);
    }

    public function test_modal_action_rejects_conflicting_behavior(): void
    {
        $rejected = 0;

        foreach ([
            fn () => Action::make('x')->url('/users')->modal(Form::make('edit')),
            fn () => Action::make('x')->clientAction('users.edit')->modal(Form::make('edit')),
            fn () => Action::make('x')->modal(Form::make('edit'))->url('/users'),
            fn () => Action::make('x')->modal(Form::make('edit'))->clientAction('users.edit'),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Expected mutually exclusive action behavior.');
            } catch (InvalidArgumentException) {
                $rejected++;
            }
        }

        self::assertSame(4, $rejected);
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
