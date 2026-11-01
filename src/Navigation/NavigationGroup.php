<?php

declare(strict_types=1);

namespace Lavertia\Panels\Navigation;

use InvalidArgumentException;
use Lavertia\Support\Components\AbstractComponent;
use Lavertia\Support\Components\SupportsComponents;
use Lavertia\Support\Contracts\Component;
use Lavertia\Support\Contracts\ComposesComponents;

/**
 * A portable navigation widget. Can appear inside a Panel slot or a Page tree.
 * Server-authorized NavigationItems are the only supported link children.
 */
final class NavigationGroup extends AbstractComponent implements ComposesComponents
{
    use SupportsComponents {
        getComponents as private childComponents;
    }

    private bool $collapsible = true;

    private bool $collapsedByDefault = false;

    private function __construct(private readonly string $label)
    {
        if (trim($label) === '' || mb_strlen($label) > 80
            || preg_match('/[<>\\x00-\\x1F\\x7F]/u', $label) !== 0) {
            throw new InvalidArgumentException('Invalid navigation group label.');
        }
    }

    public static function make(string $label): self
    {
        return new self($label);
    }

    public function getType(): string
    {
        return 'navigation-group';
    }

    public function collapsible(bool $enabled = true): self
    {
        $copy = clone $this;
        $copy->collapsible = $enabled;

        return $copy;
    }

    public function collapsedByDefault(bool $collapsed = true): self
    {
        $copy = clone $this;
        $copy->collapsedByDefault = $collapsed;

        return $copy;
    }

    /** @return array<string, mixed> */
    public function getProps(): array
    {
        return [
            'label' => $this->label,
            'collapsible' => $this->collapsible,
            'collapsedByDefault' => $this->collapsible && $this->collapsedByDefault,
        ];
    }

    /** @return list<Component> */
    public function getComponents(): array
    {
        return array_map(
            fn (Component $component): Component => $component instanceof NavigationItem
                && $component->groupName() === ''
                ? $component->group($this->label)
                : $component,
            $this->childComponents(),
        );
    }
}
