<?php

declare(strict_types=1);

namespace Lavertia\Panels\Pages;

/** Centered surface, commonly used for login, verify, setup and simple forms. */
abstract class CardPage extends Page
{
    protected function cardWidth(): string
    {
        return 'md';
    }

    protected function cardPadding(): string
    {
        return 'normal';
    }

    /** @return array{variant: string, maxWidth: string, padding: string} */
    protected function presentation(): array
    {
        return [
            'variant' => 'card',
            'maxWidth' => $this->cardWidth(),
            'padding' => $this->cardPadding(),
        ];
    }
}
