<?php

declare(strict_types=1);

namespace Lavertia\Panels\Components;

use InvalidArgumentException;
use Lavertia\Support\Components\AbstractComponent;
use Lavertia\Support\Components\SupportsComponents;
use Lavertia\Support\Contracts\InfolistComponent;

final class Infolist extends AbstractComponent implements InfolistComponent
{
    use SupportsComponents;

    private function __construct(private readonly string $name)
    {
    }

    public static function make(string $name): self
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,127}$/D', $name) !== 1) {
            throw new InvalidArgumentException('Invalid infolist name.');
        }

        return (new self($name))->key($name);
    }

    public function getType(): string
    {
        return 'infolist';
    }

    public function getInfolistName(): string
    {
        return $this->name;
    }

    /** @return array<string, mixed> */
    public function getProps(): array
    {
        return ['name' => $this->name, ...parent::getProps()];
    }
}
