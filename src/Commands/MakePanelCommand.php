<?php

declare(strict_types=1);

namespace Lavertia\Panels\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MakePanelCommand extends Command
{
    protected $signature = 'lavertia:panel
                            {name=Admin : Provider name without the PanelProvider suffix}
                            {--id=admin : Unique Panel identifier}
                            {--force : Overwrite the generated provider}';

    protected $description = 'Generate a configurable Lavertia PanelProvider using the existing Starter Kit shell.';

    public function handle(Filesystem $files): int
    {
        $name = trim((string) $this->argument('name'));
        $id = trim((string) $this->option('id'));

        if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $name) !== 1
            || preg_match('/^[a-z][a-z0-9_-]*$/D', $id) !== 1) {
            $this->error('Expected a valid provider class name and lowercase Panel ID.');

            return self::FAILURE;
        }

        $class = Str::studly($name);

        if (! str_ends_with($class, 'PanelProvider')) {
            $class .= 'PanelProvider';
        }

        $path = app_path('Providers/Lavertia/'.$class.'.php');

        if ($files->exists($path) && ! $this->option('force')) {
            $this->error('Panel Provider already exists: '.$path);

            return self::FAILURE;
        }

        $source = dirname(__DIR__, 2).'/resources/stubs/php/panel-provider.stub';

        if (! $files->isFile($source)) {
            $this->error('Lavertia Panel Provider stub is missing.');

            return self::FAILURE;
        }

        $content = str_replace(
            ['{{ class }}', '{{ id }}'],
            [$class, $id],
            $files->get($source),
        );

        $files->ensureDirectoryExists(dirname($path));

        if ($files->put($path, $content) === false) {
            $this->error('Failed to write Panel Provider.');

            return self::FAILURE;
        }

        $this->info('Created: '.$path);
        $this->line('Register App\\Providers\\Lavertia\\'.$class.'::class in bootstrap/providers.php.');
        $this->comment('Use the existing Laravel Starter Kit layout and its auth/session system.');
        $this->comment('All Page, navigation, theme and plugin settings belong inside this PanelProvider.');

        return self::SUCCESS;
    }
}
