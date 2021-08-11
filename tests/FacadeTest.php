<?php

declare(strict_types=1);

namespace Zing\Skeleton\Tests;

use Zing\Skeleton\Facades\Skeleton;
use Zing\Skeleton\SkeletonServiceProvider;

class FacadeTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [SkeletonServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Skeleton' => Skeleton::class,
        ];
    }

    public function testStaticCall(): void
    {
        self::assertTrue(Skeleton::foo());
    }

    public function testAlias(): void
    {
        self::assertSame(forward_static_call([\Skeleton::class, 'foo']), forward_static_call([Skeleton::class, 'foo']));
    }
}
