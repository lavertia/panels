# Lavertia design decisions — 2026-11-03

**Authority:** The founder's [100 answers](VISION_100_ANSWERS.md) override earlier assistant suggestions. Approved product direction does not imply an implementation already exists.

| ID | Decision | Basis | Status |
| --- | --- | --- | --- |
| D-001 | Lavertia builds all kinds of pages; Panel is an optional organizational layer | Q5, Q21, Q70 | Approved |
| D-002 | PHP-first declarative schemas; React/Vue/Svelte escape hatches | Q12–13, Q31–40 | Approved |
| D-003 | Core depends on Protocol, not shadcn or any fixed UI library | Q22–23, Q29–30, Q41–50 | Approved |
| D-004 | Default UI Kit per app, optional Panel overrides, with Page escape hatch | Q27, Q46–48 | Approved direction; technical precedence implemented in P0 |
| D-005 | Theme is independent from Kit; Preset combines Kit/Theme/Layout | Q51–60 | Approved direction; full token API pending |
| D-006 | Authentication provider chosen by application, no hard dependency | Q24 | Approved |
| D-007 | Plugins can be custom and commercially distributed in future | Q6, Q81–90, Q98 | Approved intent; ecosystem mechanics pending |
| D-008 | Eloquent query authority, Laravel Policies/Validation, explicit SSR/RTL/a11y checks | Q74–80, Q91–100 | Approved |
| D-009 | Formal Page variants and naming remain undecided | Q61 | Open |
| D-010 | Layout API and ownership remain undecided | Q64 | Open |
| D-011 | Selected Kit identity is `lavertia.uiKit.id`, with `default` preserving legacy runtime | Implementation detail (proposal, tested P0) | Implemented alpha |
| D-012 | React Registry supports global plugin + overrides + explicit fallback mode | Implementation detail (proposal, tested P0) | Implemented alpha |

## Migration caution

Current `lavertia/panels` owns Page and the installer. This is an **existing architectural constraint**; do not market it as a completely independent Core. Future extraction must ship backwards-compatible aliases and a migration test, not an unreviewed namespace rename.
