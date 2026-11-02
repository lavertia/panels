# P0-07: parameterized and reusable Resource Pages

## Motivation and Filament references

Filament discussion #10072 and discussion #9012 document a common failure:
navigation/index URL generation attempts to link to a route requiring a
`{record}` parameter. Discussion #19530 (v5) demonstrates a related need
for dynamic route parameters on a Panel Page. Filament issue #10979 shows
that treating a query string named `action` as framework action state can
be surprising. Lavertia keeps route parameters, immutable Page settings and
authorization action names **separate**.

References:
- https://github.com/filamentphp/filament/discussions/10072
- https://github.com/filamentphp/filament/discussions/9012
- https://github.com/filamentphp/filament/discussions/19530
- https://github.com/filamentphp/filament/issues/10979

## Choice 1: properties for simple Resource metadata

```php
use Lavertia\Panels\Resources\Resource;

final class TicketResource extends Resource
{
    protected static ?string $slug = 'tickets';
    protected static ?string $model = Ticket::class;
    protected static array $pages = [
        'list' => Pages\ListTickets::class,
        'create' => Pages\CreateTicket::class,
        'view' => Pages\ViewTicket::class,
        'edit' => Pages\EditTicket::class,
    ];

    // Or use static query(): Builder with tenant-safe scope.
    protected static ?string $queryProvider = TicketQuery::class;
}
```

`TicketQuery` implements `ResourceQueryProvider` with `query(): Builder`.
The container constructs it **for each request**. Do not store a scoped
Eloquent Builder as a mutable static variable. Model Policies and the
explicit query scope must both protect tenant data.

Legacy `slug()`, `model()`, `query()` and `pages()` method overrides remain
supported and take precedence over the protected static properties.

## Choice 2: same Page class under multiple names

```php
public static function pages(): array
{
    return [
        'list' => Pages\ListTickets::class,
        'published' => Pages\ListTickets::route('/published')
            ->action('list')
            ->parameters(['status' => 'published', 'heading' => 'Published']),
        'drafts' => Pages\ListTickets::route('/drafts')
            ->action('list')
            ->parameters(['status' => 'draft', 'heading' => 'Drafts']),
        'view' => Pages\ViewTicket::class,
        'history' => Pages\ViewTicket::route('/{record}/history/{period}')
            ->action('view')
            ->parameters(['heading' => 'History']),
    ];
}
```

The Page accesses these independently:

```php
protected function mount(): void
{
    $this->status = $this->pageParameter('status');
    $this->period = $this->routeParameter('period');
}
```

`pageParameter()` reads **fixed developer-defined parameters** from the
route registration. `routeParameter()` reads dynamic Laravel URL placeholders.
Neither reads client query strings. Validate untrusted URL placeholders before
using them for database queries or other business operations. `{record}` is
always scoped and authorized via Resource::query() + Policy before mount().

## Policy and routing contract

- The map key is unique and becomes the Laravel route-name suffix.
- `action('list')` checks Policy `viewAny`, `create` checks Policy `create`,
  `view` checks Policy `view` on a scoped record and `edit` checks `update`.
- `view`/`edit` registrations **must** include `{record}`. `list`/`create`
  must not declare `{record}`. This prevents accidentally making a
  record-bound Index Page that breaks navigation URL generation.
- Custom route paths must start with `/` and contain static segments and
  required named placeholders only. Unsafe/duplicate patterns are rejected.
- Static URL segments are registered before dynamic placeholders. This keeps
  `/tickets/published` distinct from `/tickets/{record}`.
- One Resource class may use a Page class many times; each registration has
  independent server-owned parameters and authorization mode.
- Configuration parameter names must not collide with URL placeholders.
- No arbitrary Closures, JS, HTML or Eloquent Models are serialized as
  registration parameters; only bounded scalar developer settings are valid.

## Navigation and URL generation

```php
NavigationItem::make('published', 'Published tickets')
    ->resource('tickets', 'published');

$url = TicketResource::getUrl('history', [
    'record' => $ticket->getKey(),
    'period' => '2026',
], panelId: 'admin');
```

Resource-backed navigation resolves only valid, no-required-parameter routes
and enforces the registration's Policy. For routes requiring `{record}` or
other placeholders, generate URLs from authorized, record-scoped actions.
The Panel ID may be omitted only when a current Panel route can be resolved.

## Compatibility and verification

Legacy maps `'list' => ListPage::class` remain valid. ResourcePageContract
still identifies the owning Resource and supplies a default action, while
the **registered route definition** takes precedence on reused Page classes.
The host owns real POST/PATCH/DELETE mutations, Form Requests and rate limits.
Test both URL routes and Inertia props, cross-tenant record 404, forbidden
Policies, query-string tampering, route ordering, shared Page keys, PHP style,
TypeScript adapters and browser keyboard/RTL before releasing.

## What this slice does not do

It does not automatically authorize arbitrary custom URL placeholders as
models, create new CRUD endpoints, cache per-user query providers globally,
or infer record IDs from a navigation item. Those require an explicit
developer-scoped query and Laravel Policy.
