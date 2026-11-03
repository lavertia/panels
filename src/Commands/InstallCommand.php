<?php

declare(strict_types=1);

namespace Lavertia\Panels\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

final class InstallCommand extends Command
{
    protected $signature = 'lavertia:install
                            {--stack= : react, vue or svelte}
                            {--ui=default : default or shadcn (experimental React-only)}
                            {--force : Overwrite existing Lavertia frontend files}';

    protected $description = 'Install thin Lavertia frontend shims for its framework-owned UI package.';

    public function handle(Filesystem $files): int
    {
        $stack = strtolower(trim((string) $this->option('stack')));

        if (! in_array($stack, ['react', 'vue', 'svelte'], true)) {
            $this->error('Specify --stack=react, --stack=vue or --stack=svelte.');

            return self::FAILURE;
        }

        $ui = strtolower(trim((string) $this->option('ui')));

        if (! in_array($ui, ['default', 'shadcn'], true)) {
            $this->error('Specify --ui=default or --ui=shadcn.');

            return self::FAILURE;
        }

        if ($ui === 'shadcn' && $stack !== 'react') {
            $this->error('The experimental Shadcn UI Kit currently supports --stack=react only.');

            return self::FAILURE;
        }

        if ($ui === 'shadcn') {
            $requirements = [
                base_path('node_modules/@lavertia/ui-shadcn/package.json'),
                resource_path('js/components/ui/input.tsx'),
                resource_path('js/components/ui/label.tsx'),
                resource_path('js/components/ui/card.tsx'),
                resource_path('js/components/ui/textarea.tsx'),
                resource_path('js/components/ui/checkbox.tsx'),
                resource_path('js/components/ui/button.tsx'),
            ];

            foreach ($requirements as $requirement) {
                if (! $files->isFile($requirement)) {
                    $this->error('Shadcn prerequisite missing: '.$requirement);
                    $this->comment('Install the portable @lavertia/ui-shadcn package and run: npx shadcn@latest add card input label textarea checkbox button');

                    return self::FAILURE;
                }
            }
        }

        $extension = match ($stack) {
            'react' => 'tsx',
            'vue' => 'vue',
            'svelte' => 'svelte',
        };

        $root = dirname(__DIR__, 2).'/resources/stubs';
        $pageStub = $root.'/'.$stack.'/'.($ui === 'shadcn' ? 'page-shadcn.tsx' : 'page.'.$extension);

        $assets = [
            $root.'/shared/types.ts' => resource_path('js/components/lavertia/types.ts'),
            $root.'/'.$stack.'/renderer.'.$extension => resource_path('js/components/lavertia/renderer.'.$extension),
            $pageStub => resource_path('js/pages/lavertia/page.'.$extension),
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

        $configPath = config_path('lavertia.php');
        $configContent = null;

        if ($ui === 'shadcn') {
            $configSource = $files->isFile($configPath)
                ? $configPath
                : dirname(__DIR__, 2).'/config/lavertia.php';
            $contents = $files->get($configSource);
            $matched = 0;
            $configContent = preg_replace_callback(
                "/('ui'\\s*=>\\s*\\[\\s*'kit'\\s*=>\\s*)'(default|shadcn)'/",
                static fn (array $match): string => $match[1]."'shadcn'",
                $contents,
                1,
                $matched,
            );

            if (! is_string($configContent) || $matched !== 1) {
                $this->error('Cannot safely configure UI Kit in config/lavertia.php. Set lavertia.ui.kit manually.');

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

        if ($configContent !== null) {
            $files->ensureDirectoryExists(dirname($configPath));
            if ($files->put($configPath, $configContent) === false) {
                $this->error('Unable to update Lavertia UI Kit configuration.');

                return self::FAILURE;
            }
            $this->line('Configured Shadcn UI Kit: '.$configPath);
        }

        // Tailwind CSS 4 ignores node_modules by default. Register only the
        // selected UI package, leaving the host's design system untouched.
        $cssPath = resource_path('css/app.css');
        $sources = [
            '@source "../../node_modules/@lavertia/'.$stack.'-ui/dist";',
        ];

        if ($ui === 'shadcn') {
            $sources[] = '@source "../../node_modules/@lavertia/ui-shadcn/dist";';
        }

        if ($files->isFile($cssPath)) {
            foreach ($sources as $source) {
                if (! str_contains($files->get($cssPath), $source)) {
                    $files->append($cssPath, PHP_EOL.$source.PHP_EOL);
                    $this->line('Registered Tailwind UI source in: '.$cssPath);
                }
            }
        } else {
            $this->warn('Tailwind CSS entry not found. Add these directives to resources/css/app.css:');
            foreach ($sources as $source) {
                $this->warn($source);
            }
        }

        $this->info('Lavertia '.$stack.' frontend shims installed (UI Kit: '.$ui.').');
        $this->comment('Required UI runtime (development): npm install github:lavertia/'.$stack.'-ui#main');
        if ($ui === 'default') {
            $this->comment('Publish config: php artisan vendor:publish --tag=lavertia-config');
        } else {
            $this->comment('Experimental Shadcn Kit selected. Keep @lavertia/ui-shadcn and React UI on compatible development revisions.');
        }
        $this->comment('Register Pages and optional theme class in config/lavertia.php, then run npm run build.');

        return self::SUCCESS;
    }
}
