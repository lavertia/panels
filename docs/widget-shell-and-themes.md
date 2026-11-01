# Lavertia widget-first shell and themes (P0-04)

## Design rule

A **Widget** is a PHP object implementing `Lavertia\Support\Contracts\Component`.
The serializer transports its safe `type`, `props` and `components` tree via Inertia.
React, Vue and Svelte each provide a renderer for that **same protocol**, without
reimplementing authorization, tenant scoping, session or routing.

`NavigationItem`, `NavigationGroup`, and `NavigationSlot` are composable
Component implementations. These widgets can appear inside other components
or the Panel shell. Existing PHP navigation registration remains supported.

## PHP-first usage

```php
use Lavertia\Panels\Navigation\NavigationGroup;
use Lavertia\Panels\Navigation\NavigationItem;
use Lavertia\Panels\Navigation\NavigationSlot;

return $panel
    ->layout('sidebar')
    ->shellSlots([
        NavigationSlot::make('main-top')->position('topbar')
            ->components([
                NavigationGroup::make('Workspace')->components([
                    NavigationItem::make('reports', 'Reports')->icon('chart-column'),
                ]),
            ]),
        NavigationSlot::make('tools')->position('footer')
            ->components([
                NavigationItem::make('dashboard', 'Dashboard')->icon('layout-dashboard'),
            ]),
        NavigationSlot::make('context')->position('context')
            ->components([
                NavigationGroup::make('Tools')->components([
                    NavigationItem::make('workspace-demo', 'Workspace')->icon('house'),
                ]),
            ]),
    ]);
```

A Page may opt into `static::layout(): ?string` = `workspace`, allowing
a context sidebar. NavigationItems always point to **registered Lavertia Pages**
in the same Panel. Gate abilities and ancestor visibility are checked on
the server. Slots do not generate or expose arbitrary routes. Links placed in
slots are omitted from the default sidebar to avoid duplicates, but multiple
slots can reuse one validated item.

Supported semantic positions: sidebar, topbar, footer, tenant, icon-rail,
context. The host adapter determines which positions can be displayed
in its selected layout. When unsupported, the slot does nothing; it never
falls back to an unsafe HTML renderer.

## Theme packages

A theme is not a fork of Panels. A **standalone Composer package** implements
`Lavertia\Panels\Themes\PanelTheme`, with `id(): string` and
`tokens(): array`:

```php
final class MyTheme implements PanelTheme
{
    public function id(): string { return 'my-brand'; }

    public function tokens(): array
    {
        return [
            'primary' => '#1d4ed8',
            'primary-foreground' => '#ffffff',
            'sidebar-primary' => '#1d4ed8',
            'sidebar-primary-foreground' => '#ffffff',
        ];
    }
}

return $panel->theme(new MyTheme);
```

The Panel validates the complete manifest before it enters Inertia.
Only allowlisted CSS variables and six-digit colors are allowed. CSS and
renderers remain in frontend-owned packages; no arbitrary HTML, scripts,
classes, dynamic imports or plugin source are sent in component props.

The bootstrap package template lives at
`lavertia/playground/themes/default` pending extraction into
a dedicated `lavertia/theme-default` repository. The current connected
GitHub interface cannot create that remote repository automatically.
Do not publish this as a separately hosted repository until that step is done.

Theme manifests are **color-token customization only in this slice**.
Custom complete layouts, dark-specific token variants, themed component
renderers, installable themes with assets, Theme Presets, browser preview,
user-level personalization and signed extension manifests require future
planned work. All themes must pass React/Vue/Svelte, RTL and WCAG QA.

## Extension and security constraints

- Never register external widget types by guessing a JavaScript import path.
  Use explicit allowlisted adapter registries in future versions.
- Children must satisfy `ComponentSerializer` bounds and JSON-only props.
- A widget rendering choice cannot bypass Gate, Policies or tenancy.
- Inactive/unregistered Pages serialize as `{visible: false}`; their
  labels, slugs and destinations must not be exposed to the browser.
- Navigation slots do not replace the Laravel authority for routes.
- No global event bus, arbitrary executable callbacks, or unreviewed
  JavaScript from PHP-provided manifests.
- In RTL, place regions using logical CSS and test keyboard navigation.
- Do not promise that this phase includes resizable context panels,
  pinned workspaces, Command Palette, Saved Views, drag-drop navigation
  or package-level renderer overrides. Those are tracked separately.
