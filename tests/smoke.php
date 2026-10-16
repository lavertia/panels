<?php

declare(strict_types=1);

foreach (
    [
        'Lavertia\\Panels\\' => __DIR__.'/../src/',
        'Lavertia\\Support\\' => __DIR__.'/../../support/src/',
    ] as $prefix => $directory
) {
    spl_autoload_register(static function (string $class) use ($prefix, $directory): void {
        if (! str_starts_with($class, $prefix)) {
            return;
        }

        $path = $directory.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

        if (is_file($path)) {
            require $path;
        }
    });
}

use Lavertia\Panels\Components\Infolist;
use Lavertia\Panels\Components\Table;
use Lavertia\Panels\Components\TextColumn;
use Lavertia\Panels\Components\TextEntry;
use Lavertia\Support\Components\Card;
use Lavertia\Support\Components\Column;

$tree = Column::make([
    Card::make([
        Infolist::make('organization')->components([
            TextEntry::make('name')->value('Fenix'),
        ]),
        Table::make('users')
            ->columns([TextColumn::make('name')])
            ->rows([['name' => 'Sara']]),
    ]),
])->toArray();

assert($tree['components'][0]['components'][1]['key'] === 'root/card-0/users');
assert($tree['components'][0]['components'][1]['props']->rows[0]->name === 'Sara');

echo "Lavertia Panels smoke test passed\n";
