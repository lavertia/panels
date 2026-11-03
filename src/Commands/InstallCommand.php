<?php

declare(strict_types=1);

namespace Lavertia\Panels\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

final class InstallCommand extends Command
{
    protected $signature = 'lavertia:install
                            {--stack= : react, vue or svelte}
                            {--force : Overwrite existing Lavertia frontend files}';

    protected $description = 'Install thin Lavertia frontend shims for its framework-owned UI package.';

    public function handle(Filesystem $files): int
    {
        $stack = strtolower(trim((string) $this->option('stack')));

        if (! in_array($stack, ['react', 'vue', 'svelte'], true)) {
            $this->error('Specify --stack=react, --stack=vue or --stack=svelte.');

            return self::FAILURE;
        }

        $extension = match ($stack) {
            'react' => 'tsx',
            'vue' => 'vue',
            'svelte' => 'svelte',
        };

        $root = dirname(__DIR__, 2).'/resources/stubs';
        $assets = [
            $root.'/shared/types.ts' => resource_path('js/components/lavertia/types.ts'),
            $root.'/'.$stack.'/renderer.'.$extension => resource_path('js/components/lavertia/renderer.'.$extension),
            $root.'/'.$stack.'/page.'.$extension => resource_path('js/pages/lavertia/page.'.$extension),
        ];

        foreach ($assets as $source => $destination) {
            if (! $files->isFile($source)) {
                $this->error('Missing Lavertia source asset: '.$source);

                return self::FAILURE;
            }

            if ($files->exists($destination) && ! $this->option('force')) {
                $this->error('Destination already exists: '.$destination.' (use --force to overwrite).');

                return self::FAILURE;
            }
        }

        foreach ($assets as $source => $destination) {
            $files->ensureDirectoryExists(dirname($destination));

            if (! $files->copy($source, $destination)) {
                $this->error('Unable to copy renderer asset to: '.$destination);

                return self::FAILURE;
            }

            $this->line('Installed: '.$destination);
        }

        // Tailwind CSS 4 ignores node_modules by default. Register only the
        // selected UI package, leaving the host's design system untouched.
        $cssPath = resource_path('css/app.css');
        $source = '@source "../../node_modules/@lavertia/'.$stack.'-ui/dist";';

        if ($files->isFile($cssPath)) {
            if (! str_contains($files->get($cssPath), $source)) {
                $files->append($cssPath, PHP_EOL.$source.PHP_EOL);
                $this->line('Registered Tailwind UI source in: '.$cssPath);
            }
        } else {
            $this->warn('Tailwind CSS entry not found. Add '.$source.' to resources/css/app.css.');
        }

        $this->info('Lavertia '.$stack.' frontend shims installed.');
        $this->comment('Required UI runtime (development): npm install github:lavertia/'.$stack.'-ui#main');
        $this->comment('Publish config: php artisan vendor:publish --tag=lavertia-config');
        $this->comment('Register Pages and optional theme class in config/lavertia.php, then run npm run build.');

        return self::SUCCESS;
    }
}
