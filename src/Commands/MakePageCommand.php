<?php

declare(strict_types=1);

namespace Lavertia\Panels\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Lavertia\Panels\Pages\Page;
use Lavertia\Panels\PanelRegistry;

final class MakePageCommand extends Command
{
    protected $signature = 'lavertia:page
                            {name : Page class name, optionally prefixed with folders (e.g. Billing/Invoices)}
                            {--panel=admin : Existing Lavertia panel ID}
                            {--presentation=default : default, card, flat or split}
                            {--standalone : Generate an independent Page for regular Laravel routes}
                            {--force : Overwrite an existing generated Page class}';

    protected $description = 'Create a PHP-first Lavertia Page class for an existing Panel.';

    public function handle(Filesystem $files): int
    {
        $name = trim((string) $this->argument('name'));
        $panel = trim((string) $this->option('panel'));
        $presentation = trim((string) $this->option('presentation'));
        $standalone = (bool) $this->option('standalone');

        if (! in_array($presentation, ['default', 'card', 'flat', 'split'], true)) {
            $this->error('Invalid presentation. Choose default, card, flat or split.');

            return self::FAILURE;
        }

        if (preg_match('#^[A-Za-z][A-Za-z0-9_]*(?:/[A-Za-z][A-Za-z0-9_]*)*$#D', $name) !== 1) {
            $this->error('Invalid Page name. Use e.g. Invoices or Billing/Invoices (no dots or traversal).');

            return self::FAILURE;
        }

        if (! $standalone && (preg_match('/^[a-z][a-z0-9_-]*$/D', $panel) !== 1
            || ! app(PanelRegistry::class)->hasPanel($panel))) {
            $this->error('Panel ['.$panel.'] has no registered PanelProvider.');

            return self::FAILURE;
        }

        $segments = array_map(static fn (string $segment): string => Str::studly($segment), explode('/', $name));
        $class = array_pop($segments);

        if (! str_ends_with($class, 'Page')) {
            $class .= 'Page';
        }

        $path = ($standalone ? 'Lavertia/PublicPages/' : 'Lavertia/Pages/')
            .($segments === [] ? '' : implode('/', $segments).'/').$class.'.php';
        $destination = app_path($path);

        if ($files->exists($destination) && ! $this->option('force')) {
            $this->error('Page already exists: '.$destination.' (use --force to overwrite).');

            return self::FAILURE;
        }

        $stub = $presentation === 'default' ? 'page.stub' : 'page-'.$presentation.'.stub';
        $source = dirname(__DIR__, 2).'/resources/stubs/php/'.$stub;

        if (! $files->isFile($source)) {
            $this->error('Missing Lavertia Page stub.');

            return self::FAILURE;
        }

        $base = substr($class, 0, -4);
        $slugSegments = array_map(static fn (string $segment): string => Str::kebab($segment), [...$segments, $base]);
        $slug = implode('/', $slugSegments);
        $title = Str::headline($base);
        $namespace = ($standalone ? 'App\\Lavertia\\PublicPages' : 'App\\Lavertia\\Pages')
            .($segments === [] ? '' : '\\'.implode('\\', $segments));
        $fqcn = $namespace.'\\'.$class;

        // Reject any class with an existing slug to avoid breaking Panel boot.
        foreach ($standalone ? [] : (app(PanelRegistry::class)->panel($panel)?->pageClasses() ?? []) as $page) {
            if (is_string($page) && class_exists($page) && is_subclass_of($page, Page::class)
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
        if ($standalone) {
            $this->line('Register an explicit Laravel GET route for '.$fqcn.'::class in routes/web.php.');
            $this->comment('Add guest/auth middleware as appropriate; secure POST actions remain in host controllers.');
        } else {
            $this->line('Register '.$fqcn.'::class in the '.$panel.' PanelProvider pages([...]),');
            $this->line('or configure discoverPages(app_path(\'Lavertia/Pages\'), \'App\\\\Lavertia\\\\Pages\').');
            $this->line("Optional navigation item: ['slug' => '".$slug."', 'label' => '".$title."'].");
            $this->comment('Then run: php artisan optimize:clear && php artisan route:list --path='.(string) (app(PanelRegistry::class)->panel($panel)?->configuration()['path'] ?? $panel));
        }
        $this->comment('Existing pages and panel configuration are never modified automatically.');

        return self::SUCCESS;
    }
}
