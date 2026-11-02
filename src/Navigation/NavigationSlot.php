<?php

declare(strict_types=1);

namespace Lavertia\Panels\Navigation;

use InvalidArgumentException;
use Lavertia\Support\Components\AbstractComponent;
use Lavertia\Support\Components\SupportsComponents;
use Lavertia\Support\Contracts\ComposesComponents;

/**
 * Named, portable shell region. Renderers decide where a region goes;
 * Laravel owns the component tree and route authorization.
 */
final class NavigationSlot extends AbstractComponent implements ComposesComponents
{
    use SupportsComponents;

    private string $position = 'sidebar';

    private function __construct(private readonly string $name)
    {
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $name) !== 1) {
            throw new InvalidArgumentException('Navigation slot requires a safe ID.');
        }
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function position(string $position): self
    {
        if (! in_array($position, ['sidebar', 'topbar', 'footer', 'tenant', 'icon-rail', 'context'], true)) {
            throw new InvalidArgumentException('Unsupported shell navigation position.');
        }

        $copy = clone $this;
        $copy->position = $position;

        return $copy;
    }

    public function getType(): string
    {
        return 'navigation-slot';
    }

    /** @return array<string, mixed> */
    public function getProps(): array
    {
        return ['name' => $this->name, 'position' => $this->position];
    }
}
