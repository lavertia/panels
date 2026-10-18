# Laravel modules (InterNACHI/modular and ordinary Composer packages)

Lavertia Panels can load Page classes from a Laravel application's `app/` directory,
from `app-modules/*/src/` packages created by [InterNACHI/modular](https://github.com/InterNACHI/modular),
or from any Composer PSR-4 autoloadable package. **InterNACHI/modular is optional**:
`lavertia/panels` does not depend on it.

This alpha integration discovers **Pages only**. Filament Resources, Widgets and plugin
contracts are not implemented by Lavertia yet.

## 1. Create a Composer-backed module

In the **host application**, install InterNACHI/modular if it is not already present:

```bash
composer require internachi/modular
php artisan make:module inventory
composer update modules/inventory -W
```

The module must be installed/autoloaded by Composer and its service provider must
be registered with Laravel, usually through its module `composer.json`.

Example module layout:

```text
app-modules/
  inventory/
    composer.json
    src/
      Providers/
        InventoryServiceProvider.php
      Lavertia/
        Pages/
          InventoryOverview.php
```

Match `for:` to the namespace in the module's **actual composer.json**. For example:

```json
{
  "name": "modules/inventory",
  "type": "library",
  "require": {
    "lavertia/panels": "^0.2"
  },
  "autoload": {
    "psr-4": {
      "Modules\\Inventory\\": "src/"
    }
  },
  "extra": {
    "laravel": {
      "providers": ["Modules\\Inventory\\Providers\\InventoryServiceProvider"]
    }
  }
}
```

The version in this example represents the **future compatible package release**.
Until Lavertia is tagged/published, continue to use the project's documented
development path repositories and existing Composer constraints.

## 2. Register module Pages with an existing Panel

`app-modules/inventory/src/Providers/InventoryServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Inventory\Providers;

use Illuminate\Support\ServiceProvider;
use Lavertia\Panels\PanelRegistry;

final class InventoryServiceProvider extends ServiceProvider
{
    public function boot(PanelRegistry $panels): void
    {
        $panels->discoverPages(
            panelId: 'admin',
            in: dirname(__DIR__).'/Lavertia/Pages',
            for: 'Modules\\Inventory\\Lavertia\\Pages',
        );
    }
}
```

This registers Pages in **only** the `admin` Panel. The Panel must already
exist in `config/lavertia.php`; modules do not silently create public routes
or bypass the Panel's middleware. `PanelRegistry` collects module registrations
during service provider boot. Routes are mounted once all service providers
have booted, regardless of provider boot order.

`app-modules/inventory/src/Lavertia/Pages/InventoryOverview.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Inventory\Lavertia\Pages;

use Lavertia\Panels\Pages\Page;
use Lavertia\Support\Components\Text;

final class InventoryOverview extends Page
{
    public static function slug(): string
    {
        return 'inventory/overview';
    }

    protected function components(): array
    {
        return [Text::make('Inventory overview')];
    }
}
```

The resulting route is `GET /admin/inventory/overview`, named
`lavertia.admin.inventory.overview`. React, Vue and Svelte all use the same
existing `lavertia/page` Inertia renderer; no frontend module bundling is
required merely to add PHP Pages.

For multiple Panels (e.g. `admin` and `branch`), call
`$panels->discoverPages()` once for each explicitly intended Panel.

## 3. Alternatively, discover Pages through config

Module service providers are not mandatory if your project prefers explicit
directory configuration. In `config/lavertia.php`:

```php
'panels' => [
    'admin' => [
        'path' => 'admin',
        'middleware' => ['web', 'auth'],
        'pages' => [
            App\Lavertia\Pages\DashboardPage::class,
        ],
        'discover_pages' => [
            [
                'in' => base_path('app-modules/inventory/src/Lavertia/Pages'),
                'for' => 'Modules\\Inventory\\Lavertia\\Pages',
            ],
        ],
    ],
],
```

Or use `Panel::make('admin')->discoverPages(in: ..., for: ...)` when mounting
your own Panel programmatically.

Discovery is recursive and deterministic. It ignores missing optional
directories, non-PHP files, non-Page classes and abstract Pages. Explicit
and discovered references to the **same** Page class are deduplicated.
Different Page classes using the same slug are rejected rather than silently
overwriting each other. Namespace declarations must match Composer PSR-4.

## 4. Development refresh for modular PHP pages

The host app's Vite config should watch module PHP paths if you want the
browser to refresh after you save module Pages:

```ts
laravel({
    input: ['resources/css/app.css', 'resources/js/app.tsx'],
    refresh: [
        'app/Lavertia/**/*.php',
        'app-modules/*/src/Lavertia/**/*.php',
        'resources/views/**',
        'routes/**',
    ],
})
```

Adjust `input` to your existing React/Vue/Svelte Starter Kit. This is
Vite's **full-browser refresh** for PHP edits, not JavaScript HMR or a
long-lived PHP runtime hot swap.

## 5. Verify and deploy

```bash
composer dump-autoload
php artisan optimize:clear
php artisan route:list --path=admin
php artisan route:cache
```

If routes are already cached, clear/rebuild the route cache after introducing
or removing module Pages. This discovery runs while routes are being
registered, and Laravel serves the cached routes in production.

**Security:** Page discovery never removes the host application's Panel
middleware. Authorization, tenancy scoping, validation and model policies
remain the responsibility of the application/module. An enabled module
can expose a route only through a configured Panel. Do not use this
feature to bypass your app's authentication or organization boundaries.
