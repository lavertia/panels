<?php

declare(strict_types=1);

namespace Lavertia\Panels\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MakePageCommand extends Command
{
    protected $signature = 'lavertia:page
                            {name : Page class name, optionally prefixed with folders (e.g. Billing/Invoices)}
                            {--panel=admin : Existing Lavertia panel ID}
                            {--force : Overwrite an existing generated Page class}';

    protected $description = 'Create a PHP-first Lavertia Page class for an existing Panel.';

    public function handle(Filesystem $files): int
    {
        $name = trim((string) $this->argument('name'));
        $panel = trim((string) $this->option('panel'));

        if (preg_match('#^[A-Za-z][A-Za-z0-9_]*(?:/[A-Za-z][A-Za-z0-9_]*)*$#D', $name) !== 1) {
            $this->error('Invalid Page name. Use e.g. Invoices or Billing/Invoices (no dots or traversal).');

            return self::FAILURE;
        }

        if (preg_match('/^[a-z][a-z0-9_-]*$/D', $panel) !== 1
            || ! is_array(config('lavertia.panels.'.$panel))) {
            $this->error('Panel ['.$panel.'] is not configured in config/lavertia.php.');

            return self::FAILURE;
        }

        $segments = array_map(static fn (string $segment): string => Str::studly($segment), explode('/', $name));
        $class = array_pop($segments);

        if (! str_ends_with($class, 'Page')) {
            $class .= 'Page';
        }

        $path = 'Lavertia/Pages/'.($segments === [] ? '' : implode('/', $segments).'/').$class.'.php';
        $destination = app_path($path);

        if ($files->exists($destination) && ! $this->option('force')) {
            $this->error('Page already exists: '.$destination.' (use --force to overwrite).');

            return self::FAILURE;
        }

        $source = dirname(__DIR__, 2).'/resources/stubs/php/page.stub';

        if (! $files->isFile($source)) {
            $this->error('Missing Lavertia Page stub.');

            return self::FAILURE;
        }

        $base = substr($class, 0, -4);
        $slugSegments = array_map(static fn (string $segment): string => Str::kebab($segment), [...$segments, $base]);
        $slug = implode('/', $slugSegments);
        $title = Str::headline($base);
        $namespace = 'App\\Lavertia\\Pages'.($segments === [] ? '' : '\\'.implode('\\', $segments));
        $fqcn = $namespace.'\\'.$class;

        // Reject any class with an existing slug to avoid breaking Panel boot.
        foreach (config('lavertia.panels.'.$panel.'.pages', []) as $page) {
            if (is_string($page) && class_exists($page) && is_subclass_of($page, \Lavertia\Panels\Pages\Page::class)
                && $page::slug() === $slug && $page !== $fqcn) {
                $this->error('Panel ['.$panel.'] already has a Page using slug ['.$slug.'].');

                return self::FAILURE;
            }
        }

        $template = $files->get($source);
        $contents = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ slug }}', '{{ title }}'],
            [$namespace, $class, $slug, $title],
            $template,
        );

        $files->ensureDirectoryExists(dirname($destination));

        if ($files->put($destination, $contents) === false) {
            $this->error('Unable to write Page: '.$destination);

            return self::FAILURE;
        }

        $this->info('Created: '.$destination);
        $this->line('To register this page, add '.$fqcn.'::class to');
        $this->line("config/lavertia.php => 'panels' => '".$panel."' => 'pages'.");
        $this->line("Optional navigation item: ['slug' => '".$slug."', 'label' => '".$title."'].");
        $this->comment('Then run: php artisan optimize:clear && php artisan route:list --path='.(string) config('lavertia.panels.'.$panel.'.path', $panel));
        $this->comment('Existing pages and panel configuration are never modified automatically.');

        return self::SUCCESS;
    }
}
