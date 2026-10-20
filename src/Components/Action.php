<?php

declare(strict_types=1);

namespace Lavertia\Panels\Components;

use InvalidArgumentException;
use Lavertia\Support\Components\AbstractComponent;
use Lavertia\Support\Contracts\ActionComponent;

final class Action extends AbstractComponent implements ActionComponent
{
    private function __construct(private readonly string $name) {}

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

    public function appearance(string $appearance): static
    {
        if (! in_array($appearance, ['primary', 'secondary', 'outline'], true)) {
            throw new InvalidArgumentException('Invalid action appearance.');
        }

        return $this->withProp('appearance', $appearance);
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

    /**
     * Invoke a client-side handler registered by the host frontend.
     *
     * JavaScript is never sent over the component protocol. Only the
     * allowlisted name and JSON-safe payload reach the browser.
     *
     * @param  array<string, mixed>  $params
     */
    public function clientAction(string $name, array $params = []): static
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,127}$/D', $name) !== 1) {
            throw new InvalidArgumentException('Invalid client action name.');
        }

        if ($params !== [] && array_is_list($params)) {
            throw new InvalidArgumentException('Client action parameters must be an associative map.');
        }

        foreach (array_keys($params) as $key) {
            if (! is_string($key) || preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,127}$/D', $key) !== 1) {
                throw new InvalidArgumentException('Invalid client action parameter name.');
            }
        }

        return $this->withProp('clientAction', $name)
            ->withProp('clientParams', $params);
    }

    /** @return array<string, mixed> */
    public function getProps(): array
    {
        return ['name' => $this->name, 'appearance' => 'secondary', ...parent::getProps()];
    }
}
