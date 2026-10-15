<?php

declare(strict_types=1);

namespace Lavertia\Panels\Components;

use InvalidArgumentException;
use Lavertia\Support\Components\AbstractComponent;
use Lavertia\Support\Contracts\FieldComponent;

final class Select extends AbstractComponent implements FieldComponent
{
    private function __construct(private readonly string $name)
    {
    }

    public static function make(string $name): self
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', $name) !== 1) {
            throw new InvalidArgumentException('Invalid select field name.');
        }

        return (new self($name))->key($name);
    }

    public function getType(): string
    {
        return 'select';
    }

    public function getFieldName(): string
    {
        return $this->name;
    }

    public function label(string $label): static
    {
        return $this->withProp('label', $label);
    }

    /** @param array<int|string, string> $options */
    public function options(array $options): static
    {
        if (count($options) > 250) {
            throw new InvalidArgumentException('Select fields may have at most 250 options.');
        }

        foreach ($options as $key => $label) {
            if ((! is_string($key) && ! is_int($key)) || ! is_string($label)) {
                throw new InvalidArgumentException('Select options must map strings to labels.');
            }
        }

        return $this->withProp('options', $options);
    }

    public function value(string $value): static
    {
        return $this->withProp('value', $value);
    }

    public function required(bool $required = true): static
    {
        return $this->withProp('required', $required);
    }

    /** @return array<string, mixed> */
    public function getProps(): array
    {
        return ['name' => $this->name, ...parent::getProps()];
    }
}
