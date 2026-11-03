<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use Illuminate\Filesystem\Filesystem;
use Lavertia\Panels\PanelsServiceProvider;
use Orchestra\Testbench\TestCase;

final class InstallUiKitTest extends TestCase
{
    private string $workspace;

    /** @return array<class-string> */
    protected function getPackageProviders($app): array
    {
        return [PanelsServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir().'/lavertia-installer-'.bin2hex(random_bytes(6));
        $files = new Filesystem;
        $files->ensureDirectoryExists($this->workspace.'/resources/css');
        $files->put($this->workspace.'/resources/css/app.css', '@import "tailwindcss";');
        $this->app->setBasePath($this->workspace);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->workspace);

        parent::tearDown();
    }

    public function test_default_install_keeps_existing_legacy_shim(): void
    {
        $this->artisan('lavertia:install', ['--stack' => 'react', '--ui' => 'default'])
            ->assertExitCode(0);

        $page = file_get_contents(resource_path('js/pages/lavertia/page.tsx'));

        self::assertStringContainsString('@lavertia/react-ui', $page);
        self::assertStringNotContainsString('createShadcnKit', $page);
        self::assertFileDoesNotExist(config_path('lavertia.php'));
    }

    public function test_shadcn_install_requires_react_and_valid_dependencies_before_writing_files(): void
    {
        $this->artisan('lavertia:install', ['--stack' => 'vue', '--ui' => 'shadcn'])
            ->assertExitCode(1);

        $this->artisan('lavertia:install', ['--stack' => 'react', '--ui' => 'shadcn'])
            ->assertExitCode(1);

        self::assertFileDoesNotExist(resource_path('js/pages/lavertia/page.tsx'));
        self::assertFileDoesNotExist(config_path('lavertia.php'));
    }

    public function test_shadcn_installs_a_page_registry_and_safely_selects_the_application_kit(): void
    {
        $files = new Filesystem;
        $files->ensureDirectoryExists(base_path('node_modules/@lavertia/ui-shadcn'));
        $files->put(base_path('node_modules/@lavertia/ui-shadcn/package.json'), '{}');

        foreach (['input', 'label', 'textarea', 'checkbox', 'button', 'card'] as $primitive) {
            $path = resource_path('js/components/ui/'.$primitive.'.tsx');
            $files->ensureDirectoryExists(dirname($path));
            $files->put($path, 'export const Stub = true;');
        }

        $this->artisan('lavertia:install', ['--stack' => 'react', '--ui' => 'shadcn'])
            ->assertExitCode(0);

        $page = file_get_contents(resource_path('js/pages/lavertia/page.tsx'));
        $config = file_get_contents(config_path('lavertia.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        self::assertStringContainsString('createShadcnKit', $page);
        self::assertStringContainsString("'kit' => 'shadcn'", $config);
        self::assertStringContainsString('@lavertia/ui-shadcn/dist', $css);
        self::assertStringContainsString('@lavertia/react-ui/dist', $css);
    }

    public function test_custom_config_does_not_get_overwritten(): void
    {
        $files = new Filesystem;
        $files->ensureDirectoryExists(config_path());
        $original = "<?php return ['ui' => ['kit' => 'third-party']];";
        $files->put(config_path('lavertia.php'), $original);
        $files->ensureDirectoryExists(base_path('node_modules/@lavertia/ui-shadcn'));
        $files->put(base_path('node_modules/@lavertia/ui-shadcn/package.json'), '{}');

        foreach (['input', 'label', 'textarea', 'checkbox', 'button', 'card'] as $primitive) {
            $path = resource_path('js/components/ui/'.$primitive.'.tsx');
            $files->ensureDirectoryExists(dirname($path));
            $files->put($path, 'export const Stub = true;');
        }

        $this->artisan('lavertia:install', ['--stack' => 'react', '--ui' => 'shadcn'])
            ->assertExitCode(1);

        self::assertSame($original, file_get_contents(config_path('lavertia.php')));
        self::assertFileDoesNotExist(resource_path('js/pages/lavertia/page.tsx'));
    }
}
