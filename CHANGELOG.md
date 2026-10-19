# Changelog

## Unreleased

- Add v0.3 alpha NavigationItem and PanelRegistry navigation, with per-Panel routing, Laravel Gate filtering, labels/groups/ordering, active-state and safe, registered-only links.
- Add DashboardWidget contract, Composer PSR-4 widget discovery and opt-in modular DashboardPage with authorization checks.
- Extend Inertia page payload with optional navigation and render responsive, RTL-aware navigation across React, Vue and Svelte.
- Add real Laravel 13 + InterNACHI/modular navigation/widget integration and three installed-adapter production build checks.

- Discover PHP Pages recursively from Composer PSR-4 Laravel modules using `Panel::discoverPages()` or module service-provider `PanelRegistry` registration.
- Register module and config routes during provider boot (regardless of module provider order), preserving precedence over broad host routes, middleware and duplicate/unsafe slug rejection.
- Verify installed `internachi/modular` modules in Laravel 13, including authenticated Inertia rendering, nested routes, and config/route caching in CI.

- Replace 2023 Panels placeholder with a composable Inertia-based panel foundation.
- Add configurable multi-panel routing, Inertia Page adapter and versioned component payload.
- Add React, Vue and Svelte starter-kit adapters and non-destructive installer.
- Add initial Table, Infolist, Action, TextInput and layout composition.
