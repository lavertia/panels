<?php

declare(strict_types=1);

namespace Lavertia\Panels\Auth;

/** Presentation-independent marker for a first-class identity flow Page. */
interface AuthPageContract
{
    /** login, register, forgot-password, reset-password, verify-email, etc. */
    public static function authFlow(): string;
}
