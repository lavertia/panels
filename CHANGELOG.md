# Changelog

## Unreleased

- Discover PHP Pages recursively from Composer PSR-4 Laravel modules using `Panel::discoverPages()` or module service-provider `PanelRegistry` registration.
- Defer route registration until Laravel providers finish booting; preserve panel middleware and reject duplicate/unsafe slugs.
- Verify installed `internachi/modular` modules in Laravel 13, including authenticated Inertia rendering, nested routes, and config/route caching in CI.

- Replace 2023 Panels placeholder with a composable Inertia-based panel foundation.
- Add configurable multi-panel routing, Inertia Page adapter and versioned component payload.
- Add React, Vue and Svelte starter-kit adapters and non-destructive installer.
- Add initial Table, Infolist, Action, TextInput and layout composition.
