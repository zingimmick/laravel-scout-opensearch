<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests\Fixtures;

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
