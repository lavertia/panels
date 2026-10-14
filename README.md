# Lavertia Panels

**PHP-first composable Inertia panels for Laravel 12 / 13.**

Define pages and nested PHP components once and render them with React 19, Vue 3 or Svelte 5 using a single versioned JSON component protocol. Each adapter is designed for an **existing official Laravel Inertia Starter Kit**. Lavertia does not replace authentication, middleware, application layouts or Vite.

## Requirements

- PHP 8.3+, Laravel 12 or 13, `inertiajs/inertia-laravel:^3.0`
- [lavertia/support](https://github.com/lavertia/support) 0.1 (the component protocol)
- An existing official **React 19 / Vue 3 / Svelte 5** Inertia Starter Kit with TypeScript and Tailwind CSS 4

## Install into a Starter Kit

These packages are currently experimental and private; until tagged/published, use local Composer path repositories for `lavertia/support` and `lavertia/panels`. Do not assume `composer require` resolves on public Packagist yet.

```bash
composer require lavertia/panels:^0.1
php artisan lavertia:install --stack=react
# Or --stack=vue or --stack=svelte
php artisan vendor:publish --tag=lavertia-config
```

The command creates three files without overwriting your existing application: `resources/js/pages/lavertia/page.{tsx,vue,svelte}`, `resources/js/components/lavertia/renderer.{tsx,vue,svelte}` and `resources/js/components/lavertia/types.ts`. Use `--force` only when deliberately replacing previously generated Lavertia files.

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

**Security / functionality:** `Action::url()` permits only relative URLs and HTTPS. It is a navigation link, not a remote PHP method call. `Table::rows()` is limited to 100 scalar rows and is *not* Eloquent-backed yet. `TextInput` is local-only. Your application must use Laravel Policies, CSRF, validation and tenant-scoped queries for any writes or exposed data.

### Architecture

- `lavertia/support`: base Component contracts, immutable composition and versioned JSON serialization.
- `lavertia/panels`: pages, route registration, Starter Kit installer and initial PHP/UI adapters.
- Specialized Forms/Tables/Actions/Infolists will be extracted to their [dedicated Lavertia repositories](https://github.com/lavertia) after stable contracts; do not introduce duplicate package implementations.

The adapters are currently copied as starter-kit assets rather than published under `@lavertia/ui`; the `lavertia/ui` repository can own distributable adapters after separate framework build and SSR tests are added.

## Status

**0.1 experimental**. Composer integration, app-level end-to-end tests (React/Vue/Svelte), authorization tests and real Eloquent pagination remain outstanding. Do not deploy this package in production yet.

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

After configuring these in your **application's** `composer.json`, install with `composer require lavertia/panels:^0.1`. In package development, keep the support and panels branches checked out to their paired feature branches.

Each app selects **one** of React, Vue or Svelte; it does not load all three on the same page. The adapter owns only the Lavertia page/renderer and leaves the Starter Kit's authentication, Inertia app boot and app layout intact.
