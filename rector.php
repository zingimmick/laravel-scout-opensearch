<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Privatization\Rector\MethodCall\PrivatizeLocalGetterToPropertyRector;
use Zing\CodingStandard\Set\RectorSetList;

return RectorConfig::configure()
    ->withPhpSets()
    ->withSets([PHPUnitSetList::PHPUNIT_CODE_QUALITY, RectorSetList::CUSTOM])
    ->withPHPStanConfigs([__DIR__ . '/phpstan.neon'])
    ->withBootstrapFiles([__DIR__ . '/vendor/larastan/larastan/bootstrap.php'])
    ->withSkip([
        PrivatizeLocalGetterToPropertyRector::class,
    ])
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/ecs.php', __DIR__ . '/rector.php']);
