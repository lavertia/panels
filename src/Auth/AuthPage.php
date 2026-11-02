<?php

declare(strict_types=1);

namespace Lavertia\Panels\Auth;

use Lavertia\Panels\Pages\Page;

/**
 * Optional convenience base. For cards/splits, implement AuthPageContract on
 * CardPage/FlatPage/SplitPage instead. Authentication is always owned by
 * the host's Fortify/SSO controllers, not a view or theme.
 */
abstract class AuthPage extends Page implements AuthPageContract {}
