<?php

declare(strict_types=1);
use Lavertia\Panels\Http\Middleware\ResolveLocale;

return [
    /*
     * Register pages explicitly. Your Starter Kit still owns auth and layouts.
     * Example:
     * 'panels' => [
     *     'admin' => [
     *         'path' => 'admin',
     *         'middleware' => ['web', 'auth', \Lavertia\Panels\Http\Middleware\ResolveLocale::class],
     *         'pages' => [App\Lavertia\Pages\DashboardPage::class],
     *     ],
     * ],
     */
    'panels' => [
        'admin' => [
            'path' => 'admin',
            'middleware' => ['web', 'auth', ResolveLocale::class],
            'pages' => [],
        ],
    ],
];
