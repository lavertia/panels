# Lavertia admin UI (prototype)

The v0.1 visual vocabulary takes inspiration from the *structure and density* of mature admin frameworks such as Filament, without copying branding or private assets.

- **PageHeader:** clear page title, context label, description, and navigational header actions
- **StatsGrid / Stat:** responsive, compact KPI cards with labelled indicator tones
- **Section / Card:** progressive content hierarchy; freely composable
- **Table:** responsive scroll container, bounded client-side search and column sorting, semantic table headers, status badges, empty state and row count
- **TextInput / Infolist:** consistent accessible labels, spacing and helper text
- **Actions:** primary, secondary and outline appearance with verified safe navigation URLs

The renderers use Tailwind CSS 4 static utility classes and the application Starter Kit's existing light/dark mode. No CSS-in-JS and no new runtime UI library are required. React, Vue and Svelte must interpret the same PHP protocol.

## Security and scope

This is **not** a Filament-compatible implementation or a complete admin panel. The table accepts up to 100 serialized scalar rows, and its search/sort run **locally** on this snapshot. No hidden Eloquent query, pagination, persistence, server actions, or tenant scoping is implied. Form fields are local-only until a secure server action system is introduced. Laravel Policies and authorization remain application responsibilities.

For existing Starter Kit applications, rerun \`php artisan lavertia:install --stack=vue --force\` (or react/svelte) **only after** checking changes to any generated renderer/page files: the installer overwrites these files.
