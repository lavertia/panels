<?php

declare(strict_types=1);

return [
    /*
     * Register pages explicitly. Your Starter Kit still owns auth and layouts.
     * Example:
     * 'panels' => [
     *     'admin' => [
     *         'path' => 'admin',
     *         'middleware' => ['web', 'auth'],
     *         'pages' => [App\Lavertia\Pages\DashboardPage::class],
     *     ],
     * ],
     */
    'panels' => [
        'admin' => [
            'path' => 'admin',
            'middleware' => ['web', 'auth'],
            'pages' => [],
        ],
    ],
];
