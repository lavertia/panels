<?php

declare(strict_types=1);

namespace Lavertia\Panels\Components;

use InvalidArgumentException;
use Lavertia\Support\Components\AbstractComponent;
use Lavertia\Support\Contracts\FieldComponent;

final class Textarea extends AbstractComponent implements FieldComponent
{
    private function __construct(private readonly string $name) {}

    public static function make(string $name): self
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', $name) !== 1) {
            throw new InvalidArgumentException('Invalid textarea field name.');
        }

        return (new self($name))->key($name);
    }

    public function getType(): string
    {
        return 'textarea';
    }

    public function getFieldName(): string
    {
        return $this->name;
    }

    public function label(string $label): static
    {
        return $this->withProp('label', $label);
    }

    public function value(string $value): static
    {
        return $this->withProp('value', $value);
    }

    public function required(bool $required = true): static
    {
        return $this->withProp('required', $required);
    }

    public function rows(int $rows): static
    {
        if ($rows < 2 || $rows > 15) {
            throw new InvalidArgumentException('Textarea rows out of bounds.');
        }

        return $this->withProp('rows', $rows);
    }

    /** @return array<string, mixed> */
    public function getProps(): array
    {
        return ['name' => $this->name, ...parent::getProps()];
    }
}
