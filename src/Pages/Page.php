<?php

declare(strict_types=1);

namespace Lavertia\Panels\Pages;

use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Lavertia\Panels\Localization\LocaleContext;
use Lavertia\Panels\PanelRegistry;
use Lavertia\Panels\Resources\ResourceContext;
use Lavertia\Panels\Resources\ResourcePageContract;
use Lavertia\Support\Components\Column;
use Lavertia\Support\Contracts\Component;
use Lavertia\Support\Serialization\ComponentProtocol;

/**
 * One Page lifecycle for panel, standalone, public, auth and resource screens.
 * Presentation is independent of route ownership and authorization.
 */
abstract class Page
{
    /** @return list<Component> */
    abstract protected function components(): array;

    public static function slug(): string
    {
        return Str::kebab(class_basename(static::class));
    }

    public static function title(): string
    {
        return Str::headline(class_basename(static::class));
    }

    /**
     * Route authorization. The host must ALSO apply authentication, policies
     * and tenancy middleware; a navigation visibility check is not security.
     */
    public static function canView(): bool
    {
        return true;
    }

    /** Runs after route middleware, before serialization, once per request. */
    protected function mount(): void
    {
    }

    /** Restrict a Page to a subset of its Panel's supported locales. */
    public static function locales(): ?array
    {
        return null;
    }

    /** Override Panel navigation: sidebar, top, full or workspace. */
    public static function layout(): ?string
    {
        return null;
    }

    /**
     * Display is independent of authentication/resource/public ownership.
     *
     * @return array{variant: string, maxWidth: string}
     */
    protected function presentation(): array
    {
        return ['variant' => 'default', 'maxWidth' => 'full'];
    }

    /**
     * Named component regions for presentation variants (e.g. split).
     *
     * @return array<string, array<string, mixed>>
     */
    protected function regions(): array
    {
        return [];
    }

    /**
     * Page-managed, HTML-safe metadata. Public indexing is opt-in: default
     * noindex also protects standalone auth and authenticated resources.
     *
     * @return array{title: string, description: string|null, robots: string, canonical: string|null}
     */
    protected function seo(): array
    {
        return [
            'title' => static::title(),
            'description' => null,
            'robots' => 'noindex,nofollow',
            'canonical' => null,
        ];
    }

    /** @return array<string, mixed> */
    final public function tree(): array
    {
        return Column::make($this->components())->toArray();
    }

    final public function __invoke(): Response
    {
        abort_unless(static::canView(), 403);

        // Resource authorization precedes mount/components/serialization;
        // subclasses cannot bypass this even with a different presentation.
        if ($this instanceof ResourcePageContract) {
            ResourceContext::authorize($this);
        }

        // Mounted state is available to components, regions and dynamic SEO.
        $this->mount();

        $locale = app()->getLocale();
        $routeName = request()->route()?->getName();
        $panelId = null;

        if (is_string($routeName)
            && preg_match('/^lavertia\.([a-z][a-z0-9_-]*)\./', $routeName, $matches) === 1
            && app(PanelRegistry::class)->hasPanel($matches[1])) {
            $panelId = $matches[1];
        }

        $presentation = $this->presentation();

        if (! in_array($presentation['variant'] ?? null, ['default', 'card', 'flat', 'split'], true)
            || ! in_array($presentation['maxWidth'] ?? null, ['sm', 'md', 'lg', 'xl', '2xl', 'full'], true)) {
            throw new InvalidArgumentException('Unsupported Page presentation configuration.');
        }

        if (isset($presentation['padding'])
            && ! in_array($presentation['padding'], ['compact', 'normal', 'spacious'], true)) {
            throw new InvalidArgumentException('Invalid Page card padding.');
        }

        if (isset($presentation['ratio'])
            && ! in_array($presentation['ratio'], ['balanced', 'start-wide', 'end-wide'], true)) {
            throw new InvalidArgumentException('Invalid Page split ratio.');
        }

        $seo = [
            'title' => static::title(),
            'description' => null,
            'robots' => 'noindex,nofollow',
            'canonical' => null,
            ...$this->seo(),
        ];

        if (! is_string($seo['title'] ?? null) || trim($seo['title']) === ''
            || mb_strlen($seo['title']) > 160
            || ! is_string($seo['robots'] ?? null)
            || ! in_array($seo['robots'], ['index,follow', 'noindex,follow', 'noindex,nofollow'], true)
            || (isset($seo['description']) && (! is_string($seo['description'])
                || mb_strlen($seo['description']) > 320))
            || (isset($seo['canonical']) && (! is_string($seo['canonical'])
                || ! str_starts_with($seo['canonical'], '/') || str_starts_with($seo['canonical'], '//')
                || preg_match('/[\x00-\x20<>\\\\]/', $seo['canonical']) === 1))) {
            throw new InvalidArgumentException('Invalid Page SEO metadata.');
        }

        // Never index Panel pages, regardless of a subclass's SEO settings.
        if ($panelId !== null) {
            $seo['robots'] = 'noindex,nofollow';
        }

        $navigation = $panelId !== null
            ? app(PanelRegistry::class)->navigation($panelId, $routeName)
            : [];

        return Inertia::render($panelId === null ? 'lavertia/standalone' : 'lavertia/page', [
            'lavertia' => [
                'version' => ComponentProtocol::VERSION,
                'title' => static::title(),
                'locale' => $locale,
                'direction' => LocaleContext::direction($locale),
                'tree' => $this->tree(),
                'navigation' => $navigation,
                'presentation' => $presentation,
                'regions' => $this->regions(),
                'seo' => [
                    ...$seo,
                    'canonical' => isset($seo['canonical'])
                        ? url($seo['canonical'])
                        : null,
                ],
            ],
        ]);
    }
}
