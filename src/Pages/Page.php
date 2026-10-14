<?php

declare(strict_types=1);

namespace Lavertia\Panels\Pages;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;
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

    /** @return array<string, mixed> */
    final public function tree(): array
    {
        return Column::make($this->components())->toArray();
    }

    final public function __invoke(): Response
    {
        return Inertia::render('lavertia/page', [
            'lavertia' => [
                'version' => ComponentProtocol::VERSION,
                'title' => static::title(),
                'tree' => $this->tree(),
            ],
        ]);
    }
}
