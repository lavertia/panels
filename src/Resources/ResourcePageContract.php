<?php

declare(strict_types=1);

namespace Lavertia\Panels\Resources;

use Lavertia\Panels\Resources\Resource as LavertiaResource;

interface ResourcePageContract
{
    /** @return class-string<LavertiaResource> */
    public static function resource(): string;

    /** 'list', 'create', 'view' or 'edit'. */
    public static function resourceAction(): string;
}
