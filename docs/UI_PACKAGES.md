# Lavertia frontend extraction (alpha)

The **default renderer owners** are `lavertia/react-ui`, `lavertia/vue-ui`, and `lavertia/svelte-ui`. Independent UI Kits, such as `lavertia/ui-shadcn`, own their own presentation without changing the shared protocol. `lavertia/ui` owns frontend-neutral protocol types, table utilities and safe theme CSS. The `playground` is a consumer and must not be treated as a library source. Panels retains *thin* per-stack install shims solely for official Laravel Inertia Starter Kit integration.

## Installation

1. Install the Composer PHP packages (`panels`, `support`, `forms`, `tables`).
2. Install **one** UI npm Git package matching your stack:

   ```sh
   npm install github:lavertia/react-ui#main
   # OR npm install github:lavertia/vue-ui#main
   # OR npm install github:lavertia/svelte-ui#main
   ```

3. Run `php artisan lavertia:install --stack=react` (or vue/svelte), then `npm run build`.
4. To use an external theme, install the Composer theme package and configure:

   ```php
   'panels' => [
       'admin' => [
           // ...existing middleware/pages...
           'theme' => \Lavertia\ThemeDefault\ClassicTheme::class,
       ],
   ],
   ```

5. Check Inertia `lavertia.theme` includes `id` and five hex tokens; React/Vue/Svelte pages apply them using shared CSS variables.

The public PHP `PanelTheme` interface is `Lavertia\\Panels\\Themes\\PanelTheme`. The accepted CSS tokens are `primary`, `primary-foreground`, `sidebar-primary`, `sidebar-primary-foreground`, and `ring`. Each value must be a six-digit hex color. The Panel config uses a **class-string**, not a closure or object, for route/config cache support.

## Migration notes

The installer overwrites generated page, renderer and types files only with `--force`. Back up and diff any customizations before using force. Prior to this extraction, those generated files held full runtime UI code; **custom edits must be ported into the package or a documented extension point**. The new installer files are tiny wrappers.

Theme variables are scoped to the Lavertia page; component structural classes still use the host Starter Kit's Tailwind CSS and light/dark mode. On Tailwind 4, external package source classes may need `@source` in `resources/css/app.css` for development: the adapter packages contain static Tailwind utilities under `node_modules` and are not guaranteed to be scanned automatically.

These alpha Git dependencies are not released npm packages. Pin Git commits for reproducibility before production. No claim of successful production integration is made until cross-stack CI and full Starter Kit smoke tests pass.

## Official React UI Kit (P1 developer preview)

The React runtime now exports separate Form, Table, Field and Action renderers, plus `DefaultPageShell`. `UiKitDefinition.shell` lets an application supply an entirely different Page shell without rewriting PHP schemas. [lavertia/ui-shadcn](https://github.com/lavertia/ui-shadcn) is the **canonical standalone React UI Kit repository**, migrated from the temporary staging directory. It contains its own npm manifest, independent CI, host-supplied shadcn components, and working Page Shell, Form, Action and Table renderer implementations. Read [the Shadcn installer guide](SHADCN_INSTALL.md).

The dedicated repository now exists. [Issue #47](https://github.com/lavertia/panels/issues/47) tracks Dialog/advanced components, versioned releases and real-app visual testing. Do not advertise the developer preview as a finished UI Kit, a published npm release or a cross-framework solution. It is optional; existing users of default React, Vue and Svelte adapters remain unaffected.
