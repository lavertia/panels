# P0-06 — Resource Pages and Auth Pages

## Guiding rule

Page, CardPage, FlatPage and SplitPage control **presentation only**.
Resources and authentication flows are separate contracts that the host may
compose with any Page variant. The final Page::__invoke still guarantees
server authorization before mount() and widget serialization.

## Resource with an explicit tenant-scoped query

```php
use Illuminate\Database\Eloquent\Builder;
use Lavertia\Panels\Resources\Resource;

final class ProjectResource extends Resource
{
    public static function slug(): string { return 'projects'; }
    public static function model(): string { return Project::class; }

    public static function query(): Builder
    {
        return Project::query()->where('organization_id', auth()->user()->current_organization_id);
    }

    public static function pages(): array
    {
        return [
            'list' => ListProjectsPage::class,
            'create' => CreateProjectPage::class,
            'view' => ViewProjectPage::class,
            'edit' => EditProjectPage::class,
        ];
    }
}

// AdminPanelProvider::panel():
return $panel->resources([ProjectResource::class]);
```

Each mapped class must implement ResourcePageContract with static resource()
and resourceAction(), or extend the optional ResourcePage base. To combine
with CardPage/FlatPage/SplitPage, implement ResourcePageContract directly
and use InteractsWithResource to obtain the current Eloquent record.

Route naming: lavertia.admin.resources.projects.list / create / view / edit.
Paths: /admin/projects, /admin/projects/create, /admin/projects/{record},
/admin/projects/{record}/edit. All GET routes inherit the Panel middleware.

Authorization: viewAny on model for list, create on model for create,
view on scoped record for view, update on scoped record for edit. If a record
is not in Resource::query()'s scope, Laravel returns 404 before the Policy
check. Enforce tenant isolation in **both** query and Model Policy.
No Eloquent Model is serialized to Inertia unless a Page explicitly creates
safe components/props. Host POST/PATCH/DELETE must have their own Form Requests
and Policies; this release intentionally does not generate mutation actions.

## Auth Routes with shared Page presentations

```php
use Lavertia\Panels\Auth\AuthPages;
use Lavertia\Panels\Auth\AuthRoute;

// Central identity routes, outside all Panels:
AuthPages::central([
    AuthRoute::make('sign-in', LoginPage::class)->middleware(['web', 'guest']),
    AuthRoute::make('verify-email', VerifyEmailPage::class)->middleware(['web', 'auth']),
], prefix: 'identity');

// Alternatively: link the same Auth Page to an existing Panel for language scope.
AuthPages::forPanel('admin', [
    AuthRoute::make('sign-in', LoginPage::class)->middleware(['web', 'guest']),
]);
```

Auth Page classes implement AuthPageContract, including a static authFlow()
returning a supported flow. An optional AuthPage base extends Page;
CardPage/SplitPage can implement the contract without another parent.
Auth routes always render standalone with no admin/sidebar navigation,
and are forced noindex,nofollow with no canonical, regardless of Page SEO.
Panel-linked auth uses its Panel's allowed locales via ResolveAuthLocale;
central auth uses Laravel's configured locale unless host middleware changes it.

All routes are **GET presentation routes only**. Real login, registration,
password reset, MFA, verification, throttling, CSRF, signed URLs and redirects
must use Fortify or a verified host identity provider and real guarded
POST/PATCH actions. Do not expose a form that submits to a placeholder.

## Extension and release gaps

- Resource custom actions, CRUD mutation endpoints and keyed subnavigation
  remain separate work with real model policies and explicit route contracts.
- Panel-linked branding/theme injection into the standalone shell is not yet
  implemented; current integration links URL/locale, not a full identity theme.
- Module auto-discovery, versioned route caching, auth callback flows and SSR
  still require integration tests and are not release-ready simply because
  these GET contracts are available.
- Test unauthenticated, unauthorized, cross-tenant, missing record, locale
  first render, guest/auth redirects and all three adapters before merging.
