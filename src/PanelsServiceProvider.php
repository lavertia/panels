<?php

namespace Lavertia\Panels;

use Lavertia\Panels\Commands\PanelsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PanelsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('panels')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_panels_table')
            ->hasCommand(PanelsCommand::class);
    }
}
