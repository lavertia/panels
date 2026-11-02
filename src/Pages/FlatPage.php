<?php

declare(strict_types=1);

namespace Lavertia\Panels\Pages;

/** Constrained center column without a Card border, background or shadow. */
abstract class FlatPage extends Page
{
    protected function contentWidth(): string
    {
        return 'lg';
    }

    /** @return array{variant: string, maxWidth: string} */
    protected function presentation(): array
    {
        return ['variant' => 'flat', 'maxWidth' => $this->contentWidth()];
    }
}
