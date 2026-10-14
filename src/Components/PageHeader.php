<?php

declare(strict_types=1);

namespace Lavertia\Panels\Components;

use Lavertia\Support\Components\AbstractComponent;
use Lavertia\Support\Components\SupportsComponents;
use Lavertia\Support\Contracts\ComposesComponents;

final class PageHeader extends AbstractComponent implements ComposesComponents
{
    use SupportsComponents;

    public static function make(string $title): self
    {
        return (new self())->withProp('title', $title);
    }

    public function getType(): string
    {
        return 'page-header';
    }

    public function description(string $description): static
    {
        return $this->withProp('description', $description);
    }

    public function eyebrow(string $eyebrow): static
    {
        return $this->withProp('eyebrow', $eyebrow);
    }
}
