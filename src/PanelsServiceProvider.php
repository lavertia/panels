<?php

declare(strict_types=1);

namespace Lavertia\Panels;

use Illuminate\Support\ServiceProvider;
use Lavertia\Panels\Commands\InstallCommand;

final class PanelsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lavertia.php', 'lavertia');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/lavertia.php' => config_path('lavertia.php'),
        ], 'lavertia-config');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);
        }

        if ($this->app->routesAreCached()) {
            return;
        }

        foreach (config('lavertia.panels', []) as $id => $options) {
            if (! is_string($id) || ! is_array($options)) {
                continue;
            }

            Panel::make($id)
                ->path((string) ($options['path'] ?? $id))
                ->middleware($options['middleware'] ?? ['web', 'auth'])
                ->pages($options['pages'] ?? [])
                ->registerRoutes();
        }
    }
}
