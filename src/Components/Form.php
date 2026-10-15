<?php

declare(strict_types=1);

namespace Lavertia\Panels\Components;

use InvalidArgumentException;
use Lavertia\Support\Components\AbstractComponent;
use Lavertia\Support\Components\SupportsComponents;
use Lavertia\Support\Contracts\ComposesComponents;

final class Form extends AbstractComponent implements ComposesComponents
{
    use SupportsComponents;

    private function __construct(private readonly string $name)
    {
    }

    public static function make(string $name): self
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D', $name) !== 1) {
            throw new InvalidArgumentException('Invalid form name.');
        }

        return (new self($name))->key($name);
    }

    public function getType(): string
    {
        return 'form';
    }

    public function label(string $label): static
    {
        return $this->withProp('label', $label);
    }

    /**
     * Navigation-free form action. The Laravel route must validate, authorize and
     * enforce tenant scope. No automatic model mass assignment is provided.
     */
    public function action(string $url): static
    {
        if (! str_starts_with($url, '/') || str_starts_with($url, '//')
            || str_contains($url, '\\') || str_contains($url, '#')) {
            throw new InvalidArgumentException('Form action must be a local absolute path.');
        }

        return $this->withProp('action', $url);
    }

    /**
     * Browser submissions use Inertia and Laravel's normal web middleware,
     * including session-based CSRF protection and validation redirects.
     */
    public function method(string $method): static
    {
        $method = strtolower($method);

        if (! in_array($method, ['post', 'put', 'patch'], true)) {
            throw new InvalidArgumentException('Form method must be POST, PUT or PATCH.');
        }

        return $this->withProp('method', $method);
    }

    public function submitLabel(string $label): static
    {
        return $this->withProp('submitLabel', $label);
    }

    /** @return array<string, mixed> */
    public function getProps(): array
    {
        return ['name' => $this->name, 'method' => 'post', ...parent::getProps()];
    }
}
