<?php

declare(strict_types=1);

namespace Lavertia\Panels\Pages;

use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lavertia\Panels\Localization\LocaleContext;
use Lavertia\Panels\PanelRegistry;
use Lavertia\Support\Components\Column;
use Lavertia\Support\Contracts\Component;
use Lavertia\Support\Serialization\ComponentProtocol;

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

    /** Override to restrict this Page to a subset of the Panel's supported locales. */
    public static function locales(): ?array
    {
        return null;
    }

    /** Override to 'sidebar', 'top', 'full' or 'workspace'. Null inherits the Panel layout. */
    public static function layout(): ?string
    {
        return null;
    }

    /** @return array<string, mixed> */
    final public function tree(): array
    {
        return Column::make($this->components())->toArray();
    }

    final public function __invoke(): Response
    {
        $locale = app()->getLocale();

        $routeName = request()->route()?->getName();
        $navigation = [];

        if (is_string($routeName)
            && preg_match('/^lavertia\.([a-z][a-z0-9_-]*)\./', $routeName, $matches) === 1) {
            $navigation = app(PanelRegistry::class)->navigation($matches[1], $routeName);
        }

        return Inertia::render('lavertia/page', [
            'lavertia' => [
                'version' => ComponentProtocol::VERSION,
                'title' => static::title(),
                'locale' => $locale,
                'direction' => LocaleContext::direction($locale),
                'tree' => $this->tree(),
                'navigation' => $navigation,
            ],
        ]);
    }
}
