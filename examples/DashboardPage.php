<?php

declare(strict_types=1);

namespace App\Lavertia\Pages;

use Lavertia\Panels\Components\Action;
use Lavertia\Panels\Components\Infolist;
use Lavertia\Panels\Components\Table;
use Lavertia\Panels\Components\TextColumn;
use Lavertia\Panels\Components\TextEntry;
use Lavertia\Panels\Pages\Page;
use Lavertia\Support\Components\Card;
use Lavertia\Support\Components\Column;
use Lavertia\Support\Components\Text;
use Lavertia\Support\Contracts\Component;

final class DashboardPage extends Page
{
    /** @return list<Component> */
    protected function components(): array
    {
        return [
            Column::make([
                Text::make('Welcome to Lavertia'),
                Card::make([
                    Infolist::make('organization')->components([
                        TextEntry::make('name')->label('Organization')->value('Fenix Software'),
                    ]),
                    Table::make('team')->columns([
                        TextColumn::make('name')->label('Name'),
                        TextColumn::make('email')->label('Email'),
                    ])->rows([
                        ['name' => 'Sara', 'email' => 'sara@example.com'],
                        ['name' => 'Ahmad', 'email' => 'ahmad@example.com'],
                    ]),
                    Action::make('home')->label('Home')->url('/'),
                ]),
            ]),
        ];
    }
}
