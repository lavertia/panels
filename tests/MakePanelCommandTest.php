<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use Illuminate\Filesystem\Filesystem;
use Lavertia\Panels\PanelsServiceProvider;
use Orchestra\Testbench\TestCase;

final class MakePanelCommandTest extends TestCase
{
    private string $temporaryAppPath;

    private string $previousAppPath;

    protected function getPackageProviders($app): array
    {
        return [PanelsServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousAppPath = app_path();
        $this->temporaryAppPath = sys_get_temp_dir().'/lavertia-panel-provider-'.bin2hex(random_bytes(8));

        (new Filesystem)->ensureDirectoryExists($this->temporaryAppPath);
        $this->app->useAppPath($this->temporaryAppPath);
    }

    protected function tearDown(): void
    {
        $this->app->useAppPath($this->previousAppPath);
        (new Filesystem)->deleteDirectory($this->temporaryAppPath);

        parent::tearDown();
    }

    public function test_generates_a_real_panel_provider(): void
    {
        $this->artisan('lavertia:panel', [
            'name' => 'Operations',
            '--id' => 'operations',
        ])->assertExitCode(0);

        $path = $this->temporaryAppPath.'/Providers/Lavertia/OperationsPanelProvider.php';
        self::assertFileExists($path);

        $content = (string) file_get_contents($path);
        self::assertStringContainsString('class OperationsPanelProvider extends PanelProvider', $content);
        self::assertStringContainsString("->id('operations')", $content);
        self::assertStringContainsString("->path('operations')", $content);
    }

    public function test_prevents_overwriting_existing_provider_without_force(): void
    {
        $this->artisan('lavertia:panel', ['name' => 'Admin'])->assertExitCode(0);
        $path = $this->temporaryAppPath.'/Providers/Lavertia/AdminPanelProvider.php';

        $original = file_get_contents($path);
        $this->artisan('lavertia:panel', ['name' => 'Admin'])->assertExitCode(1);

        self::assertSame($original, file_get_contents($path));

        $this->artisan('lavertia:panel', ['name' => 'Admin', '--force' => true])->assertExitCode(0);
    }

    public function test_rejects_unsafe_provider_identifiers(): void
    {
        $rejected = 0;

        foreach ([
            ['../Bad', 'admin'],
            ['Admin', '../admin'],
            ['Bad/Name', 'admin'],
            ['Admin', 'javascript:bad'],
        ] as [$name, $id]) {
            $this->artisan('lavertia:panel', ['name' => $name, '--id' => $id])->assertExitCode(1);
            $rejected++;
        }

        self::assertSame(4, $rejected);
    }
}
