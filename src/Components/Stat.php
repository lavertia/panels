<?php

declare(strict_types=1);

namespace Lavertia\Panels\Components;

use InvalidArgumentException;
use Lavertia\Support\Components\AbstractComponent;

final class Stat extends AbstractComponent
{
    public static function make(string $label, string|int|float $value): self
    {
        return (new self)
            ->withProp('label', $label)
            ->withProp('value', $value);
    }

    public function getType(): string
    {
        return 'stat';
    }

    public function description(string $description): static
    {
        return $this->withProp('description', $description);
    }

    public function tone(string $tone): static
    {
        if (! in_array($tone, ['primary', 'success', 'warning', 'neutral'], true)) {
            throw new InvalidArgumentException('Invalid statistic tone.');
        }

        return $this->withProp('tone', $tone);
    }
}
