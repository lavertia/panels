<?php

declare(strict_types=1);

namespace Lavertia\Panels\Resources;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Compose into any Page presentation, e.g.
 * CardPage implements ResourcePageContract { use InteractsWithResource; }
 */
trait InteractsWithResource
{
    protected function record(): Model
    {
        $record = request()->attributes->get(ResourceContext::RECORD_ATTRIBUTE);

        if (! $record instanceof Model) {
            throw new LogicException('No Eloquent record is bound to this Resource Page.');
        }

        return $record;
    }
}
