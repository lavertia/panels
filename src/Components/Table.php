<?php

declare(strict_types=1);

namespace Lavertia\Panels\Components;

use Lavertia\Tables\Components\Table;

/**
 * @deprecated Prefer \Lavertia\Tables\Components\Table. This autoload alias preserves
 * existing Panels API while v0.2 components move into standalone packages.
 */
class_alias(Table::class, __NAMESPACE__.'\\Table');
