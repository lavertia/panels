<?php

declare(strict_types=1);

namespace Lavertia\Panels;

/**
 * Module service providers register page discovery during their boot() method.
 * Panels are mounted after all service providers have finished booting.
 */
final class PanelRegistry
{
    /** @var array<string, list<array{in: string, for: string}>> */
    private array $pageDirectories = [];

    public function discoverPages(string $panelId, string $in, string $for): void
    {
        // Validate the same inputs that Panel accepts, without scanning files yet.
        Panel::make($panelId)->discoverPages(in: $in, for: $for);

        $entry = ['in' => $in, 'for' => trim($for, '\\')];

        if (! in_array($entry, $this->pageDirectories[$panelId] ?? [], true)) {
            $this->pageDirectories[$panelId][] = $entry;
        }
    }

    /** @return list<array{in: string, for: string}> */
    public function pageDirectories(string $panelId): array
    {
        return $this->pageDirectories[$panelId] ?? [];
    }
}
