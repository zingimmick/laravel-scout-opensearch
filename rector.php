<?php

declare(strict_types=1);

use Rector\Core\Configuration\Option;
use Rector\Naming\Rector\Assign\RenameVariableToMatchMethodCallReturnTypeRector;
use Rector\Naming\Rector\ClassMethod\RenameParamToMatchTypeRector;
use Rector\PHPUnit\Rector\Class_\AddSeeTestAnnotationRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Privatization\Rector\Class_\FinalizeClassesWithoutChildrenRector;
use Rector\Privatization\Rector\Class_\RepeatedLiteralToClassConstantRector;
use Rector\Privatization\Rector\MethodCall\PrivatizeLocalGetterToPropertyRector;
use Rector\Set\ValueObject\LevelSetList;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Zing\CodingStandard\Set\RectorSetList;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->import(RectorSetList::CUSTOM);
    $containerConfigurator->import(PHPUnitSetList::PHPUNIT_CODE_QUALITY);
    $containerConfigurator->import(LevelSetList::UP_TO_PHP_72);

    $parameters = $containerConfigurator->parameters();
    $parameters->set(
        Option::SKIP,
        [
            RenameVariableToMatchMethodCallReturnTypeRector::class,
            RenameParamToMatchTypeRector::class,
            AddSeeTestAnnotationRector::class,
            FinalizeClassesWithoutChildrenRector::class,
            RepeatedLiteralToClassConstantRector::class,
            PrivatizeLocalGetterToPropertyRector::class,
        ]
    );
    $parameters->set(
        Option::PATHS,
        [__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/ecs.php', __DIR__ . '/rector.php']
    );
};
