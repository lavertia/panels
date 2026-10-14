<?php

declare(strict_types=1);

namespace Lavertia\Panels\Components;

use InvalidArgumentException;
use Lavertia\Support\Components\AbstractComponent;
use Lavertia\Support\Contracts\FieldComponent;

final class TextInput extends AbstractComponent implements FieldComponent
{
    private function __construct(private readonly string $name)
    {
    }

    public static function make(string $name): self
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,127}$/D', $name) !== 1) {
            throw new InvalidArgumentException('Invalid field name.');
        }

        return (new self($name))->key($name);
    }

    public function getType(): string
    {
        return 'text-input';
    }

    public function getFieldName(): string
    {
        return $this->name;
    }

    public function label(string $label): static
    {
        return $this->withProp('label', $label);
    }

    public function placeholder(string $placeholder): static
    {
        return $this->withProp('placeholder', $placeholder);
    }

    public function value(string $value): static
    {
        return $this->withProp('value', $value);
    }

    /** @return array<string, mixed> */
    public function getProps(): array
    {
        return ['name' => $this->name, ...parent::getProps()];
    }
}
