<?php

declare(strict_types=1);

namespace Lavertia\Panels\Components;

use Lavertia\Forms\Components\Textarea;

/**
 * @deprecated Prefer \Lavertia\Forms\Components\Textarea. This autoload alias preserves
 * existing Panels API while v0.2 components move into standalone packages.
 */
class_alias(Textarea::class, __NAMESPACE__.'\\Textarea');
