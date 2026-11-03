<?php

declare(strict_types=1);

namespace Lavertia\Panels\Tests;

use PHPUnit\Framework\TestCase;

final class StarterKitAssetsTest extends TestCase
{
    public function test_three_inertia_stacks_ship_renderers_and_pages(): void
    {
        foreach (['react' => 'tsx', 'vue' => 'vue', 'svelte' => 'svelte'] as $stack => $extension) {
            self::assertFileExists(__DIR__.'/../resources/stubs/'.$stack.'/renderer.'.$extension);
            self::assertFileExists(__DIR__.'/../resources/stubs/'.$stack.'/page.'.$extension);
        }

        self::assertFileExists(__DIR__.'/../resources/stubs/react/page-shadcn.tsx');
        self::assertFileExists(__DIR__.'/../resources/stubs/shared/types.ts');
        self::assertStringContainsString(
            '@lavertia/ui',
            file_get_contents(__DIR__.'/../resources/stubs/shared/types.ts'),
        );

        // Composer only provides tiny wiring; framework packages own all UI.
        foreach (['react' => 'tsx', 'vue' => 'vue', 'svelte' => 'svelte'] as $stack => $extension) {
            $source = file_get_contents(__DIR__.'/../resources/stubs/'.$stack.'/renderer.'.$extension);
            self::assertStringContainsString('@lavertia/'.$stack.'-ui', $source);
            self::assertLessThan(1500, strlen($source));
        }
    }
}
