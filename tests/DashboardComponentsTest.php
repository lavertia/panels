<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use InvalidArgumentException;
use Lavertia\Panels\Components\Action;
use Lavertia\Panels\Components\PageHeader;
use Lavertia\Panels\Components\Section;
use Lavertia\Panels\Components\Stat;
use Lavertia\Panels\Components\StatsGrid;
use Lavertia\Panels\Components\Table;
use Lavertia\Panels\Components\TextColumn;
use PHPUnit\Framework\TestCase;

final class DashboardComponentsTest extends TestCase
{
    public function test_filament_inspired_page_components_compose_without_a_resource(): void
    {
        $header = PageHeader::make('Overview')
            ->description('Demo')
            ->components([Action::make('docs')->appearance('primary')->url('https://github.com/lavertia/panels')]);

        $table = Table::make('members')
            ->label('Team members')
            ->searchable()
            ->columns([
                TextColumn::make('name')->sortable(),
                TextColumn::make('status')->badge(['Active' => 'success']),
            ])
            ->rows([['name' => 'Sara', 'status' => 'Active']]);

        $dashboard = Section::make('Directory')->components([
            StatsGrid::make([Stat::make('Members', 1)->tone('success')]),
            $table,
        ]);

        $tree = $dashboard->toArray();

        self::assertSame('section', $tree['type']);
        self::assertSame('stats-grid', $tree['components'][0]['type']);
        self::assertSame('stat', $tree['components'][0]['components'][0]['type']);
        self::assertSame('table', $tree['components'][1]['type']);
        self::assertTrue($tree['components'][1]['props']->searchable);
        self::assertSame('success', $tree['components'][1]['components'][1]['props']->badgeTones->Active);
        self::assertSame('page-header', $header->getType());
        self::assertSame('primary', $header->getComponents()[0]->getProps()['appearance']);
    }

    public function test_rejects_invalid_badge_tone(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TextColumn::make('status')->badge(['Active' => 'unsafe']);
    }

    public function test_rejects_invalid_action_appearance(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Action::make('create')->appearance('unknown');
    }
}
