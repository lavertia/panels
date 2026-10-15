# Lavertia v0.2 capability review (updated 2026-10-16)

## Official Filament 5 comparison

References (used to design behavior, **not** copied code):

- [Columns / toggling / reordering](https://filamentphp.com/docs/5.x/tables/columns/overview)
- [Filters](https://filamentphp.com/docs/5.x/tables/filters/overview)
- [Tables / query building](https://filamentphp.com/docs/5.x/tables/overview)
- [Forms / field types and validation](https://filamentphp.com/docs/5.x/forms/overview)
- [Schemas](https://filamentphp.com/docs/5.x/schemas/overview)

## Implemented experimental API

- `Table::filters([SelectFilter::make('status')->options([...])])`: exact-match allowlisted selection; client-side on snapshots, SQL on Eloquent builders.
- `TextColumn::toggleable(hiddenByDefault: true)`: column manager, show/hide. `hidden()` always hides a column from the rendered UI; **never** treats this as a security permission.
- `Table::reorderableColumns()`: user changes column order with accessible buttons. Record-level row reorder is **not** supported.
- `Table::columnManager(false)`: disable the column manager.
- `TextColumn::sortable()->searchable()`: local on snapshots, query-backed on Eloquent tables.
- `Table::eloquent($scopedBuilder, fn () => auth()->user()->can('viewAny', Model::class), perPage: 15)`: database pagination, allowlisted sort/search/filter, explicit authorization gate, scalar-only projection from declared columns.
- Query params are **namespaced per table** as `lvt_<table-name>_page/search/sort/direction/filter_<name>` so multiple instances on one page need not collide.
- `Form::make()->action('/local-route')->method('post'|'put'|'patch')` submits named `TextInput`, `Select`, `Textarea` and `Checkbox` values through Inertia, with processing state and Laravel validation feedback in all three adapters. Omit `action()` to keep preview-only behavior. Backend persistence and authorization remain application-owned. The Playground v0.2 branch demonstrates a user-owned Eloquent record and includes authentication, validation and mass-assignment negative tests.
- `ResolveLocale` handles explicit `?lang=en|ar|fr`, stores in Laravel session, and uses the current locale for page metadata. The frontend controls have en/ar/fr text with a safe English fallback; `dir=rtl` is applied to the Lavertia content main area. Full Starter Kit shell RTL and localized validation strings require separate integration.

### Eloquent example

```php
Table::make('users')
    ->searchable()
    ->columns([
        TextColumn::make('name')->sortable()->searchable(),
        TextColumn::make('email')->searchable()->toggleable(),
    ])
    ->filters([
        SelectFilter::make('status')->options(['active' => 'Active']),
    ])
    ->eloquent(
        User::query()->where('organization_id', $organization->id),
        fn (): bool => auth()->user()->can('viewAny', User::class)
            && auth()->user()->organizations()->whereKey($organization)->exists(),
        perPage: 20,
    );
```

**Tenant membership validation and row-level authorization are application responsibilities.** Recheck authorization on every mutation, enforce tenant scope in the query, and do not rely on `toggleable()` or `hidden()` to keep secrets from the client. `getAttribute()` is evaluated only for developer-declared columns; expensive accessors and relationship lookups remain the application's responsibility. Add indexes on searchable/sortable/filterable fields when appropriate.

### External APIs

Existing `Table::rows()` can display up to 100 **already-sanitized** scalar records mapped from a server-side API integration. This is a snapshot. A first-class remote cursor/offset API source with authenticated loading, paginated requests, rate limits and error handling is **not yet implemented**. Do not put API secrets into serialized component props.

## Production blockers (not stable)

1. No end-to-end browser/SSR test suite exercising interactions across React/Vue/Svelte.
2. Playground cross-repo GitHub Actions require authorized read access to private `support` and `panels`.
3. A real form persistence demo exists in Playground, but its PHP integration tests cannot run until private package read access is configured; framework-provided Form Request generation is not included.
4. No Repeater, Wizard, relationship editor, upload or server-side Actions.
5. No remote API pagination adapter or row reordering policy.
6. i18n coverage currently en/ar/fr, with limited shell RTL integration and no accessibility audit across locales.
7. Eloquent testing must be run against Postgres/MySQL too; a `LIKE '%term%'` search can be slow without an appropriate index or full-text strategy.
8. The framework lacks a versioned release and backwards-compatibility policy; do not adopt it in production yet.

## Next milestones

- v0.2.1: Verify persisted forms and Laravel Form Requests end-to-end across three stacks, browser a11y and SSR, and review Policies/transaction boundaries.
- v0.2.2: API TableSource contract with safe configurable endpoint and cursor/offset pagination.
- v0.2.3: Nested Repeater and Wizard, accessible error/focus behavior.
- v0.3: Plugin registry, remote/bulk actions, server record reorder, persistence of table preferences, full RTL.
