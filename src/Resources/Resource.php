<?php

declare(strict_types=1);

namespace Lavertia\Panels\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Business Resources own model scope, authorization policy and Page mapping.
 * Presentation stays in Page/CardPage/FlatPage/SplitPage subclasses.
 */
abstract class Resource
{
    /** One URL segment (e.g. 'projects'), unique per Panel. */
    abstract public static function slug(): string;

    /** @return class-string<Model> */
    abstract public static function model(): string;

    /**
     * Explicit, tenant/permission-scoped Eloquent query for record routes.
     * Never use an unscoped query for a multi-tenant Resource.
     *
     * @return Builder<Model>
     */
    abstract public static function query(): Builder;

    /**
     * Map list/create/view/edit to Pages implementing ResourcePageContract.
     * The host owns POST/PUT/PATCH/DELETE routes and input validation.
     *
     * @return array<string, class-string<\Lavertia\Panels\Pages\Page>>
     */
    abstract public static function pages(): array;
}
