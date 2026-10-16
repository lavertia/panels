<?php

declare(strict_types=1);

namespace Lavertia\Panels\Components;

use Lavertia\Forms\Components\TextInput;

/**
 * @deprecated Prefer \Lavertia\Forms\Components\TextInput. This autoload alias preserves
 * existing Panels API while v0.2 components move into standalone packages.
 */
class_alias(TextInput::class, __NAMESPACE__.'\\TextInput');
