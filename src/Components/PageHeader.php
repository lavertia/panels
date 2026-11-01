<?php

declare(strict_types=1);

namespace Lavertia\Panels\Components;

use InvalidArgumentException;
use Lavertia\Support\Components\AbstractComponent;
use Lavertia\Support\Components\SupportsComponents;
use Lavertia\Support\Contracts\ComposesComponents;

final class PageHeader extends AbstractComponent implements ComposesComponents
{
    use SupportsComponents;

    public static function make(string $title): self
    {
        return (new self)->withProp('title', $title);
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

    /** @param list<Action> $actions */
    public function actions(array $actions): static
    {
        foreach ($actions as $action) {
            if (! $action instanceof Action) {
                throw new InvalidArgumentException('Page header actions must be Action components.');
            }
        }

        return $this->components($actions);
    }

    /**
     * @param  list<array{label: string, url?: string|null}>  $crumbs
     */
    public function breadcrumbs(array $crumbs): static
    {
        return $this->withProp('breadcrumbs', $this->normalizeLinks($crumbs, false));
    }

    /**
     * @param  list<array{label: string, url: string, active?: bool}>  $tabs
     */
    public function tabs(array $tabs): static
    {
        $normalized = $this->normalizeLinks($tabs, true);

        if (count(array_filter($normalized, static fn (array $tab): bool => $tab['active'])) > 1) {
            throw new InvalidArgumentException('Only one Page Header tab can be active.');
        }

        return $this->withProp('tabs', $normalized);
    }

    /**
     * @param  array<mixed>  $links
     * @return list<array{label: string, url: string|null, active: bool}>
     */
    private function normalizeLinks(array $links, bool $tabs): array
    {
        if (! array_is_list($links) || count($links) > 20) {
            throw new InvalidArgumentException('Header links must be a list of at most 20 entries.');
        }

        $normalized = [];

        foreach ($links as $link) {
            if (! is_array($link) || ! is_string($link['label'] ?? null)
                || trim($link['label']) === '' || mb_strlen($link['label']) > 100
                || preg_match('/[<>\\x00-\\x1F\\x7F]/u', $link['label']) !== 0) {
                throw new InvalidArgumentException('Invalid header link label.');
            }

            $url = $link['url'] ?? null;

            if ($tabs && ! is_string($url)) {
                throw new InvalidArgumentException('Tabs must have a URL.');
            }

            if ($url !== null && (! is_string($url) || ! str_starts_with($url, '/')
                || str_starts_with($url, '//') || preg_match('/[\\\\\\x00-\\x20\\x7F]/', $url) !== 0)) {
                throw new InvalidArgumentException('Header links require a safe same-origin URL.');
            }

            if (array_key_exists('active', $link) && ! is_bool($link['active'])) {
                throw new InvalidArgumentException('Header tab active flag must be boolean.');
            }

            $normalized[] = [
                'label' => $link['label'],
                'url' => $url,
                'active' => $tabs && ($link['active'] ?? false),
            ];
        }

        return $normalized;
    }
}
