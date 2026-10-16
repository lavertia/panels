<?php

declare(strict_types=1);

namespace Lavertia\Panels\Components;

/**
 * @deprecated Prefer \Lavertia\Tables\Components\SelectFilter. This autoload alias preserves
 * existing Panels API while v0.2 components move into standalone packages.
 */
class_alias(\Lavertia\Tables\Components\SelectFilter::class, __NAMESPACE__.'\\SelectFilter');
