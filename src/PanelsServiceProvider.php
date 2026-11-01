<?php

declare(strict_types=1);

namespace Lavertia\Panels;

use Illuminate\Support\ServiceProvider;
use Lavertia\Panels\Commands\InstallCommand;
use Lavertia\Panels\Commands\MakePageCommand;
use Lavertia\Panels\Commands\MakePanelCommand;

final class PanelsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PanelRegistry::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, MakePageCommand::class, MakePanelCommand::class]);
        }

        // Mount host pages before broad app routes such as /{current_team}/dashboard.
        // Module providers that boot later add their own Pages immediately through
        // PanelRegistry; module providers that boot earlier are already collected.
        $this->app->make(PanelRegistry::class)->mountRegisteredPanels();
    }
}
