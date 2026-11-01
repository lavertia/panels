<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use Illuminate\Filesystem\Filesystem;
use Lavertia\Panels\Pages\Page;
use Lavertia\Panels\PanelsServiceProvider;
use Orchestra\Testbench\TestCase;

final class MakePageCommandTest extends TestCase
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
        $this->temporaryAppPath = sys_get_temp_dir().'/lavertia-page-command-'.bin2hex(random_bytes(8));

        $files = new Filesystem;
        $files->ensureDirectoryExists($this->temporaryAppPath);
        $this->app->useAppPath($this->temporaryAppPath);

        config()->set('lavertia.panels.admin', [
            'path' => 'admin',
            'middleware' => ['web', 'auth'],
            'pages' => [],
            'navigation' => [],
        ]);
    }

    protected function tearDown(): void
    {
        $this->app->useAppPath($this->previousAppPath);
        (new Filesystem)->deleteDirectory($this->temporaryAppPath);

        parent::tearDown();
    }

    public function test_generates_page_with_namespace_title_and_safe_slug(): void
    {
        $this->artisan('lavertia:page', [
            'name' => 'Billing/Invoices',
            '--panel' => 'admin',
        ])->assertExitCode(0);

        $path = $this->temporaryAppPath.'/Lavertia/Pages/Billing/InvoicesPage.php';

        self::assertFileExists($path);

        $content = file_get_contents($path);

        self::assertIsString($content);
        self::assertStringContainsString('namespace App\\Lavertia\\Pages\\Billing;', $content);
        self::assertStringContainsString('final class InvoicesPage extends Page', $content);
        self::assertStringContainsString("return 'billing/invoices';", $content);
        self::assertStringContainsString("return 'Invoices';", $content);
        self::assertStringContainsString('PageHeader::make', $content);
    }

    public function test_rejects_overwriting_an_existing_page_unless_force_is_explicit(): void
    {
        $this->artisan('lavertia:page', ['name' => 'Reports'])->assertExitCode(0);
        $file = $this->temporaryAppPath.'/Lavertia/Pages/ReportsPage.php';
        $original = file_get_contents($file);

        $this->artisan('lavertia:page', ['name' => 'Reports'])->assertExitCode(1);
        self::assertSame($original, file_get_contents($file));

        file_put_contents($file, '// overwritten only with --force');
        $this->artisan('lavertia:page', [
            'name' => 'Reports',
            '--force' => true,
        ])->assertExitCode(0);

        self::assertStringContainsString('class ReportsPage extends Page', (string) file_get_contents($file));
    }

    public function test_rejects_directory_traversal_and_unsafe_names(): void
    {
        foreach (['../Secrets', 'Billing/../../Bad', 'Bad\\Name', 'Bad.Name', '/Absolute', 'Bad Name'] as $name) {
            $this->artisan('lavertia:page', ['name' => $name])->assertExitCode(1);
        }

        self::assertDirectoryDoesNotExist($this->temporaryAppPath.'/Lavertia/Pages');
    }

    public function test_rejects_unknown_panels_without_writing_files(): void
    {
        $this->artisan('lavertia:page', [
            'name' => 'Invoices',
            '--panel' => 'missing',
        ])->assertExitCode(1);

        self::assertFileDoesNotExist($this->temporaryAppPath.'/Lavertia/Pages/InvoicesPage.php');
    }

    public function test_rejects_duplicate_slugs_before_writing(): void
    {
        config()->set('lavertia.panels.admin.pages', [ExistingInvoicesPage::class]);

        $this->artisan('lavertia:page', ['name' => 'Invoices'])->assertExitCode(1);

        self::assertFileDoesNotExist($this->temporaryAppPath.'/Lavertia/Pages/InvoicesPage.php');
    }
}

final class ExistingInvoicesPage extends Page
{
    public static function slug(): string
    {
        return 'invoices';
    }

    protected function components(): array
    {
        return [];
    }
}
