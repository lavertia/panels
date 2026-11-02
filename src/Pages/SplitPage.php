<?php

declare(strict_types=1);

namespace Lavertia\Panels\Pages;

use Lavertia\Support\Components\Column;
use Lavertia\Support\Contracts\Component;

/**
 * Responsive two-region presentation. Each region contains ordinary Lavertia
 * Components, not raw HTML. Supports public, auth and panel routes equally.
 */
abstract class SplitPage extends Page
{
    /** @return list<Component> */
    abstract protected function leftComponents(): array;

    /** @return list<Component> */
    abstract protected function rightComponents(): array;

    /** @return list<Component> */
    protected function components(): array
    {
        return [];
    }

    protected function splitRatio(): string
    {
        return 'balanced';
    }

    /** @return array{variant: string, maxWidth: string, ratio: string} */
    protected function presentation(): array
    {
        return [
            'variant' => 'split',
            'maxWidth' => 'full',
            'ratio' => $this->splitRatio(),
        ];
    }

    /** @return array{left: array<string, mixed>, right: array<string, mixed>} */
    protected function regions(): array
    {
        return [
            'left' => Column::make($this->leftComponents())->toArray(),
            'right' => Column::make($this->rightComponents())->toArray(),
        ];
    }
}
