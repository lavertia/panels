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
     *         'theme' => \Lavertia\ThemeDefault\ClassicTheme::class, // optional Composer package
     *         'navigation' => [['slug' => 'dashboard', 'label' => 'Dashboard']],
     *         'dashboard_widgets' => [],
     *         'discover_widgets' => [],
     *         'discover_pages' => [
     *             ['in' => app_path('Lavertia/Pages'), 'for' => 'App\\Lavertia\\Pages'],
     *         ],
     *     ],
     * ],
     */
    'panels' => [
        'admin' => [
            'path' => 'admin',
            'middleware' => ['web', 'auth', ResolveLocale::class],
            'pages' => [],
            'theme' => null,
            'discover_pages' => [],
            'navigation' => [],
            'dashboard_widgets' => [],
            'discover_widgets' => [],
        ],
    ],
];
