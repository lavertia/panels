<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Lavertia\Panels\Panel;
use Lavertia\Panels\PanelProvider;
use Lavertia\Panels\PanelsServiceProvider;
use Lavertia\Panels\Resources\ListRecords;
use Lavertia\Panels\Resources\Resource;
use Lavertia\Panels\Resources\Tabs\Tab;
use Lavertia\Tables\Components\Table;
use Lavertia\Tables\Components\TextColumn;
use Orchestra\Testbench\TestCase;

final class ResourceListTabsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PanelsServiceProvider::class, TabTestPanelProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        Schema::create('tab_test_records', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('status');
            $table->string('title');
        });

        TabTestRecord::query()->create(['tenant_id' => 1, 'status' => 'published', 'title' => 'Visible published']);
        TabTestRecord::query()->create(['tenant_id' => 1, 'status' => 'draft', 'title' => 'Visible draft']);
        TabTestRecord::query()->create(['tenant_id' => 2, 'status' => 'published', 'title' => 'Foreign published']);

        Gate::policy(TabTestRecord::class, TabTestPolicy::class);
    }

    public function test_tabs_filter_scoped_eloquent_rows_and_render_badges(): void
    {
        $this->be(new GenericUser(['id' => 1]));

        $this->withSession(['tenant_id' => 1])
            ->withHeader('X-Inertia', 'true')
            ->get('/tab-test/items?activeTab=published')
            ->assertOk()
            ->assertJsonPath('props.lavertia.tree.components.0.props.tabs.0.label', 'All records')
            ->assertJsonPath('props.lavertia.tree.components.0.props.tabs.1.label', 'Published')
            ->assertJsonPath('props.lavertia.tree.components.0.props.tabs.1.badge', '1')
            ->assertJsonPath('props.lavertia.tree.components.0.props.tabs.1.badgeColor', 'success')
            ->assertJsonPath('props.lavertia.tree.components.0.props.tabs.1.active', true)
            ->assertJsonPath('props.lavertia.tree.components.1.components.0.props.pagination.total', 1)
            ->assertJsonPath('props.lavertia.tree.components.1.components.0.props.rows.0.title', 'Visible published');
    }

    public function test_unknown_tab_defaults_safely_and_client_status_cannot_broaden_query(): void
    {
        $this->be(new GenericUser(['id' => 1]));
        $this->withSession(['tenant_id' => 1])
            ->withHeader('X-Inertia', 'true')
            ->get('/tab-test/items?activeTab=invalid&tenant_id=2')
            ->assertOk()
            ->assertJsonPath('props.lavertia.tree.components.0.props.tabs.0.active', true)
            ->assertJsonPath('props.lavertia.tree.components.1.components.0.props.pagination.total', 2);
    }

    public function test_tab_query_filters_remain_scoped_to_current_tenant(): void
    {
        $this->be(new GenericUser(['id' => 1]));
        $this->withSession(['tenant_id' => 2])
            ->withHeader('X-Inertia', 'true')
            ->get('/tab-test/items?activeTab=drafts')
            ->assertOk()
            ->assertJsonPath('props.lavertia.tree.components.1.components.0.props.pagination.total', 0);
    }

    public function test_guest_cannot_read_resource_tabs(): void
    {
        $this->withHeader('X-Inertia', 'true')->get('/tab-test/items')->assertForbidden();
    }

    public function test_tab_builder_rejects_invalid_labels_and_badge_colors(): void
    {
        $invalid = [
            static fn () => Tab::make(''),
            static fn () => Tab::make()->badgeColor('javascript'),
            static fn () => Tab::make()->icon('../../unsafe'),
        ];
        $rejected = 0;

        foreach ($invalid as $callback) {
            try {
                $callback();
                self::fail('Unsafe Tab configuration accepted.');
            } catch (InvalidArgumentException) {
                $rejected++;
            }
        }

        self::assertSame(count($invalid), $rejected);
    }
}

final class TabTestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->id('tab-test')
            ->middleware(['web'])
            ->resources([TabTestResource::class]);
    }
}

final class TabTestRecord extends Model
{
    protected $table = 'tab_test_records';

    protected $guarded = [];

    public $timestamps = false;
}

final class TabTestPolicy
{
    public function viewAny(?Authenticatable $user): bool
    {
        return $user !== null;
    }
}

final class TabTestResource extends Resource
{
    protected static ?string $slug = 'items';

    protected static ?string $model = TabTestRecord::class;

    protected static array $pages = ['list' => TabTestListPage::class];

    public static function query(): Builder
    {
        return TabTestRecord::query()->where('tenant_id', (int) session('tenant_id', 1));
    }
}

final class TabTestListPage extends ListRecords
{
    public static function resource(): string
    {
        return TabTestResource::class;
    }

    public static function resourceAction(): string
    {
        return 'list';
    }

    protected function tableName(): string
    {
        return 'items';
    }

    protected function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id'),
            TextColumn::make('title'),
            TextColumn::make('status'),
        ]);
    }

    protected function getTabs(): array
    {
        return [
            'all' => Tab::make('All records'),
            'published' => Tab::make('Published')
                ->badge(static fn (): int => TabTestResource::query()->where('status', 'published')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(static fn (Builder $query) => $query->where('status', 'published')),
            'drafts' => Tab::make('Drafts')
                ->modifyQueryUsing(static fn (Builder $query) => $query->where('status', 'draft')),
        ];
    }

    protected function getDefaultActiveTab(): ?string
    {
        return 'all';
    }
}
