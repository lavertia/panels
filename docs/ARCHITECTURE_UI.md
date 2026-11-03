# Architecture: Page-first runtime and swappable UI Kits

> Current state: transitional alpha. `lavertia/panels` still houses the Page class; a truly Panel-independent Composer package is a **future extraction**, not an existing feature.

## Responsibility boundaries

```text
Host Laravel application (routes, auth provider, authorization, tenancy)
  ├── Independent Page -> schema -> Inertia response
  └── Optional Panel -> routes/navigation/shared context
             |
     ComponentProtocol v1 (serializable, JSON-safe PHP components)
             |
     @lavertia/ui (types, theme variables, UI Kit identity)
             |
     React / Vue / Svelte renderers
             |
     per-app UI Registry -> selected UI Kit -> Renderer implementation
             |
     Theme tokens, Local Overrides, Plugin Components
```

- Laravel owns server authorization, CSRF, validation, query scoping and navigation access checks.
- Core cannot import shadcn, Base UI, Mantine, any kit CSS, or auth provider internals.
- UI Kit packages cannot mutate server-side permissions or Eloquent queries.
- The host app owns Inertia bootstrap and chooses a React/Vue/Svelte stack once per app build.
- The playground is a consumer/test lab only, never the canonical source of UI.
- Plugin identifiers are `vendor.component`; the serializer validates them and limits JSON tree complexity.

## Public concepts

| Concept | Contract | Scope |
| --- | --- | --- |
| Page | PHP Component tree + Inertia response | standalone or Panel |
| Panel | routes, middleware, navigation and shared context | optional group |
| Layout | component arrangement and slots | Page/Panel/Kit; final API pending |
| UI Kit | renderer map, capabilities, fallback policy | application default, Panel/Page override |
| Theme | validated semantic tokens | application default, Panel override |
| Preset | defaults combining kit/theme/layout | planned |
| Plugin | PHP component + frontend renderer + manifest | minimal registry groundwork now; manifest planned |

## Current PHP usage (implemented in this milestone)

```php
// config/lavertia.php
'ui' => [
    'kit' => 'default',
    'theme' => null,
],
'panels' => [
    'admin' => [
        'path' => 'admin',
        'middleware' => ['web', 'auth'],
        'ui_kit' => null, // inherit application
        'theme' => null,  // inherit application
        'pages' => [],
    ],
],
```

A Page works without a Panel:

```php
use Illuminate\Support\Facades\Route;
use App\Lavertia\Pages\HomePage;

Route::middleware('web')->get('/about', HomePage::class);
```

For Page-specific Kit selection, override `protected static function uiKit(): ?string` and return a registered Kit ID. Selection order: Page > Panel > Application. Valid IDs: lowercase `shadcn`, `headless`, or namespaced `vendor.marketing` (no arbitrary paths or executable code).

## React UI Component Registry (implemented in this milestone)

```tsx
import { LavertiaPage, createUiRegistry } from '@lavertia/react-ui';
import type { LavertiaPayload } from '@lavertia/ui';
import { RatingInput, CustomInput } from './local-ui';

const registry = createUiRegistry()
    .registerKit({
        id: 'shadcn',
        components: { 'text-input': CustomInput },
        fallback: 'default', // explicitly inherit built-in renderers for missing types
    })
    .register('vendor.rating-input', RatingInput)
    .override('card', MyCard);

export default function Page({ lavertia }: { lavertia: LavertiaPayload }) {
    return <LavertiaPage lavertia={lavertia} registry={registry} />;
}
```

The example assumes `MyCard`, `RatingInput`, `CustomInput` are implemented locally and the selected Kit ID is registered. It does **not** install an official Shadcn UI Kit by itself. A `fallback: 'error'` kit rejects missing renderers at runtime instead of silently displaying a different UI. App override > Kit mapping > namespaced plugin component > documented built-in fallback.

### Theme vs UI Kit

Changing validated CSS tokens in an already-loaded Theme can be instant. Switching to a new UI Kit that is not installed or compiled requires dependency installation and a Vite build. A Kit ID in Inertia props alone is not sufficient: the host must register the actual renderer. The current Theme token contract has only five colors; typography, density, layout, high contrast and complete light/dark palettes are roadmap items.

### Cross-framework requirement

The UI identity is shared in `@lavertia/ui`. React has the first implementation of Registry; Vue and Svelte will implement the same capability as separate tasks. Do not report them as compatible until adapter integration tests pass.

### Security and performance constraints

- Do not deserialize executable PHP or JavaScript from Inertia props; only an allowlisted Kit ID crosses the server boundary.
- Do not accept arbitrary dynamic imports based on incoming Kit IDs.
- Never trust hidden UI buttons for authorization.
- Keep Eloquent filtering/sorting/pagination server-side by default, with explicit client snapshot mode for small datasets.
- Test locale/dir on first HTML render and SSR parity, including Arabic RTL.
- Avoid injecting raw style/CSS/HTML into serialized component props.
