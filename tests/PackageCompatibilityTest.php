<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use Lavertia\Forms\Components\Form as CanonicalForm;
use Lavertia\Forms\Components\TextInput as CanonicalTextInput;
use Lavertia\Panels\Components\Form;
use Lavertia\Panels\Components\Select;
use Lavertia\Panels\Components\Table;
use Lavertia\Panels\Components\TextColumn;
use Lavertia\Panels\Components\TextInput;
use Lavertia\Tables\Components\Table as CanonicalTable;
use Lavertia\Tables\Components\TextColumn as CanonicalTextColumn;
use PHPUnit\Framework\TestCase;

final class PackageCompatibilityTest extends TestCase
{
    public function test_legacy_panels_form_namespace_is_a_php_alias_to_canonical_forms(): void
    {
        $old = Form::make('profile')
            ->action('/profiles')
            ->components([TextInput::make('name')->required()]);

        $new = CanonicalForm::make('profile')
            ->action('/profiles')
            ->components([CanonicalTextInput::make('name')->required()]);

        self::assertInstanceOf(CanonicalForm::class, $old);
        self::assertInstanceOf(Form::class, $new);
        self::assertEquals($new->toArray(), $old->toArray());
        self::assertSame('root/name', $old->toArray()['components'][0]['key']);
    }

    public function test_legacy_tables_are_identical_to_canonical_components(): void
    {
        $legacy = Table::make('members')
            ->columns([TextColumn::make('name')->searchable()])
            ->rows([['name' => 'Maya']]);

        $canonical = CanonicalTable::make('members')
            ->columns([CanonicalTextColumn::make('name')->searchable()])
            ->rows([['name' => 'Maya']]);

        self::assertInstanceOf(CanonicalTable::class, $legacy);
        self::assertInstanceOf(Table::class, $canonical);
        self::assertSame($canonical->toArray(), $legacy->toArray());
    }

    public function test_old_and_new_fields_can_be_composed_together(): void
    {
        $fields = Form::make('account')->components([
            TextInput::make('name'),
            CanonicalTextInput::make('email'),
            Select::make('role')->options(['user' => 'User']),
        ]);

        self::assertSame(3, count($fields->toArray()['components']));
    }
}
