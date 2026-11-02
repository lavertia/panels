<?php

declare(strict_types=1);

namespace Lavertia\Panels\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class InstallCommand extends Command
{
    protected $signature = 'lavertia:install
                            {--stack=react : Frontend adapter: react, vue or svelte}
                            {--panel=admin : Panel identifier (and URL prefix)}
                            {--demo : Create a locally accessible dashboard, blocked outside local environment}
                            {--force : Replace previously generated Lavertia files}';

    protected $description = 'Install Lavertia in a Laravel Inertia application, with or without a Starter Kit.';

    public function handle(Filesystem $files): int
    {
        $stack = strtolower(trim((string) $this->option('stack')));
        $panelId = trim((string) $this->option('panel'));
        $demo = (bool) $this->option('demo');
        $force = (bool) $this->option('force');

        if (! in_array($stack, ['react', 'vue', 'svelte'], true)) {
            $this->error('Use --stack=react, --stack=vue or --stack=svelte.');

            return self::FAILURE;
        }

        if (preg_match('/^[a-z][a-z0-9_-]*$/D', $panelId) !== 1) {
            $this->error('The panel ID must be lowercase letters, numbers, underscores or hyphens.');

            return self::FAILURE;
        }

        $bootstrap = base_path('bootstrap/providers.php');

        if (! $files->isFile($bootstrap)) {
            $this->error('Missing bootstrap/providers.php; this installer requires Laravel 12 or 13.');

            return self::FAILURE;
        }

        if ($stack === 'react' && ! $this->hasReactInertiaHost($files)) {
            $this->error('React + Inertia are not configured in this Laravel project.');
            $this->line('Create the project using "laravel new" with React and no Starter Kit,');
            $this->line('or configure the Inertia React/Vite entry points before running this installer.');

            return self::FAILURE;
        }

        $extension = match ($stack) {
            'react' => 'tsx',
            'vue' => 'vue',
            'svelte' => 'svelte',
        };

        $root = dirname(__DIR__, 2).'/resources/stubs';
        $assets = [
            [$root.'/shared/types.ts', resource_path('js/components/lavertia/types.ts')],
            [$root.'/shared/form-state.ts', resource_path('js/components/lavertia/form-state.ts')],
            [$root.'/shared/client-actions.ts', resource_path('js/components/lavertia/client-actions.ts')],
            [$root.'/'.$stack.'/renderer.'.$extension, resource_path('js/components/lavertia/renderer.'.$extension)],
            [$root.'/'.$stack.'/page.'.$extension, resource_path('js/pages/lavertia/page.'.$extension)],
            // Standalone Pages are rendered by Page::__invoke() using this name.
            [$root.'/'.$stack.'/page.'.$extension, resource_path('js/pages/lavertia/standalone.'.$extension)],
        ];

        if ($stack === 'react') {
            $assets[] = [
                $root.'/react/panel-shell.tsx',
                resource_path('js/components/lavertia/panel-shell.tsx'),
            ];
        }

        $planned = [];

        foreach ($assets as [$source, $destination]) {
            if (! $files->isFile($source)) {
                $this->error('Missing Lavertia package asset: '.$source);

                return self::FAILURE;
            }

            $planned[$destination] = $files->get($source);
        }

        $class = Str::studly($panelId).'PanelProvider';
        $providerClass = 'App\\Providers\\Lavertia\\'.$class;
        $providerPath = app_path('Providers/Lavertia/'.$class.'.php');
        $providerStub = $root.'/php/panel-provider.stub';
        $dashboardStub = $root.'/php/dashboard-page.stub';
        $localMiddlewareStub = $root.'/php/local-preview-middleware.stub';

        foreach ([$providerStub, $dashboardStub] as $stub) {
            if (! $files->isFile($stub)) {
                $this->error('Missing Lavertia package asset: '.$stub);

                return self::FAILURE;
            }
        }

        if ($demo && ! $files->isFile($localMiddlewareStub)) {
            $this->error('Missing Lavertia package asset: '.$localMiddlewareStub);

            return self::FAILURE;
        }

        $middleware = $demo
            ? "            ->middleware(['web', \\App\\Http\\Middleware\\LavertiaLocalPreview::class])\n"
            : '';

        $panelContent = str_replace(
            ['{{ class }}', '{{ id }}', '{{ middleware }}'],
            [$class, $panelId, $middleware],
            $files->get($providerStub),
        );

        // The clean installation ships an actual, allowlisted Dashboard link.
        // Generic Panel generators intentionally do not assume Dashboard exists.
        $planned[$providerPath] = str_replace(
            '            ->discoverPages(',
            "            ->navigationItems([\\Lavertia\\Panels\\Navigation\\NavigationItem::make('dashboard', 'Dashboard')->icon('layout-dashboard')])\n"
                .'            ->discoverPages(',
            $panelContent,
        );

        $dashboardPath = app_path('Lavertia/Pages/DashboardPage.php');
        $planned[$dashboardPath] = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ slug }}', '{{ title }}', '{{ stack }}'],
            ['App\\Lavertia\\Pages', 'DashboardPage', 'dashboard', 'Dashboard', ucfirst($stack)],
            $files->get($dashboardStub),
        );

        if ($demo) {
            $planned[app_path('Http/Middleware/LavertiaLocalPreview.php')] = $files->get($localMiddlewareStub);
        }

        $providerContents = $files->get($bootstrap);
        $registration = $providerClass.'::class';

        if (! str_contains($providerContents, $registration)) {
            $updated = preg_replace(
                '/\R\];[ \t]*\R?\z/',
                "    {$registration},\n];\n",
                $providerContents,
                1,
            );

            if ($updated === null || $updated === $providerContents) {
                $this->error('Cannot safely register the PanelProvider in bootstrap/providers.php.');

                return self::FAILURE;
            }

            $planned[$bootstrap] = $updated;
        }

        // Validate all destinations before changing any file. Re-running the
        // command is harmless; differing files require an explicit --force.
        foreach ($planned as $destination => $content) {
            if ($files->isFile($destination) && $files->get($destination) !== $content && ! $force
                && $destination !== $bootstrap) {
                $this->error('Existing file differs: '.$destination.' (use --force to overwrite).');

                return self::FAILURE;
            }
        }

        foreach ($planned as $destination => $content) {
            if ($files->isFile($destination) && $files->get($destination) === $content) {
                continue;
            }

            $files->ensureDirectoryExists(dirname($destination));

            if ($files->put($destination, $content) === false) {
                $this->error('Unable to write: '.$destination);

                return self::FAILURE;
            }

            $this->line('Installed: '.$destination);
        }

        $this->newLine();
        $this->info('Lavertia '.$stack.' installed. Panel: /'.$panelId.'/dashboard');
        $this->line('Next: php artisan config:clear && php artisan route:clear && php artisan view:clear && npm run build');

        if ($demo) {
            $this->warn('Demo route is accessible ONLY in APP_ENV=local. Never remove its environment guard.');
        } else {
            $this->comment('Admin uses Laravel auth middleware. Configure a login route before accessing it.');
        }

        return self::SUCCESS;
    }

    private function hasReactInertiaHost(Filesystem $files): bool
    {
        $packagePath = base_path('package.json');

        if (! $files->isFile($packagePath)
            || ! $files->isFile(resource_path('js/app.tsx'))
            || ! $files->isFile(resource_path('views/app.blade.php'))
            || ! $files->isFile(base_path('vite.config.ts'))) {
            return false;
        }

        $package = json_decode($files->get($packagePath), true);

        if (! is_array($package)) {
            return false;
        }

        $dependencies = [...($package['dependencies'] ?? []), ...($package['devDependencies'] ?? [])];

        return isset($dependencies['react'], $dependencies['@inertiajs/react']);
    }
}
