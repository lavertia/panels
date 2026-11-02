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
php artisan lavertia:panel Admin --id=admin
```

The command creates five files without overwriting your existing application: `resources/js/pages/lavertia/page.{tsx,vue,svelte}`, `resources/js/components/lavertia/renderer.{tsx,vue,svelte}`, `resources/js/components/lavertia/types.ts`, `resources/js/components/lavertia/form-state.ts` and `resources/js/components/lavertia/client-actions.ts`. Use `--force` only when deliberately replacing previously generated Lavertia files.

Ensure the app's existing Inertia page resolver includes `resources/js/pages/**/*` and uses the corresponding stack. No changes are needed to Laravel Fortify/WorkOS authentication or the existing Inertia root view. To reuse your Starter Kit app layout, wrap the generated page with your existing layout according to that kit.

Register the generated `App\\Providers\\Lavertia\\AdminPanelProvider::class` in
`bootstrap/providers.php`, and configure Pages, navigation, and styling
inside that Provider. No `config/lavertia.php` exists or is published.
Run `php artisan route:list --path=admin` and `npm run build` to verify.

### PanelProvider is the only source of Panel configuration

**Do not create `config/lavertia.php`**. Each Panel is defined in a real
Laravel `PanelProvider` registered through `bootstrap/providers.php`.
The underlying official Laravel Starter Kit still owns Fortify, team switching,
Inertia/Vite, the responsive Sidebar and user settings.

```bash
php artisan lavertia:panel Admin --id=admin
php artisan lavertia:page Invoices --panel=admin
```

Example `app/Providers/Lavertia/AdminPanelProvider.php`:

```php
<?php

namespace App\Providers\Lavertia;

use App\Lavertia\Pages\DashboardPage;
use Lavertia\Panels\Http\Middleware\ResolveLocale;
use Lavertia\Panels\Navigation\NavigationItem;
use Lavertia\Panels\Panel;
use Lavertia\Panels\PanelProvider;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->middleware(['web', 'auth', ResolveLocale::class])
            ->brandName('Lavertia')
            ->brandSubtitle('Workspace')
            ->brandLogo('/images/logo.svg')
            ->primaryColor('#2563eb')
            ->themeMode('system')
            ->fontFamily('Inter')
            ->borderRadius('lg')
            ->contentWidth('full')
            ->sidebarVariant('inset')
            ->sidebarWidth('18rem')
            ->sidebarCollapsible()
            ->navigationPosition('sidebar')
            ->topbar()
            ->breadcrumbs()
            ->stickyHeader()
            ->mobileNavigation()
            ->pages([DashboardPage::class])
            ->discoverPages(app_path('Lavertia/Pages'), 'App\\Lavertia\\Pages')
            ->navigationItems([
                NavigationItem::make('dashboard', 'Dashboard'),
                NavigationItem::make('invoices', 'Invoices')->group('Sales'),
            ])
            ->plugins([]);
    }
}
```

All values are validated before use. `PanelRegistry` keeps registered
Provider definitions in memory, loads module-owned pages and widgets, builds
named routes, and sends only JSON-safe theme/navigation metadata to Inertia.
An unregistered Panel does not silently exist. Duplicate Panel IDs fail
immediately. Route cache includes Panel routes, while `config:cache` does not
serialize PHP Panel instances.

### Complete native Panel shell controls

All of these settings belong in the `AdminPanelProvider`; no
`config/lavertia.php` file is needed:

```php
return $panel
    ->id('admin')
    ->brandName('Lavertia')
    ->brandLogo('/images/logo.svg')
    ->brandLogoDisplay('full') // full | icon | hidden
    ->brandLogoPlacement('sidebar') // sidebar | topbar | both
    ->tenantSwitcher()
    ->tenantSwitcherPlacement('sidebar') // sidebar | topbar
    ->sidebarCollapsible()
    ->navigationPosition('sidebar') // sidebar | top
    ->topbar()
    ->globalSearch()
    ->notifications()
    ->footerLinks([
        ['label' => 'Documentation', 'url' => 'https://example.com/docs', 'external' => true],
        ['label' => 'Home', 'url' => '/'],
    ])
    ->userMenuSettings()
    ->userMenuLogout()
    ->userMenuLinks([
        ['label' => 'Help', 'url' => 'https://example.com/help', 'external' => true],
    ]);
```

The existing Starter Kit remains the implementation for Team Switcher,
authentication, profile settings, and responsive sidebar. Tenant switcher
configuration only controls **where the existing Team Switcher appears**;
it does not replace team membership middleware, policy/tenant scoping, or
authorization. Implement those in the host application.

Global search v1 searches **authorized, registered Page navigation entries**
using the shared PanelRegistry navigation; it does not yet search Eloquent
records or arbitrary models. The Playground shows Ctrl/Cmd+K search.

Notifications v1 uses Laravel database notifications. The Playground
includes a `notifications` migration, an authenticated user-scoped
Mark-As-Read route and a dropdown. The host application must run
`php artisan migrate` before enabling `->notifications()`.
No cross-user notifications or hidden record URLs are exposed by the
notification presenter; notifications containing a `url` must use
a same-origin absolute path.

Logo paths must be same-origin, as in `/images/logo.svg`. The existing
Starter Kit icon is used when no logo image is supplied. For custom brand
logos, store the actual asset under `public/images` rather than pointing
at a non-existent file. Footer and custom user menu links are validated;
external links must use HTTPS and open with `rel="noopener noreferrer"`.
The built-in Logout action always remains a server POST with CSRF protection.

### Navigation icons and badges (P0)

A registered Page navigation item may specify one validated Lucide icon and a compact
status badge. The server resolves the Page route and Gate ability before serializing
either field. React, Vue, and Svelte render icons from an explicit allowlist.

```php
->navigationItems([
    NavigationItem::make('dashboard', 'Dashboard')
        ->icon('layout-dashboard'),
    NavigationItem::make('invoices', 'Invoices')
        ->icon('receipt')
        ->group('Management')
        ->sort(10),
    NavigationItem::make('reports', 'Reports')
        ->icon('chart-column')
        ->badge('New')
        ->group('Management')
        ->ability('viewReports'),
])
```

When omitted, `icon` and `badge` serialize as `null`, preserving existing
navigation entries and client fallbacks. Badge text is limited to 20 Unicode
characters and cannot contain HTML or control characters. It is presentation
metadata, **not** a permission or a tenant-scoped live record count; resolve
sensitive counts on the server with the current tenant and authorization first.
The current adapter fixtures demonstrate static badges only.

Supported icon values: `bell`, `book-open`, `briefcase`, `calendar`,
`chart-column`, `file-chart-column`, `file-text`, `folder`, `house`,
`layout-dashboard`, `list`, `receipt`, `settings`, `shield`,
`users`, `wallet`.

This is the first P0 navigation slice. Collapsible groups, nested items,
Page Header actions, and record Search Providers are tracked separately;
none are implemented by these APIs.

### Collapsible navigation and page header controls (P0-02)

Panels support configured group ordering, optional collapse behavior, and **one
nested level** of registered Page links. A child is omitted if its parent is
not authorized or registered, or if it belongs to a different group. There
are no arbitrary URLs or client-side authorization shortcuts.

```php
->navigationGroups([
    ['label' => 'Management', 'sort' => 10, 'collapsible' => true, 'collapsedByDefault' => true],
    ['label' => 'Billing', 'sort' => 20, 'collapsible' => true],
])
->navigationItems([
    NavigationItem::make('reports', 'Reports')->group('Management')->icon('chart-column'),
    NavigationItem::make('billing/reports', 'Billing Reports')
        ->group('Management')->parent('reports')->icon('file-chart-column'),
]);
```

`PageHeader` keeps Action components in the existing component protocol:
```php
PageHeader::make('Reports')
    ->description('Review your workspace reports.')
    ->breadcrumbs([
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Reports'],
    ])
    ->tabs([
        ['label' => 'Overview', 'url' => '/admin/reports', 'active' => true],
        ['label' => 'Billing', 'url' => '/admin/billing/reports'],
    ])
    ->actions([
        Action::make('open-billing')->label('Billing reports')->url('/admin/billing/reports'),
    ]);
```

Header links accept only safe same-origin paths. Client rendering uses
native Inertia links and does not bypass HTTP authorization. Group and
item collapse state is UI-only. Authorization is always resolved on the
server, and navigation search must never expose hidden children.

### Zero-config locales and Page layout overrides (P0-03)

By default **no localization configuration is required**. The Panel uses Laravel's
configured application locale and renders no language selector. For multilingual
Panels, provide only a list of supported locale codes:

```php
return $panel
    ->id('admin')
    ->locales(['en', 'ar', 'fr', 'pt-BR'])
    ->defaultLocale('en')
    ->layout('sidebar');
```

Only codes in the allowlist are accepted from the `?lang=ar` query, with a
preference stored under a **Panel-specific session key**. Invalid codes do not
change that preference. The page's HTML and Inertia payload resolve language
and writing direction server-side for the first response. Locales use
case-sensitive BCP-47-style tags (e.g. `pt-BR`, `zh-Hans`).

A Page may override layout and restrict its eligible language subset:

```php
final class PrintableReportPage extends Page
{
    public static function layout(): ?string
    {
        return 'full'; // also supports 'sidebar' and 'top'
    }

    public static function locales(): ?array
    {
        return ['en', 'ar'];
    }

    protected function components(): array
    {
        return [];
    }
}
```

A Page cannot introduce locales its Panel did not allow. Invalid or
nonoverlapping subsets are rejected. A `full` Page hides the Panel Sidebar
and Topbar but **does not** remove middleware, authorization or tenancy scope.
The host React/Vue/Svelte Starter Kit controls actual shell rendering.

**Important:** Setting locales determines which language is active and when
the switcher appears; it does *not* automatically translate static Page text,
Form labels, Resource data or validation messages. Translation catalogs and
multilingual form persistence are tracked separately in #22.

### Plugin API

Server-side plugins can register module-owned Pages, Navigation and Widgets.
They do not inject arbitrary JavaScript or bypass server authorization:

```php
use Lavertia\Panels\PanelRegistry;
use Lavertia\Panels\Plugins\PanelPlugin;
use Lavertia\Panels\Navigation\NavigationItem;

final class SalesPlugin implements PanelPlugin
{
    public function register(PanelRegistry $registry, string $panelId): void
    {
        $registry->discoverPages(
            $panelId,
            __DIR__.'/Lavertia/Pages',
            'Modules\\Sales\\Lavertia\\Pages',
        );
        $registry->navigationItem(
            $panelId,
            NavigationItem::make('sales/invoices', 'Invoices')->group('Sales'),
        );
    }
}
```

Register using `->plugins([new SalesPlugin])` on the Panel.

### Responsive, RTL and Starter Kit integration

The host shares `lavertiaPanel`, `lavertiaSidebar` and the initial
locale direction from its existing `HandleInertiaRequests` middleware.
These props drive the original Starter Kit components in React/Vue/Svelte:
single Sidebar, mobile drawer, optional top navigation, breadcrumbs, sticky
header, branding and width, typography and border-radius tokens.

The `themeMode` value is a **preference** supplied to the shell; do not
override a user's stored accessibility/theme selection from a post-mount
effect. Existing starter-kit theme controls remain authoritative. The
`header` remains accessible with a sidebar toggle even when `topbar(false)`
is selected. UI component visibility is never authorization; protect all
routes and data using policies and tenant scoping.

### Page generator

`lavertia:page` checks the registered PanelProvider, generates a PHP Page
class under `app/Lavertia/Pages`, and prints the class and route prefix.
The Provider can discover generated Pages via `discoverPages()`, avoiding
manual PHP config edits. Generated Pages remain fully composable and
the React/Vue/Svelte adapters render a shared Component Protocol.

### Laravel Modular

An `internachi/modular` module provider may add Pages before or after the
Panels provider boots via `PanelRegistry::discoverPages()`. Plugins can
register their own navigation and Dashboard Widgets. Module contributions
are deduplicated; route names and middleware are controlled by the owning
Panel, and module discovery is compatible with Laravel route caching.


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

Forms may also be mounted inside an opt-in browser-native Action dialog.
The dialog opens locally, submits the nested Form via Inertia, and closes
**only after a successful save**; validation errors keep it open:

```php
use Lavertia\Panels\Components\Action;
use Lavertia\Forms\Components\{Form, TextInput};

Action::make('editUser')
    ->label('Edit user')
    ->modal(
        Form::make('editUser')
            ->action('/users/12')
            ->method('patch')
            ->refreshOnSuccess(['lavertia'])
            ->components([
                TextInput::make('name')->required()->value('Sara'),
            ]),
    )
    ->closeOnSuccess();
```

This API uses native HTML dialog behavior (Escape-to-close and focus
containment); no full-page reload is necessary. The Form route must redirect
back using Laravel's normal Inertia validation workflow, and the input defaults
must be supplied server-side with proper access checks. Dynamic record loading,
granular invalidation, dirty-form discard prompts and accessibility browser
E2E remain to be implemented.

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

Important limitations: this is **not** automatic model
persistence, a full server-driven record hydration engine, or a complete record-version conflict resolver. Future versions will provide these. Continue using Laravel Policies,
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

## Install in a blank Laravel React app (no Starter Kit)

The `lavertia:install` command also supports Laravel 13's built-in
`laravel new` → **No Starter Kit** → **React** scaffold.

After adding the package through Composer and confirming that
`@inertiajs/react`, Inertia Laravel v3 and the Laravel Vite entry
points exist, run:

```bash
php artisan lavertia:install --stack=react --panel=admin --demo
php artisan config:clear
php artisan route:clear
php artisan view:clear
npm run build
php artisan route:list --path=admin
```

The installer copies the React renderer, component protocol, client actions
and local form-state runtime. It generates `DashboardPage.php`, an
`AdminPanelProvider`, adds the provider to `bootstrap/providers.php`,
and writes both `lavertia/page.tsx` and `lavertia/standalone.tsx`.
Existing React/Vite/Blade files are not replaced.

**Security:** `--demo` adds middleware that only allows the preview
while `APP_ENV=local`; outside local it returns 404. Without
`--demo` the default panel uses Laravel's `auth` middleware, and the
host application must provide session authentication and a login route.
Never use a demo panel as a production authorization mechanism.

The command can be safely re-run while generated files are unchanged.
It refuses to overwrite modified files unless explicitly given `--force`.
These commands are also available in the
[standalone React example](https://github.com/lavertia/lavertia-react-clean/tree/feat/lavertia-installer).

Until tagged Composer releases become available, set up VCS repositories
or symlinked Composer path repositories for `lavertia/{panels,forms,tables,support}`
before calling `composer require lavertia/panels`. See the example
project's `scripts/install-lavertia.sh` for both supported approaches.
