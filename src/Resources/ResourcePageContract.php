<?php

declare(strict_types=1);

namespace Lavertia\Panels\Resources;

interface ResourcePageContract
{
    /** @return class-string<Resource> */
    public static function resource(): string;

    /** 'list', 'create', 'view' or 'edit'. */
    public static function resourceAction(): string;
}
