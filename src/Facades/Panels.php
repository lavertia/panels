<?php

namespace Lavertia\Panels\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Lavertia\Panels\Panels
 */
class Panels extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Lavertia\Panels\Panels::class;
    }
}
