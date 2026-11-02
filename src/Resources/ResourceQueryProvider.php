<?php

declare(strict_types=1);

namespace Lavertia\Panels\Resources;

use Illuminate\Database\Eloquent\Builder;

/**
 * A container-resolved, request-scoped query builder. Unlike static Eloquent
 * builders or closures kept in a global property, this can safely resolve
 * the active user and tenant on every request.
 */
interface ResourceQueryProvider
{
    public function query(): Builder;
}
