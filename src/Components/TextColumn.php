<?php

declare(strict_types=1);

namespace Lavertia\Panels\Components;

use InvalidArgumentException;
use Lavertia\Support\Components\AbstractComponent;
use Lavertia\Support\Contracts\TableColumnComponent;

final class TextColumn extends AbstractComponent implements TableColumnComponent
{
    private function __construct(private readonly string $name)
    {
    }

    public static function make(string $name): self
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,127}$/D', $name) !== 1) {
            throw new InvalidArgumentException('Invalid table column name.');
        }

        return (new self($name))->key($name);
    }

    public function getType(): string
    {
        return 'text-column';
    }

    public function getColumnName(): string
    {
        return $this->name;
    }

    public function label(string $label): static
    {
        return $this->withProp('label', $label);
    }

    /** @return array<string, mixed> */
    public function getProps(): array
    {
        return ['name' => $this->name, 'label' => $this->name, ...parent::getProps()];
    }
}
