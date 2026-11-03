# Experimental Shadcn UI Kit installation (React only)

The Shadcn UI Kit prototype currently lives at `lavertia/react-ui/packages/ui-shadcn`, **not** in a published npm registry or in a standalone `lavertia/ui-shadcn` repository. This is developer-preview tooling. The built-in `default` UI remains the stable installation route.

## Before installation

Start with an official Laravel React/Inertia Starter Kit and install the Lavertia PHP packages per the project README. Ensure your host already has shadcn configured and the needed primitives:

```bash
npm install github:lavertia/react-ui#main
npx shadcn@latest add card input label textarea checkbox button
```

Clone the React UI repository next to your Laravel app and install the local staging kit:

```bash
git clone https://github.com/lavertia/react-ui.git ../lavertia-react-ui
npm install ../lavertia-react-ui/packages/ui-shadcn
```

The local kit package has its own TypeScript build and tests; its `prepare` script builds its `dist` folder. This is deliberately not a reproducible production deployment until versioned distribution is ready.

## Configure with one command

```bash
php artisan lavertia:install --stack=react --ui=shadcn
npm run build
```

The installer checks the package and six host-owned primitives, writes thin shims and a registry wrapper, selects `ui.kit='shadcn'` in `config/lavertia.php`, and adds Tailwind v4 `@source` directives if `resources/css/app.css` exists. **No remote code is installed or executed by Artisan.** It never overwrites custom PHP config it cannot update safely.

If you need shadcn with Vue or Svelte, use `--ui=default` until framework-specific compatible Kits are implemented. The installer rejects unsupported combinations.

Re-running without `--force` never overwrites an existing Lavertia page. Use `--force` only after backing up application customizations. To revert your frontend to the default renderer, re-run `php artisan lavertia:install --stack=react --ui=default --force`, then set `ui.kit` to `default` explicitly in `config/lavertia.php`. No UI package is silently removed.

## Known limitations

- The current Shadcn adapter has Forms and Actions but still reuses the default renderer for Tables and some advanced widgets.
- Selected kit needs an actual registered runtime; a PHP config value alone cannot install JavaScript.
- Shadcn open-code components and their import paths remain owned by the Laravel application.
- True immutable package releases, dependency installation prompts, fully interactive setup, RTL visual tests and frontend end-to-end verification remain milestones.
