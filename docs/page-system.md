# Lavertia Page System — P0-05

## One Page lifecycle, independent visual variants

The Page base class owns slug/title, static canView(), protected mount(),
protected components(), optional Panel locale/layout overrides, protected
presentation(), named regions(), and protected seo(). Its final __invoke()
enforces visibility, invokes mount, validates metadata and renders via Inertia.

A visual presentation is not an authentication or routing category.
Panel, standalone public, central auth and resource pages share one base.

## Available presentation classes

- Page: unconstrained canvas with ordinary Lavertia Components.
- CardPage: centered bordered card with cardWidth() and cardPadding().
- FlatPage: centered, borderless column with contentWidth().
- SplitPage: leftComponents() and rightComponents() and splitRatio().

Each is an abstract PHP class. All component collections contain the same
Lavertia Support Component contract, not arbitrary HTML. The frontend
adapters render the same protocol with responsive stacking and RTL support.

## Panel route example

Extend CardPage and register via the PanelProvider pages([...]) or
discoverPages(...). Laravel middleware, policy, Gate, tenant scope and CSRF
remain the responsibility of the host. canView() denies both direct requests
and generated navigation links, but is not a substitute for a model Policy.

## Public route example

Extend FlatPage and add a regular Laravel route mapping /about to the Page
invokable class. It renders as the lavertia/standalone Inertia page, without
the host admin layout or inherited default Panel appearance.

For a public page opt into SEO by overriding protected seo() with title,
description, robots = index,follow and canonical = /about.
By default all pages are noindex,nofollow. For Panel routes, noindex is
mandatory even if the Page requests indexing.

## Authentication without coupling to a presentation

Login, register, forgot, verify, confirm password and 2FA can choose any
visual Page variant. Central auth may use independent Laravel routes;
Panel-specific auth can register within a guest-enabled auth Panel.
Real authentication mutations remain in Laravel Fortify/host controllers.
Form security, CSRF, throttling and session regeneration are essential.
These P0-05 classes do not implement an authentication provider.

## Resource Pages

List, Create, Edit, View and custom pages may extend the same Page variants.
Resource registration, policy automation, route binding and CRUD action
ownership require a separate ResourcePage implementation. This initial
branch does NOT claim that those resource contracts are implemented.

## SEO and deployment requirements

Inertia React/Vue Head and Svelte svelte:head render page metadata from
validated server props. Canonical URLs accept only same-origin paths.
Public SEO requires working Inertia SSR for first-response HTML, not only
client-side JavaScript and metadata. Monitor SSR failures, preserve locale
and direction in first paint, and later add sitemap/hreflang/OpenGraph and
social metadata when public marketing sites become first-class.

## Scaffolding commands

Run these in the host application once the compatible Panels branch is installed:

    php artisan lavertia:page Settings --panel=admin --presentation=card
    php artisan lavertia:page Auth/Login --standalone --presentation=split
    php artisan lavertia:page About --standalone --presentation=flat

The command creates a PSR-4 PHP Page class from a corresponding stub.
Standalone pages are placed under app/Lavertia/PublicPages and must be
registered with explicit Laravel routes. The generator intentionally does
not invent authentication mutation endpoints or make those routes public.
## Compatibility

Existing Page subclasses still use components(), slug() and title()
without configuration changes. Page layouts sidebar/top/full/workspace
remain independent of these visual presentation variants.
