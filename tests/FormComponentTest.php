<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use InvalidArgumentException;
use Lavertia\Panels\Components\Checkbox;
use Lavertia\Panels\Components\Form;
use Lavertia\Panels\Components\Select;
use Lavertia\Panels\Components\Textarea;
use Lavertia\Panels\Components\TextInput;
use PHPUnit\Framework\TestCase;

final class FormComponentTest extends TestCase
{
    public function test_form_submission_contract_serializes_an_approved_local_action(): void
    {
        $tree = Form::make('profile-preview')
            ->label('Profile')
            ->action('/admin/profile-demo')
            ->method('patch')
            ->submitLabel('Update')
            ->components([
                TextInput::make('name')->value('Sara')->required(),
                Select::make('role')->options(['owner' => 'Owner'])->required(),
                Textarea::make('bio')->rows(4)->required(),
                Checkbox::make('notify')->checked(),
            ])
            ->toArray();

        self::assertSame('form', $tree['type']);
        self::assertSame('/admin/profile-demo', $tree['props']->action);
        self::assertSame('patch', $tree['props']->method);
        self::assertSame('Update', $tree['props']->submitLabel);
        self::assertSame(['text-input', 'select', 'textarea', 'checkbox'], array_column($tree['components'], 'type'));
        self::assertTrue($tree['components'][0]['props']->required);
        self::assertTrue($tree['components'][2]['props']->required);
    }

    public function test_form_is_post_by_default(): void
    {
        $tree = Form::make('profile')->toArray();
        self::assertSame('post', $tree['props']->method);
        self::assertArrayNotHasKey('action', (array) $tree['props']);
    }

    public function test_select_options_accept_numeric_keys_that_php_coerces_to_integers(): void
    {
        $tree = Select::make('level')->options([0 => 'Zero', 1 => 'One'])->toArray();

        self::assertSame(['Zero', 'One'], $tree['props']->options);
    }

    public function test_form_rejects_external_action_urls(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Form::make('profile')->action('//malicious.example/collect');
    }

    public function test_form_rejects_unsafe_http_methods(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Form::make('profile')->method('get');
    }
}
