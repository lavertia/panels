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

        self::assertFileExists(__DIR__.'/../resources/stubs/shared/types.ts');
        self::assertFileExists(__DIR__.'/../resources/stubs/shared/form-state.ts');
    }
}
