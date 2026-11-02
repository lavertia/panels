<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use Illuminate\Filesystem\Filesystem;
use Lavertia\Panels\PanelsServiceProvider;
use Orchestra\Testbench\TestCase;

final class CleanReactInstallCommandTest extends TestCase
{
    private string $root;

    private string $originalBasePath;

    protected function getPackageProviders($app): array
    {
        return [PanelsServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalBasePath = base_path();
        $this->root = sys_get_temp_dir().'/lavertia-clean-install-'.bin2hex(random_bytes(8));

        $files = new Filesystem;
        $files->ensureDirectoryExists($this->root.'/bootstrap');
        $files->ensureDirectoryExists($this->root.'/resources/js');
        $files->ensureDirectoryExists($this->root.'/resources/views');
        $files->put($this->root.'/bootstrap/providers.php', "<?php\n\nreturn [\n    App\\Providers\\AppServiceProvider::class,\n];\n");
        $files->put($this->root.'/resources/js/app.tsx', "import '@inertiajs/react';\n");
        $files->put($this->root.'/resources/views/app.blade.php', '<x-inertia::app />');
        $files->put($this->root.'/vite.config.ts', 'export default {};');
        $files->put(
            $this->root.'/package.json',
            '{"dependencies":{"react":"^19.0.0","@inertiajs/react":"^3.0.0"}}',
        );

        $this->app->setBasePath($this->root);
        $this->app->useAppPath($this->root.'/app');
    }

    protected function tearDown(): void
    {
        $this->app->setBasePath($this->originalBasePath);
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_installs_react_without_a_starter_kit_and_registers_a_secure_panel(): void
    {
        $this->artisan('lavertia:install', ['--stack' => 'react'])->assertExitCode(0);

        $this->assertFileExists($this->root.'/resources/js/pages/lavertia/page.tsx');
        $this->assertFileExists($this->root.'/resources/js/pages/lavertia/standalone.tsx');
        $this->assertFileExists($this->root.'/resources/js/components/lavertia/panel-shell.tsx');
        $this->assertFileExists($this->root.'/resources/js/components/lavertia/form-state.ts');
        $this->assertFileExists($this->root.'/app/Lavertia/Pages/DashboardPage.php');

        $provider = (string) file_get_contents($this->root.'/app/Providers/Lavertia/AdminPanelProvider.php');
        self::assertStringContainsString("->id('admin')", $provider);
        self::assertStringContainsString("NavigationItem::make('dashboard', 'Dashboard')", $provider);

        $dashboard = (string) file_get_contents($this->root.'/app/Lavertia/Pages/DashboardPage.php');
        self::assertStringContainsString('StatsGrid::make([', $dashboard);
        self::assertStringContainsString("Table::make('examples')", $dashboard);
        self::assertStringContainsString('Sample, browser-local records', $dashboard);
        self::assertStringNotContainsString('LavertiaLocalPreview', $provider);

        $bootstrap = (string) file_get_contents($this->root.'/bootstrap/providers.php');
        self::assertSame(1, substr_count($bootstrap, 'App\Providers\Lavertia\AdminPanelProvider::class'));

        // No repo-owned welcome page, Vite setup, routes or auth files are replaced.
        self::assertFileDoesNotExist($this->root.'/routes/web.php');
        self::assertSame("import '@inertiajs/react';\n", file_get_contents($this->root.'/resources/js/app.tsx'));
        $this->artisan('lavertia:install', ['--stack' => 'react'])->assertExitCode(0);
    }

    public function test_demo_mode_uses_local_only_middleware_and_preserves_existing_work(): void
    {
        $this->artisan('lavertia:install', [
            '--stack' => 'react',
            '--demo' => true,
        ])->assertExitCode(0);

        $providerPath = $this->root.'/app/Providers/Lavertia/AdminPanelProvider.php';
        $provider = (string) file_get_contents($providerPath);
        self::assertStringContainsString("->middleware(['web', \\App\\Http\\Middleware\\LavertiaLocalPreview::class])", $provider);

        $middleware = (string) file_get_contents($this->root.'/app/Http/Middleware/LavertiaLocalPreview.php');
        self::assertStringContainsString("environment('local')", $middleware);
        self::assertStringContainsString('404', $middleware);

        $this->artisan('lavertia:install', [
            '--stack' => 'react',
            '--demo' => true,
        ])->assertExitCode(0);

        file_put_contents($providerPath, '// Custom provider');
        $this->artisan('lavertia:install', [
            '--stack' => 'react',
            '--demo' => true,
        ])->assertExitCode(1);
        self::assertSame('// Custom provider', file_get_contents($providerPath));

        $this->artisan('lavertia:install', [
            '--stack' => 'react',
            '--demo' => true,
            '--force' => true,
        ])->assertExitCode(0);
        self::assertStringContainsString('extends PanelProvider', (string) file_get_contents($providerPath));
        self::assertStringContainsString('NavigationItem::make(', (string) file_get_contents($providerPath));
    }

    public function test_missing_host_or_invalid_panel_id_fails_without_writing_files(): void
    {
        (new Filesystem)->delete($this->root.'/resources/js/app.tsx');
        $this->artisan('lavertia:install', ['--stack' => 'react'])->assertExitCode(1);

        self::assertFileDoesNotExist($this->root.'/app/Providers/Lavertia/AdminPanelProvider.php');

        $this->artisan('lavertia:install', [
            '--stack' => 'vue',
            '--panel' => '../bad',
        ])->assertExitCode(1);
        self::assertFileDoesNotExist($this->root.'/resources/js/pages/lavertia/page.vue');
    }

    public function test_malformed_bootstrap_providers_is_rejected_without_partial_install(): void
    {
        (new Filesystem)->put($this->root.'/bootstrap/providers.php', "<?php\nreturn [];\n");

        $this->artisan('lavertia:install', ['--stack' => 'react'])->assertExitCode(1);

        self::assertFileDoesNotExist($this->root.'/app/Providers/Lavertia/AdminPanelProvider.php');
        self::assertFileDoesNotExist($this->root.'/resources/js/pages/lavertia/page.tsx');
    }
}
