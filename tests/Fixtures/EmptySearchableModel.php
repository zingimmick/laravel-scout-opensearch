<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests\Fixtures;

class EmptySearchableModel extends SearchableModel
{
    public function toSearchableArray(): array
    {
        return [];
    }
}
