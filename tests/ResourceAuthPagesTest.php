<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Lavertia\Panels\Auth\AuthPageContract;
use Lavertia\Panels\Auth\AuthPages;
use Lavertia\Panels\Auth\AuthRoute;
use Lavertia\Panels\Components\Section;
use Lavertia\Panels\Navigation\NavigationItem;
use Lavertia\Panels\Pages\CardPage;
use Lavertia\Panels\Pages\FlatPage;
use Lavertia\Panels\Pages\SplitPage;
use Lavertia\Panels\Panel;
use Lavertia\Panels\PanelProvider;
use Lavertia\Panels\PanelRegistry;
use Lavertia\Panels\PanelsServiceProvider;
use Lavertia\Panels\Resources\InteractsWithResource;
use Lavertia\Panels\Resources\Resource;
use Lavertia\Panels\Resources\ResourcePage;
use Lavertia\Panels\Resources\ResourcePageContract;
use Orchestra\Testbench\TestCase;

final class ResourceAuthPagesTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PanelsServiceProvider::class, ResourceAuthPanelProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        Schema::create('resource_demo_entries', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('title');
        });

        EntryModel::query()->create(['tenant_id' => 1, 'title' => 'First tenant']);
        EntryModel::query()->create(['tenant_id' => 2, 'title' => 'Other tenant']);

        Gate::policy(EntryModel::class, EntryPolicy::class);

        AuthPages::central([
            AuthRoute::make('login-preview', CentralAuthLoginPage::class)->middleware(['web', 'guest']),
        ]);

        AuthPages::forPanel('demo', [
            AuthRoute::make('login-preview', CentralAuthLoginPage::class)->middleware(['web', 'guest']),
        ]);
    }

    public function test_resource_routes_register_actions_and_authorize_before_mount(): void
    {
        $this->be(new GenericUser(['id' => 7]));

        $this->withSession(['tenant_id' => 1])
            ->withHeader('X-Inertia', 'true')
            ->get('/demo/entries')
            ->assertOk()
            ->assertJsonPath('props.lavertia.tree.components.0.props.title', 'Entry listing');

        $this->get('/demo/entries/create')
            ->assertOk()
            ->assertJsonPath('props.lavertia.presentation.variant', 'flat');

        $this->get('/demo/entries/1')
            ->assertOk()
            ->assertJsonPath('props.lavertia.presentation.variant', 'card')
            ->assertJsonPath('props.lavertia.tree.components.0.props.title', 'First tenant');

        $this->get('/demo/entries/1/edit')
            ->assertOk()
            ->assertJsonPath('props.lavertia.presentation.variant', 'split')
            ->assertJsonPath('props.lavertia.regions.left.components.0.props.title', 'Edit First tenant');

        self::assertSame('lavertia.demo.resources.entries.view', app('router')->getRoutes()
            ->match(Request::create('/demo/entries/1', 'GET'))->getName());

        $nav = app(PanelRegistry::class)->navigation('demo', 'lavertia.demo.resources.entries.edit');
        self::assertCount(2, $nav);
        $links = array_column($nav, null, 'slug');
        self::assertSame('/demo/entries', $links['entries']['url']);
        self::assertTrue($links['entries']['active']);
    }

    public function test_cross_tenant_record_is_not_found_and_unpermitted_edit_is_forbidden(): void
    {
        $this->be(new GenericUser(['id' => 7]));
        $this->withSession(['tenant_id' => 1]);

        $this->get('/demo/entries/2')->assertNotFound();
        $this->get('/demo/entries/2/edit')->assertNotFound();

        $this->withSession(['tenant_id' => 1, 'deny_update' => true])
            ->get('/demo/entries/1/edit')
            ->assertForbidden();
    }

    public function test_resource_policy_rejects_guests_and_unregistered_records(): void
    {
        $this->get('/demo/entries')->assertForbidden();
        $this->get('/demo/entries/create')->assertForbidden();
        $this->get('/demo/entries/no-such-id')->assertNotFound();

        $this->be(new GenericUser(['id' => 7]));
        $this->get('/demo/entries/0')->assertNotFound();
    }

    public function test_same_page_class_can_have_multiple_named_routes_with_fixed_parameters(): void
    {
        $this->be(new GenericUser(['id' => 7]));
        $this->withSession(['tenant_id' => 1])->withHeader('X-Inertia', 'true');

        $this->get('/demo/entries/active?heading=untrusted')
            ->assertOk()
            ->assertJsonPath('props.lavertia.tree.components.0.props.title', 'Active entries');

        $this->get('/demo/entries/archived')
            ->assertOk()
            ->assertJsonPath('props.lavertia.tree.components.0.props.title', 'Archived entries');

        $this->get('/demo/entries/1/history/2026')
            ->assertOk()
            ->assertJsonPath('props.lavertia.tree.components.0.props.title', 'First tenant history 2026');

        self::assertSame('/demo/entries/active', EntryResource::getUrl('active', [], 'demo'));
        self::assertSame(
            '/demo/entries/1/history/2026',
            EntryResource::getUrl('history', ['record' => 1, 'year' => '2026'], 'demo'),
        );

        $nav = app(PanelRegistry::class)->navigation('demo', 'lavertia.demo.resources.entries.active');
        self::assertCount(2, $nav);
        $links = array_column($nav, null, 'slug');
        self::assertTrue($links['active-entries']['active']);
    }

    public function test_reused_page_keeps_policy_scope_for_record_aliases(): void
    {
        $this->be(new GenericUser(['id' => 7]));
        $this->withSession(['tenant_id' => 1])
            ->withHeader('X-Inertia', 'true');

        $this->get('/demo/entries/2/history/2026')->assertNotFound();
        $this->get('/demo/entries/1/history/2026?action=edit')->assertOk();

        // The class normally serves View, but this separate registration
        // requires Update (edit) Policy, not the class's static view action.
        $this->get('/demo/entries/1/review')->assertOk();
        $this->withSession(['tenant_id' => 1, 'deny_update' => true])
            ->get('/demo/entries/1/review')
            ->assertForbidden();
    }

    public function test_invalid_page_descriptors_cannot_shadow_security_or_routes(): void
    {
        $invalid = [
            static fn () => EntryListPage::route('/{record?}')->action('list'),
            static fn () => EntryListPage::route('/safe')->parameters(['record' => 2]),
            static fn () => EntryListPage::route('/safe')->parameters(['heading' => new \stdClass]),
            static fn () => EntryViewPage::route('/history')->action('view')->resolve('history'),
            static fn () => EntryListPage::route('/{record}')->action('list')->resolve('active'),
            static fn () => EntryListPage::route('/{year}')->action('list')->parameters(['year' => '2026'])->resolve('active'),
        ];

        $rejected = 0;

        foreach ($invalid as $bad) {
            try {
                $bad();
                self::fail('Unsafe Resource Page definition was accepted.');
            } catch (InvalidArgumentException) {
                $rejected++;
            }
        }

        self::assertCount(6, $invalid);
        self::assertSame(6, $rejected);
    }

    public function test_central_and_panel_auth_keep_noindex_without_admin_shell(): void
    {
        $this->withHeader('X-Inertia', 'true')
            ->get('/identity/login-preview')
            ->assertOk()
            ->assertJsonPath('component', 'lavertia/standalone')
            ->assertJsonPath('props.lavertia.seo.robots', 'noindex,nofollow')
            ->assertJsonPath('props.lavertia.seo.canonical', null);

        $this->get('/demo/auth/login-preview?lang=ar')
            ->assertOk()
            ->assertJsonPath('component', 'lavertia/standalone')
            ->assertJsonPath('props.lavertia.locale', 'ar')
            ->assertJsonPath('props.lavertia.direction', 'rtl')
            ->assertJsonPath('props.lavertia.seo.robots', 'noindex,nofollow');

        self::assertNull(app(PanelRegistry::class)->panelForRoute('lavertia-auth.demo.login-preview'));
    }

    public function test_rejects_invalid_resource_and_auth_registration(): void
    {
        $rejected = 0;

        foreach ([
            static fn () => Panel::make()->resources([MismatchedResource::class]),
            static fn () => AuthRoute::make('bad?path', CentralAuthLoginPage::class),
            static fn () => AuthRoute::make('auth', EntryViewPage::class),
            static fn () => AuthPages::central([AuthRoute::make('login', CentralAuthLoginPage::class)]),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Unsafe Page registration was accepted.');
            } catch (InvalidArgumentException) {
                $rejected++;
            }
        }

        self::assertSame(4, $rejected);
    }
}

final class ResourceAuthPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->id('demo')
            ->middleware(['web'])
            ->locales(['en', 'ar'])
            ->resources([EntryResource::class])
            ->navigationItems([
                NavigationItem::make('entries', 'Entries')->resource('entries'),
                NavigationItem::make('active-entries', 'Active entries')->resource('entries', 'active'),
            ]);
    }
}

final class EntryModel extends Model
{
    protected $table = 'resource_demo_entries';

    public $timestamps = false;

    protected $guarded = [];
}

final class EntryPolicy
{
    public function viewAny(?Authenticatable $user): bool
    {
        return $user !== null;
    }

    public function create(?Authenticatable $user): bool
    {
        return $user !== null;
    }

    public function view(?Authenticatable $user, EntryModel $record): bool
    {
        return $user !== null && $record->tenant_id === 1;
    }

    public function update(?Authenticatable $user, EntryModel $record): bool
    {
        return $user !== null && $record->tenant_id === 1 && ! session('deny_update', false);
    }
}

final class EntryResource extends Resource
{
    protected static ?string $slug = 'entries';

    protected static ?string $model = EntryModel::class;

    public static function query(): Builder
    {
        return EntryModel::query()->where('tenant_id', (int) session('tenant_id', 1));
    }

    public static function pages(): array
    {
        return [
            'list' => EntryListPage::class,
            'active' => EntryListPage::route('/active')
                ->action('list')
                ->parameters(['heading' => 'Active entries']),
            'archived' => EntryListPage::route('/archived')
                ->action('list')
                ->parameters(['heading' => 'Archived entries']),
            'create' => EntryCreatePage::class,
            'view' => EntryViewPage::class,
            'edit' => EntryEditPage::class,
            'history' => EntryViewPage::route('/{record}/history/{year}')
                ->action('view')
                ->parameters(['heading' => 'history']),
            'review' => EntryViewPage::route('/{record}/review')
                ->action('edit')
                ->parameters(['heading' => 'review']),
        ];
    }
}

final class MismatchedResource extends Resource
{
    public static function slug(): string
    {
        return 'mismatch';
    }

    public static function model(): string
    {
        return EntryModel::class;
    }

    public static function query(): Builder
    {
        return EntryModel::query();
    }

    public static function pages(): array
    {
        return ['view' => EntryViewPage::class];
    }
}

final class EntryListPage extends ResourcePage
{
    public static function resource(): string
    {
        return EntryResource::class;
    }

    public static function resourceAction(): string
    {
        return 'list';
    }

    protected function components(): array
    {
        return [Section::make((string) $this->pageParameter('heading', 'Entry listing'))];
    }
}

final class EntryCreatePage extends FlatPage implements ResourcePageContract
{
    use InteractsWithResource;

    public static function resource(): string
    {
        return EntryResource::class;
    }

    public static function resourceAction(): string
    {
        return 'create';
    }

    protected function components(): array
    {
        return [Section::make('New entry')];
    }
}

final class EntryViewPage extends CardPage implements ResourcePageContract
{
    use InteractsWithResource;

    public static function resource(): string
    {
        return EntryResource::class;
    }

    public static function resourceAction(): string
    {
        return 'view';
    }

    protected function components(): array
    {
        return [Section::make($this->record()->title
            .($this->routeParameter('year') === null ? '' : ' history '.$this->routeParameter('year')))];
    }
}

final class EntryEditPage extends SplitPage implements ResourcePageContract
{
    use InteractsWithResource;

    public static function resource(): string
    {
        return EntryResource::class;
    }

    public static function resourceAction(): string
    {
        return 'edit';
    }

    protected function leftComponents(): array
    {
        return [Section::make('Edit '.$this->record()->title)];
    }

    protected function rightComponents(): array
    {
        return [Section::make('Inspector')];
    }
}

final class CentralAuthLoginPage extends CardPage implements AuthPageContract
{
    public static function authFlow(): string
    {
        return 'login';
    }

    protected function seo(): array
    {
        return [
            'title' => 'Sign in',
            'description' => null,
            'robots' => 'index,follow',
            'canonical' => '/identity/login-preview',
        ];
    }

    protected function components(): array
    {
        return [Section::make('Authentication preview')];
    }
}
