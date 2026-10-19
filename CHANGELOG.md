# Changelog

## Unreleased

- Discover PHP Pages recursively from Composer PSR-4 Laravel modules using `Panel::discoverPages()` or module service-provider `PanelRegistry` registration.
- Register module and config routes during provider boot (regardless of module provider order), preserving precedence over broad host routes, middleware and duplicate/unsafe slug rejection.
- Verify installed `internachi/modular` modules in Laravel 13, including authenticated Inertia rendering, nested routes, and config/route caching in CI.

- Replace 2023 Panels placeholder with a composable Inertia-based panel foundation.
- Add configurable multi-panel routing, Inertia Page adapter and versioned component payload.
- Add React, Vue and Svelte starter-kit adapters and non-destructive installer.
- Add initial Table, Infolist, Action, TextInput and layout composition.
