<?php

declare(strict_types=1);

namespace Lavertia\Panels\Components;

/**
 * @deprecated Prefer \Lavertia\Forms\Components\Select. This autoload alias preserves
 * existing Panels API while v0.2 components move into standalone packages.
 */
class_alias(\Lavertia\Forms\Components\Select::class, __NAMESPACE__.'\\Select');
