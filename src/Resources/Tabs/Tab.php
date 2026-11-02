<?php

declare(strict_types=1);

namespace Lavertia\Panels\Resources\Tabs;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Illuminate\Support\Str;

/**
 * Server-owned filter tab. Callbacks never enter the Inertia payload.
 * Query modifiers receive an already tenant-scoped Eloquent Builder.
 */
final class Tab
{
    private ?Closure $queryModifier = null;

    private ?string $icon = null;

    private string $iconPosition = 'before';

    private int|string|Closure|null $badge = null;

    private string $badgeColor = 'neutral';

    private function __construct(private ?string $label = null) {}

    public static function make(?string $label = null): self
    {
        if ($label !== null && (trim($label) === '' || mb_strlen($label) > 100)) {
            throw new InvalidArgumentException('Tab label must be 1–100 characters.');
        }

        return new self($label);
    }

    public function label(string $label): self
    {
        if (trim($label) === '' || mb_strlen($label) > 100) {
            throw new InvalidArgumentException('Tab label must be 1–100 characters.');
        }

        $copy = clone $this;
        $copy->label = $label;

        return $copy;
    }

    /** The return value is ignored: the original scoped builder is preserved. */
    public function modifyQueryUsing(Closure $callback): self
    {
        $copy = clone $this;
        $copy->queryModifier = $callback;

        return $copy;
    }

    public function icon(string $icon): self
    {
        if (preg_match('/^[a-z][a-z0-9-]{0,59}$/D', $icon) !== 1) {
            throw new InvalidArgumentException('Tab icon must be a safe icon identifier.');
        }

        $copy = clone $this;
        $copy->icon = $icon;

        return $copy;
    }

    public function iconPosition(string $position): self
    {
        if (! in_array($position, ['before', 'after'], true)) {
            throw new InvalidArgumentException('Tab icon position must be before or after.');
        }

        $copy = clone $this;
        $copy->iconPosition = $position;

        return $copy;
    }

    /** Scalar counts or a request-time count closure, never a global cached count. */
    public function badge(int|string|Closure $badge): self
    {
        $copy = clone $this;
        $copy->badge = $badge;

        return $copy;
    }

    public function badgeColor(string $color): self
    {
        if (! in_array($color, ['neutral', 'primary', 'success', 'warning', 'danger', 'info'], true)) {
            throw new InvalidArgumentException('Unsupported Tab badge color.');
        }

        $copy = clone $this;
        $copy->badgeColor = $color;

        return $copy;
    }

    public function scope(Builder $query): Builder
    {
        if ($this->queryModifier !== null) {
            ($this->queryModifier)($query);
        }

        return $query;
    }

    /**
     * Return serializable metadata only: neither query nor badge callbacks
     * are sent to the frontend.
     *
     * @return array{label: string, icon: string|null, iconPosition: string, badge: string|null, badgeColor: string}
     */
    public function metadata(string $key): array
    {
        $badge = $this->badge instanceof Closure ? ($this->badge)() : $this->badge;

        if ($badge !== null && (! is_int($badge) && ! is_string($badge))) {
            throw new InvalidArgumentException('Tab badge callback must return a string, integer or null.');
        }

        if (is_string($badge) && mb_strlen($badge) > 30) {
            throw new InvalidArgumentException('Tab badge cannot exceed 30 characters.');
        }

        return [
            'label' => $this->label ?? Str::headline($key),
            'icon' => $this->icon,
            'iconPosition' => $this->iconPosition,
            'badge' => $badge === null ? null : (string) $badge,
            'badgeColor' => $this->badgeColor,
        ];
    }
}
