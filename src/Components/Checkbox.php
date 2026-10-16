<?php

declare(strict_types=1);

namespace Lavertia\Panels\Components;

use InvalidArgumentException;
use Lavertia\Support\Components\AbstractComponent;
use Lavertia\Support\Contracts\FieldComponent;

final class Checkbox extends AbstractComponent implements FieldComponent
{
    private function __construct(private readonly string $name) {}

    public static function make(string $name): self
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', $name) !== 1) {
            throw new InvalidArgumentException('Invalid checkbox field name.');
        }

        return (new self($name))->key($name);
    }

    public function getType(): string
    {
        return 'checkbox';
    }

    public function getFieldName(): string
    {
        return $this->name;
    }

    public function label(string $label): static
    {
        return $this->withProp('label', $label);
    }

    public function checked(bool $checked = true): static
    {
        return $this->withProp('checked', $checked);
    }

    /** @return array<string, mixed> */
    public function getProps(): array
    {
        return ['name' => $this->name, ...parent::getProps()];
    }
}
