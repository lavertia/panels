# Lavertia Panels

PHP-first composable interfaces for Laravel and Inertia.js, with adapters for React, Vue and Svelte. Lavertia describes UI elements in PHP and serializes them using the [Support Component Protocol v1](https://github.com/lavertia/support).

> **Experimental / not yet released.** The default branch still contains an **older package scaffold**, not the v0.1 implementation. Do **not** follow the old scaffold's installation steps or deploy it as a stable package.

## Active implementation

- [PR #4 — Panels, routes and Starter Kit adapters](https://github.com/lavertia/panels/pull/4)
- [PR #5 — composable UI widgets](https://github.com/lavertia/panels/pull/5)
- [PR #6 — Forms/Tables, locale and pagination](https://github.com/lavertia/panels/pull/6)
- [PR #7 — canonical Forms/Tables package integration](https://github.com/lavertia/panels/pull/7)

The supported code will be merged into `main` only after PHP/Testbench, adapter, and Laravel Playground integration tests run successfully. These PRs are **not** production releases. Do not assume any Packagist version exists or that `composer require lavertia/panels` is ready.

## Architecture

- [Support](https://github.com/lavertia/support) — typed PHP components and bounded, JSON-safe serialization
- [Forms](https://github.com/lavertia/forms) — form/field descriptors (v0.2 in draft)
- [Tables](https://github.com/lavertia/tables) — Eloquent-backed table descriptors (v0.2 in draft)
- [Playground](https://github.com/lavertia/playground) — Laravel 13 React/Vue/Svelte integration applications
- [Documentation](https://lavertia.github.io/lavertia-doc/) — concepts, roadmap and guides

Your Laravel app remains responsible for policies, authentication, CSRF, validated inputs and tenant-scoped queries.

## Security and contribution

See [SECURITY.md](SECURITY.md) for private disclosure and the [release checklist](https://github.com/lavertia/lavertia/issues/3) before using a development branch. Never commit `.env`, application keys, live customer records or credentials.

## License

MIT — see [LICENSE.md](LICENSE.md). Third-party code and assets retain their respective licenses.
