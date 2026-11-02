<?php

declare(strict_types=1);

namespace Lavertia\Panels\Auth;

use InvalidArgumentException;
use Lavertia\Panels\Pages\Page;
use ReflectionClass;

final class AuthRoute
{
    /** @var list<string> */
    private array $middleware = [];

    private function __construct(
        private readonly string $slug,
        private readonly string $page,
    ) {
        if (preg_match('#^[a-z][a-z0-9-]*(?:/[a-z][a-z0-9-]*)*$#D', $slug) !== 1
            || ! is_subclass_of($page, Page::class)
            || ! is_subclass_of($page, AuthPageContract::class)
            || (new ReflectionClass($page))->isAbstract()) {
            throw new InvalidArgumentException('Auth routes require a safe slug and a concrete Auth Page.');
        }

        $flow = $page::authFlow();

        if (! in_array($flow, [
            'login', 'register', 'forgot-password', 'reset-password',
            'verify-email', 'confirm-password', 'two-factor', 'recovery',
        ], true)) {
            throw new InvalidArgumentException('Unknown auth flow.');
        }
    }

    /** @param  class-string<Page&AuthPageContract>  $page */
    public static function make(string $slug, string $page): self
    {
        return new self($slug, $page);
    }

    /** @param  list<string>  $middleware */
    public function middleware(array $middleware): self
    {
        if (! array_is_list($middleware) || ! in_array('web', $middleware, true)
            || count($middleware) < 2) {
            throw new InvalidArgumentException('Auth Pages require web middleware and explicit guest/auth access.');
        }

        foreach ($middleware as $item) {
            if (! is_string($item) || $item === '' || preg_match('/^[a-zA-Z0-9_\\:.,-]+$/D', $item) !== 1) {
                throw new InvalidArgumentException('Invalid Auth Page middleware.');
            }
        }

        // Guest/auth handling cannot be implicit, since verify-email and 2FA
        // have different session states. Host may add Fortify-specific guards.
        if (! in_array('guest', $middleware, true) && ! in_array('auth', $middleware, true)
            && ! in_array('guest:web', $middleware, true)
            && ! in_array('auth:web', $middleware, true)) {
            throw new InvalidArgumentException('Auth Page must explicitly declare guest or auth middleware.');
        }

        $copy = clone $this;
        $copy->middleware = $middleware;

        return $copy;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function pageClass(): string
    {
        return $this->page;
    }

    /** @return list<string> */
    public function middlewares(): array
    {
        if ($this->middleware === []) {
            throw new InvalidArgumentException('Auth Route middleware must be configured.');
        }

        return $this->middleware;
    }
}
