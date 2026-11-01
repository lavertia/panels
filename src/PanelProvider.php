<?php

declare(strict_types=1);

namespace Lavertia\Panels;

use Illuminate\Support\ServiceProvider;

/**
 * Define a Lavertia Panel in the host Laravel application's providers.php,
 * just like a Filament PanelProvider. The existing Starter Kit remains the UI.
 */
abstract class PanelProvider extends ServiceProvider
{
    abstract public function panel(Panel $panel): Panel;

    final public function boot(): void
    {
        $panel = $this->panel(Panel::make());

        $this->app->make(PanelRegistry::class)->registerPanel($panel);
    }
}
