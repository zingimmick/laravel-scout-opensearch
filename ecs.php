<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\FunctionNotation\StaticLambdaFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Zing\CodingStandard\Set\ECSSetList;

return ECSConfig::configure()
    ->withSets([ECSSetList::PHP_82, ECSSetList::CUSTOM])
    ->withSkip([
        StaticLambdaFixer::class => [__DIR__ . '/src/OpenSearchServiceProvider.php'],
    ])
    ->withParallel()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/ecs.php', __DIR__ . '/rector.php']);
