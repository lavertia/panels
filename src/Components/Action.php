<?php

declare(strict_types=1);

namespace Lavertia\Panels\Components;

use InvalidArgumentException;
use Lavertia\Support\Components\AbstractComponent;
use Lavertia\Support\Contracts\ActionComponent;

final class Action extends AbstractComponent implements ActionComponent
{
    private function __construct(private readonly string $name)
    {
    }

    public static function make(string $name): self
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,127}$/D', $name) !== 1) {
            throw new InvalidArgumentException('Invalid action name.');
        }

        return (new self($name))->key($name);
    }

    public function getType(): string
    {
        return 'action';
    }

    public function getActionName(): string
    {
        return $this->name;
    }

    public function label(string $label): static
    {
        return $this->withProp('label', $label);
    }

    public function url(string $url): static
    {
        $relative = str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '\\');
        $https = filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'https';

        if (! $relative && ! $https) {
            throw new InvalidArgumentException('Action URLs must be relative paths or HTTPS URLs.');
        }

        return $this->withProp('href', $url);
    }

    /** @return array<string, mixed> */
    public function getProps(): array
    {
        return ['name' => $this->name, ...parent::getProps()];
    }
}
