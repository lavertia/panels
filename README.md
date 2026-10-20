# Lavertia Panels

**PHP-first composable Inertia panels for Laravel 12 / 13.**

Define pages and nested PHP components once and render them with React 19, Vue 3 or Svelte 5 using a single versioned JSON component protocol. Each adapter is designed for an **existing official Laravel Inertia Starter Kit**. Lavertia does not replace authentication, middleware, application layouts or Vite.

## Requirements

- PHP 8.3+, Laravel 12 or 13, `inertiajs/inertia-laravel:^3.0`
- [lavertia/support](https://github.com/lavertia/support) 0.1 (the component protocol)
- An existing official **React 19 / Vue 3 / Svelte 5** Inertia Starter Kit with TypeScript and Tailwind CSS 4

## Install into a Starter Kit

Repositories are public, but this remains an experimental, untagged version. Use local Composer path repositories for Support and Panels until package releases are approved; a Packagist install is not yet promised.

```bash
composer require lavertia/panels:^0.1
php artisan lavertia:install --stack=react
# Or --stack=vue or --stack=svelte
php artisan vendor:publish --tag=lavertia-config
```

The command creates five files without overwriting your existing application: `resources/js/pages/lavertia/page.{tsx,vue,svelte}`, `resources/js/components/lavertia/renderer.{tsx,vue,svelte}`, `resources/js/components/lavertia/types.ts`, `resources/js/components/lavertia/form-state.ts` and `resources/js/components/lavertia/client-actions.ts`. Use `--force` only when deliberately replacing previously generated Lavertia files.

Ensure the app's existing Inertia page resolver includes `resources/js/pages/**/*` and uses the corresponding stack. No changes are needed to Laravel Fortify/WorkOS authentication or the existing Inertia root view. To reuse your Starter Kit app layout, wrap the generated page with your existing layout according to that kit.

Add your Page class to `config/lavertia.php`:

```php
'panels' => [
    'admin' => [
        'path' => 'admin',
        'middleware' => ['web', 'auth'],
        'pages' => [App\Lavertia\Pages\DashboardPage::class],
    ],
],
```

Then run `php artisan route:list --path=admin` and `npm run build`. Pages are mounted at `/admin/{page-slug}`. Each panel can register multiple pages.

### Laravel Modular support (module-owned Pages)

Lavertia can discover PHP Pages from [InterNACHI/modular](https://github.com/InterNACHI/modular) `app-modules/*/src/` packages **without adding a hard dependency on Modular**. In the module's Laravel service provider, register the Pages belonging to a configured Panel:

```php
public function boot(\Lavertia\Panels\PanelRegistry $panels): void
{
    $panels->discoverPages(
        panelId: 'admin',
        in: dirname(__DIR__).'/Lavertia/Pages',
        for: 'Modules\\Inventory\\Lavertia\\Pages',
    );
}
```

The host application's existing Panel middleware continues to protect these routes; module provider registrations are mounted during provider boot, preserving precedence over broad host routes. Alternatively use the Panel's `discover_pages` config or `Panel::discoverPages()` directly. This alpha feature discovers **Pages** (not Filament Resources/Widgets). See [Laravel Modular integration and Vite PHP refresh](docs/MODULAR_LARAVEL.md).

### v0.3 alpha: modular Navigation and Dashboard Widgets

Register a Page in a configured Panel, then add a permission-filtered NavigationItem and optionally discover DashboardWidgets from a Composer PSR-4 module's Service Provider:

```php
public function boot(\Lavertia\Panels\PanelRegistry $panels): void
{
    $panels->discoverWidgets(
        panelId: 'admin',
        in: dirname(__DIR__).'/Lavertia/Widgets',
        for: 'Modules\\Inventory\\Lavertia\\Widgets',
    );

    $panels->navigationItem(
        panelId: 'admin',
        item: \Lavertia\Panels\Navigation\NavigationItem::make('inventory/overview', 'Inventory')
            ->group('Operations')
            ->ability('inventory.view'),
    );
}
```

The Module's Pages stay behind the Panel's Laravel middleware. The new opt-in `Lavertia\Panels\Pages\DashboardPage` renders all authorized Widgets registered for that Panel; it must not be registered alongside another Page with the same slug. Navigation is delivered as optional Inertia props and rendered by React, Vue and Svelte, including active state and RTL layout. Neither hiding links nor Widget `canView()` replaces server-side Policies and tenant scoping.

See the [v0.3 Navigation and Widgets guide](docs/V03.md). Resources and Relation Managers remain future milestones.

### Local-first form interactions (experimental)

The React, Vue and Svelte installers now include one shared form-state runtime.
Repeated rows can be added, removed, reordered, and collapsed locally without
network requests. Their indexed values are submitted as nested arrays through
Inertia; even an empty repeater is submitted as an empty array. Typed text and
checkboxes remain unchanged when stale server props arrive.

Use the Repeater descriptor from the canonical lavertia/forms package. Existing
Forms/Fields keep their PHP API. With Forms' refreshOnSuccess(['lavertia']) an
Inertia partial reload may be requested after a successful save; this is not a
full browser refresh. Refreshing a more granular top-level data prop will become
possible when Pages split field data from the component schema.

Host applications can also register named browser actions. PHP only sends the
action identifier and JSON-safe data; executable JavaScript lives in the app's
TypeScript bundle:

```php
Action::make('printReceipt')
    ->label('Print')
    ->clientAction('receipts.print', ['format' => 'a4']);
```

In an app startup module, import the generated registry:

```typescript
import { registerClientAction } from '@/components/lavertia/client-actions';

registerClientAction('receipts.print', async (params) => {
    window.print();
});
```

Handlers run only after a user clicks an Action. Errors are rendered in
an accessible alert; Laravel cannot send arbitrary scripts or bypass the host's
bundling/CSP rules.

Important limitations: this is **not** a modal Action API, automatic model
persistence, or a complete record-version conflict resolver. Future versions will provide these. Continue using Laravel Policies,
Form Requests, CSRF protection and transactions for nested writes. Each row
identifier supplied by a browser must be tenant-scoped and authorized.

### Example: place a Table anywhere

```php
<?php

namespace App\Lavertia\Pages;

use Lavertia\Panels\Components\{Action, Infolist, Table, TextColumn, TextEntry};
use Lavertia\Panels\Pages\Page;
use Lavertia\Support\Components\{Card, Column, Text};
use Lavertia\Support\Contracts\Component;

final class DashboardPage extends Page
{
    /** @return list<Component> */
    protected function components(): array
    {
        return [
            Column::make([
                Text::make('Dashboard'),
                Card::make([
                    Infolist::make('company')->components([
                        TextEntry::make('name')->value('Fenix Software'),
                    ]),
                    Table::make('users')->columns([
                        TextColumn::make('name'),
                        TextColumn::make('email'),
                    ])->rows([
                        ['name' => 'Sara', 'email' => 'sara@example.com'],
                    ]),
                    Action::make('home')->label('Home')->url('/'),
                ]),
            ]),
        ];
    }
}
```

**Security / functionality:** `Action::url()` permits only relative URLs and HTTPS. It is a navigation link, not a remote PHP method call. `Table::rows()` is limited to 100 scalar rows in snapshot mode; the v0.2 `eloquent()` mode accepts an explicitly authorized, user/tenant-scoped Eloquent query. `TextInput` is local-only. Your application must use Laravel Policies, CSRF, validation and tenant-scoped queries for any writes or exposed data.

### Architecture

- `lavertia/support`: base Component contracts, immutable composition and versioned JSON serialization.
- `lavertia/panels`: pages, route registration, Starter Kit installer and initial PHP/UI adapters.
- Canonical [Forms](https://github.com/lavertia/forms) and [Tables](https://github.com/lavertia/tables) packages are public experimental modules. Panels v0.2 uses compatibility aliases for legacy `Lavertia\\Panels\\Components\\Form`, `Table`, `TextColumn`, `Select`, and other extracted descriptors. See [the migration guide](docs/PACKAGE_MIGRATION_V02.md).

The adapters are currently copied as starter-kit assets rather than published under `@lavertia/ui`; the `lavertia/ui` repository can own distributable adapters after separate framework build and SSR tests are added.

## Status

**v0.2 development alpha**. PHPUnit/Testbench and React/Vue/Svelte Laravel integration, frontend type and production build suites now run. No supported stable Composer tag or production-readiness claim has been approved. Do not deploy this package in production yet.

## Local development before Packagist release

Clone `lavertia/support` and `lavertia/panels` as siblings next to your Laravel application and configure the application's Composer repositories:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../support",
      "options": {
        "symlink": true,
        "versions": { "lavertia/support": "0.1.0" }
      }
    },
    {
      "type": "path",
      "url": "../panels",
      "options": {
        "symlink": true,
        "versions": { "lavertia/panels": "0.1.0" }
      }
    }
  ]
}
```

After configuring these in your **application's** `composer.json`, install with `composer require lavertia/panels:^0.1`. For development, use `lavertia/support` on `main` and `lavertia/panels` at its tested development ref; canonical Forms/Tables are available as local sibling Composer path dependencies.

Each app selects **one** of React, Vue or Svelte; it does not load all three on the same page. The adapter owns only the Lavertia page/renderer and leaves the Starter Kit's authentication, Inertia app boot and app layout intact.


## Admin UI components (Filament-inspired visual hierarchy)

The latest development branch adds the PHP components `PageHeader`, `StatsGrid`, `Stat` and `Section` for a structured dashboard. This is a **visual inspiration**, not a Filament fork or a Filament-compatible API.

```php
use Lavertia\Panels\Components\{
    Action, PageHeader, Section, Stat, StatsGrid,
    Table, TextColumn
};

return [
    PageHeader::make('Overview')
        ->description('Manage your workspace')
        ->components([
            Action::make('source')
                ->label('View source')
                ->appearance('primary')
                ->url('https://github.com/lavertia/playground'),
        ]),

    StatsGrid::make([
        Stat::make('Members', 6)->tone('success'),
        Stat::make('Pending', 2)->tone('warning'),
    ]),

    Section::make('Directory')->components([
        Table::make('members')
            ->label('Team members')
            ->searchable()
            ->columns([
                TextColumn::make('name')->sortable(),
                TextColumn::make('status')->badge([
                    'Active' => 'success',
                    'Pending' => 'warning',
                ]),
            ])
            ->rows([
                ['name' => 'Sara', 'status' => 'Active'],
                ['name' => 'Omar', 'status' => 'Pending'],
            ]),
    ]),
];
```

The same PHP schema renders through React, Vue, and Svelte with static Tailwind CSS 4 utilities, responsive layout, accessible labels and focus states, light/dark mode, readable badges, and descriptive empty states.

**Scope:** `Table::searchable()` and `TextColumn::sortable()` operate solely on the bounded client-side snapshot (up to 100 rows). For Eloquent mode, explicitly authorized and allowlisted SQL search/filter/sort/pagination are available only through a scoped `Builder`. Forms with an explicit local `action()` can submit via Inertia (POST, PUT or PATCH), display validation errors and use application-owned Laravel controllers for persistence. Forms without `action()` remain previews. All executable actions must be implemented as explicitly authorized Laravel routes on the server.

To update an existing Starter Kit app, review generated files and then run `php artisan lavertia:install --stack=react --force` (or `vue`/`svelte`). The `--force` flag overwrites generated renderer/page files. See [DESIGN_SYSTEM.md](docs/DESIGN_SYSTEM.md) for component roles and limitations.

## Security and licensing

MIT licensed for Lavertia-owned source ([LICENSE.md](LICENSE.md)). Report security issues privately via [SECURITY.md](SECURITY.md); verify pre-release status in [the release checklist](https://github.com/lavertia/lavertia/issues/3).

### Canonical package integration

The v0.2 Panels development code delegates form and table descriptors to the independent `lavertia/forms` and `lavertia/tables` packages and maintains backwards-compatible `Lavertia\\Panels\\Components` aliases. Use explicitly scoped Eloquent Builders and server-side authorization; the frontend never receives raw Eloquent queries or PHP callbacks. No stable package tag has been approved.
