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

        // Mount host pages before broad app routes such as /{current_team}/dashboard.
        // Module providers that boot later add their own Pages immediately through
        // PanelRegistry; module providers that boot earlier are already collected.
        $this->app->make(PanelRegistry::class)->mountConfiguredPanels();
    }
}
