<?php

declare(strict_types=1);

namespace Zing\Skeleton\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool foo()
 * @mixin \Zing\Skeleton\SkeletonManager
 */
class Skeleton extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'skeleton';
    }
}
