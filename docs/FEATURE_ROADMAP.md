# Lavertia feature backlog — grounded in founder's 100 answers

**Legend:** `DONE` = merged implementation with tests; `PARTIAL` = usable subset, not feature-complete; `NEXT` = agreed direction awaiting implementation; `OPEN` = unresolved product decision. Do not mistake an approved answer for shipped functionality.

| Track | Survey questions | Requirements and acceptance criteria | State |
| --- | --- | --- | --- |
| Product vision | 1–10 | PHP-driven UI-agnostic app framework; independent Pages; visual speed; extensible commercial Plugins later | PARTIAL |
| Developer experience | 11–20 | React prioritized; PHP-only common flows; fast first CRUD; beginner and advanced escape hatches; small-to-large apps | PARTIAL |
| Core & ownership | 21–30 | Panel optional; default app UI; auth-provider independence; Shell/Widget contracts; no UI source in Core | PARTIAL |
| PHP components | 31–40 | Fluent schemas; nested components; versioned types; client/server events; typed props; new custom types | PARTIAL |
| UI Kit system | 41–50 | Select Kit at install; swap/mix Kits; package manifests; component override; fail-fast capabilities; framework adapters | PARTIAL — selection/React registry first |
| Themes & presets | 51–60 | semantic tokens, typography, radius, density, dark/system, live preview, per-panel override, Presets | PARTIAL — five legacy color tokens |
| Pages/layouts | 61–70 | Page variants (OPEN Q61), Layout API (OPEN Q64), many components per page, navigation regions, tenancy, lifecycle | PARTIAL |
| Forms/Tables/Resources | 71–80 | client-fast fields, Laravel validation, Eloquent-backed scalable tables, CRUD Pages, relationship managers | PARTIAL — Resources not released |
| Plugins & marketplace | 81–90 | namespaced components, PHP/frontend extensions, Registry, generators, modular discovery, manifests, paid ecosystem | PARTIAL — namespace/React registration |
| Quality/Release | 91–100 | SSR optional, instant RTL, a11y WCAG 2.2 AA, tests per adapter, runtime performance metrics, stable v1 | NEXT |

## Execution sequence and milestones

### P0 — Contracts and truth in documentation (this milestone)
- [x] Archive all 100 answers verbatim and distinguish unresolved points.
- [x] Support vendor-qualified Component Protocol types in PHP.
- [x] Add shared frontend UI Kit identifier to protocol payload.
- [x] Add React renderer Registry with overrides, kit fallback and plugin registration.
- [x] Make Page/Panel/Application kit precedence explicit in PHP and test independent routes.
- [x] Allow application Theme fallback for standalone Pages.
- [ ] Cross-repository Laravel Starter Kit E2E visual QA.

### P1 — UI ownership and production-ready default Kit (in progress)
- [x] Split React's monolithic renderer into separate Form, Table, Field, Action and Page Shell modules (lavertia/react-ui PR #3).
- [x] Stage an independent-build portable Shadcn React Kit prototype (Shell, Card, TextInput, PageHeader) with SSR/RTL/theme tests (lavertia/react-ui PR #4).
- [x] Implement Shadcn Form, Textarea, Checkbox, Select and Action renderers using a shared Inertia hook (React UI PRs #5–#7).
- [x] Migrate Shadcn Kit to canonical `lavertia/ui-shadcn` repo with dedicated typecheck, SSR and package CI (ui-shadcn PR #1).
- [x] Split shared snapshot/Eloquent table state from default rendering (react-ui PR #8).
- [x] Implement a native Shadcn Table via host primitives, reusable state and SSR tests (ui-shadcn PR #2).
- [ ] Finish Dialog, advanced Tables, fields and real browser/visual/a11y QA (issue #47).
- [x] Add opt-in alpha `--ui=shadcn` React installer with safe dependency/config checks and Testbench coverage (Panels PR #49).
- [x] Update alpha React installer to the official Shadcn Kit and require native Table primitives (Panels PR #51).
- [ ] Finish interactive install wizard, published package distribution, immutable release tags and full Starter Kit E2E QA.
- [ ] Test Page standalone, Marketing, Auth and Dashboard under the default Kit.
- [ ] Make Page not depend on the Panels Composer package (likely package extraction with deprecation adapter).

### P2 — UI switching and parity
- [ ] Implement equivalent registries in Vue/Svelte.
- [ ] Add `ui-headless` with actual styled component implementations, not empty primitives.
- [ ] Define capability matrix, versioned manifests and predictable fallback error behavior.
- [ ] Implement per-page and per-scope Kit overrides, lazy imports, SSR-safe selection.
- [ ] Test switching Kits without modifying PHP Component Schemas.

### P3 — Complete Design System and layout architecture
- [ ] Expand semantic tokens: backgrounds, surfaces, typography, spacing, radii, density, light/dark/system/high contrast.
- [ ] Define provider-first Layout API, regions/slots, auth/marketing/app presets; resolve Q61/Q64 with experiments first.
- [ ] Panel Shell: notifications, global search, menus, multi-nav, footer, tenant switcher, mobile UX.
- [ ] Cross-framework RTL + localization from first paint.

### P4 — Filament-level developer productivity
- [ ] Stable Form state, server validation and hooks, async searches, rich field plugins, fast Repeaters.
- [ ] Scalable Eloquent Table query DSL, filters, sorting, columns, selection, actions, pagination.
- [ ] Resources: List/Create/View/Edit/Relations, policies, hooks and tenant enforcement.
- [ ] Generators for Pages, Components, UI Kits, Themes, Plugins and Resources.
- [ ] Laravel Modular integration across independent pages and feature modules.

### P5 — Community, releases and commercial extensions
- [ ] Manifest/version compatibility contracts, safe Plugin installation/upgrade/testing.
- [ ] Registry/community discovery and optional commercial licensing/distribution integration.
- [ ] Published immutable packages, complete docs, migration guides and stability guarantees.
- [ ] Benchmarks, a11y audits, SSR smoke, security and tenant isolation gates for stable 1.0.

## Definition of done

A feature is DONE only when PHP/JS code and tests are merged, docs and examples match shipped APIs, SSR/build compatibility and security risks are checked, and cross-stack parity is explicitly stated (or marked unsupported). A UI Kit isn't complete because its manifest exists: verify accessible Forms, Tables, Shell and interaction states on a real app.
