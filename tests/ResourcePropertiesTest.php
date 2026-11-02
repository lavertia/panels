<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lavertia\Panels\Pages\Page;
use Lavertia\Panels\Resources\Resource;
use Lavertia\Panels\Resources\ResourcePage;
use Lavertia\Panels\Resources\ResourceQueryProvider;
use Lavertia\Panels\Resources\ResourceRoutes;
use Orchestra\Testbench\TestCase;

final class ResourcePropertiesTest extends TestCase
{
    public function test_resource_can_be_configured_using_static_properties(): void
    {
        self::assertSame('property-example', PropertyConfiguredResource::slug());
        self::assertSame(PropertyConfiguredModel::class, PropertyConfiguredResource::model());
        self::assertSame(PropertyListPage::class, PropertyConfiguredResource::pages()['list']);
        self::assertSame(
            ['mode' => 'published'],
            ResourceRoutes::definitions(PropertyConfiguredResource::class)['published']['parameters'],
        );
        self::assertInstanceOf(Builder::class, PropertyConfiguredResource::query());

        ResourceRoutes::validate(PropertyConfiguredResource::class);
    }

    public function test_query_provider_fails_closed_when_not_explicitly_configured(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UnscopedPropertyResource::query();
    }

    public function test_custom_route_collisions_are_rejected_before_registration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ResourceRoutes::validate(CollidingPropertyResource::class);
    }

    public function test_invalid_property_array_options_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ResourceRoutes::validate(InvalidArrayResource::class);
    }

    public function test_old_class_string_page_mappings_are_still_supported(): void
    {
        $definitions = ResourceRoutes::definitions(PropertyConfiguredResource::class);

        self::assertSame('/', $definitions['list']['path']);
        self::assertSame('list', $definitions['list']['action']);
        self::assertSame([], $definitions['list']['parameters']);
    }
}

final class PropertyConfiguredModel extends Model
{
    protected $table = 'unused_models';
}

final class PropertyQueryProvider implements ResourceQueryProvider
{
    public function query(): Builder
    {
        return PropertyConfiguredModel::query()->whereRaw('1 = 0');
    }
}

final class PropertyConfiguredResource extends Resource
{
    protected static ?string $slug = 'property-example';

    protected static ?string $model = PropertyConfiguredModel::class;

    protected static ?string $queryProvider = PropertyQueryProvider::class;

    protected static array $pages = [
        'list' => PropertyListPage::class,
        'published' => [
            'page' => PropertyListPage::class,
            'route' => '/published',
            'action' => 'list',
            'parameters' => ['mode' => 'published'],
        ],
    ];
}

final class UnscopedPropertyResource extends Resource
{
    protected static ?string $slug = 'no-query';

    protected static ?string $model = PropertyConfiguredModel::class;

    protected static array $pages = [
        'list' => UnscopedPropertyListPage::class,
    ];
}

final class CollidingPropertyResource extends Resource
{
    protected static ?string $slug = 'colliding';

    protected static ?string $model = PropertyConfiguredModel::class;

    public static function pages(): array
    {
        return [
            'list' => CollidingPropertyListPage::class,
            'same' => CollidingPropertyListPage::route('/')->action('list'),
        ];
    }
}

final class PropertyListPage extends ResourcePage
{
    public static function resource(): string
    {
        return PropertyConfiguredResource::class;
    }

    public static function resourceAction(): string
    {
        return 'list';
    }

    protected function components(): array
    {
        return [];
    }
}

final class UnscopedPropertyListPage extends ResourcePage
{
    public static function resource(): string
    {
        return UnscopedPropertyResource::class;
    }

    public static function resourceAction(): string
    {
        return 'list';
    }

    protected function components(): array
    {
        return [];
    }
}

final class CollidingPropertyListPage extends ResourcePage
{
    public static function resource(): string
    {
        return CollidingPropertyResource::class;
    }

    public static function resourceAction(): string
    {
        return 'list';
    }

    protected function components(): array
    {
        return [];
    }
}


final class InvalidArrayResource extends Resource
{
    protected static ?string $slug = 'invalid-array';

    protected static ?string $model = PropertyConfiguredModel::class;

    protected static array $pages = [
        'list' => PropertyListPage::class,
        'invalid' => [
            'page' => PropertyListPage::class,
            'route' => '/safe',
            'action' => 'list',
            'callback' => 'unsafe-unknown-option',
        ],
    ];
}
