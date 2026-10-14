<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use InvalidArgumentException;
use Lavertia\Panels\Components\Action;
use Lavertia\Panels\Components\Infolist;
use Lavertia\Panels\Components\Table;
use Lavertia\Panels\Components\TextColumn;
use Lavertia\Panels\Components\TextEntry;
use Lavertia\Panels\Pages\Page;
use Lavertia\Support\Components\Card;
use Lavertia\Support\Components\Text;
use Lavertia\Support\Contracts\Component;
use PHPUnit\Framework\TestCase;

final class PageCompositionTest extends TestCase
{
    public function test_table_and_infolist_can_live_in_same_card(): void
    {
        $page = new class extends Page {
            /** @return list<Component> */
            protected function components(): array
            {
                return [
                    Card::make([
                        Infolist::make('organization')->components([
                            TextEntry::make('name')->label('Name')->value('Fenix'),
                        ]),
                        Table::make('users')->columns([
                            TextColumn::make('name')->label('Name'),
                        ])->rows([['name' => 'Sara']]),
                        Text::make('Done'),
                    ]),
                ];
            }
        };

        $insideCard = $page->tree()['components'][0]['components'];

        self::assertSame('infolist', $insideCard[0]['type']);
        self::assertSame('table', $insideCard[1]['type']);
        self::assertSame('Sara', $insideCard[1]['props']->rows[0]->name);
        self::assertSame('text-column', $insideCard[1]['components'][0]['type']);
        self::assertSame('text', $insideCard[2]['type']);
    }

    public function test_unsafe_action_url_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Action::make('unsafe')->url('javascript:alert(1)');
    }

    public function test_duplicate_column_names_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Table::make('users')->columns([
            TextColumn::make('name'),
            TextColumn::make('name'),
        ]);
    }

    public function test_table_snapshots_have_a_hard_limit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Table::make('users')->rows(array_fill(0, 101, ['name' => 'Sara']));
    }
}
