<?php

declare(strict_types=1);

namespace Lavertia\Panels;

use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Lavertia\Panels\Commands\InstallCommand;
use Lavertia\Panels\Http\Middleware\ResolveLocale;

final class PanelsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lavertia.php', 'lavertia');
        $this->app->singleton(PanelRegistry::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/lavertia.php' => config_path('lavertia.php'),
        ], 'lavertia-config');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);
        }

        // Defer route registration until module service providers have booted.
        // This works with Composer-discovered providers (e.g. InterNACHI/modular)
        // regardless of which provider boots first.
        $this->app->booted(function (): void {
            if ($this->app->routesAreCached()) {
                return;
            }

            $registry = $this->app->make(PanelRegistry::class);

            foreach (config('lavertia.panels', []) as $id => $options) {
                if (! is_string($id) || ! is_array($options)) {
                    continue;
                }

                $panel = Panel::make($id)
                    ->path((string) ($options['path'] ?? $id))
                    ->middleware($options['middleware'] ?? ['web', 'auth', ResolveLocale::class])
                    ->pages($options['pages'] ?? []);

                foreach ($options['discover_pages'] ?? [] as $discovery) {
                    if (! is_array($discovery)
                        || ! is_string($discovery['in'] ?? null)
                        || ! is_string($discovery['for'] ?? null)) {
                        throw new InvalidArgumentException('Panel discover_pages entries require in and for strings.');
                    }

                    $panel = $panel->discoverPages(in: $discovery['in'], for: $discovery['for']);
                }

                foreach ($registry->pageDirectories($id) as $discovery) {
                    $panel = $panel->discoverPages(in: $discovery['in'], for: $discovery['for']);
                }

                $panel->registerRoutes();
            }
        });
    }
}
