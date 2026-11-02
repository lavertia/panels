<?php

declare(strict_types=1);

namespace Lavertia\Panels\Resources;

use Lavertia\Panels\Pages\Page;

/**
 * Optional convenience base. To use Card/Flat/Split presentation, implement
 * ResourcePageContract on that Page subclass instead.
 */
abstract class ResourcePage extends Page implements ResourcePageContract
{
    use InteractsWithResource;
}
