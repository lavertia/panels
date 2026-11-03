# Lavertia Shadcn UI Kit — experimental React installation

**Canonical package:** [lavertia/ui-shadcn](https://github.com/lavertia/ui-shadcn). The former `lavertia/react-ui/packages/ui-shadcn` staging directory has been retired. The Kit is not yet released on npm; GitHub installations remain a **developer preview**.

Lavertia Core and PHP components have no dependency on Shadcn. ReactUI provides the default renderers and shared logic, while the Shadcn Kit supplies the Page Shell, Forms, Actions, and native Table markup.

## Prerequisites

Start with an existing official Laravel React/Inertia Starter Kit, Laravel 12/13 and the Lavertia PHP packages.

Install the UI protocol, React runtime and optional Shadcn Kit from GitHub:

```bash
npm install github:lavertia/ui#main github:lavertia/react-ui#main github:lavertia/ui-shadcn#main
```

Make sure shadcn is initialized in the Laravel application. **Do not rerun init on a configured Starter Kit.** Add any missing primitives:

```bash
npx shadcn@latest add card input label textarea checkbox button table
```

The shadcn components remain open-code files owned by the Laravel application. The package uses the application's existing `@/components/ui` alias, CSS variables, Tailwind and component variant styles.

## Configure with one command

```bash
php artisan lavertia:install --stack=react --ui=shadcn
npm run build
```

The installer validates `node_modules/@lavertia/ui-shadcn` and **seven host component files** (`card`, `input`, `label`, `textarea`, `checkbox`, `button`, `table`). It copies a thin React page wrapper to register the Shadcn Kit, sets `lavertia.ui.kit = shadcn` in `config/lavertia.php`, and adds Tailwind v4 `@source` entries.

No dependencies or remote code are installed by Artisan. The installer refuses to overwrite existing page shims without `--force`, and never replaces unrelated PHP configuration when a safe match cannot be found.

If your app only needs some Shadcn components, you may register the Kit **manually** with a subset of primitives; its Table component is optional in the JS API and falls back to the built-in renderer when missing. The standard installer intentionally uses all seven components to give a consistent experience.

## Runtime boundaries

- **Page-first:** a PHP Page may render without any Lavertia Panel.
- **Theme vs Kit:** Theme tokens control semantic colors; UI Kit controls actual markup and interaction styles.
- **Laravel authority:** Eloquent filters and pagination run on the server in `eloquent` mode. Forms use Inertia and Laravel server validation; frontend action visibility does not grant permission.
- **React only:** Vue and Svelte have separate renderers. Attempting `--stack=vue --ui=shadcn` fails with an actionable error.
- **RTL:** `lang` and `dir` come from the initial PHP/Inertia response.

## Upgrades and rollback

Do not re-run `lavertia:install` with `--force` on a customized page wrapper without backing up those changes.

For reproducible builds, **commit `package-lock.json`** (Git-installed SHAs are recorded in it) and avoid silent dependency updates. Immutable published version pins and a complete Laravel Starter Kit E2E test matrix are still milestones.

To return to the default Kit: change `ui.kit` to `default` in `config/lavertia.php` and use the default React page adapter. The command `--ui=default --force` replaces generated frontend shims but does **not** reset your PHP config automatically.

## Remaining limitations

Native Table provides search, sorting, filtering, column visibility/reordering and pagination. Advanced actions, bulk selection, shadcn Dialog, richer field adapters, final a11y/browser visual QA and automatic dependency installation remain incomplete.
