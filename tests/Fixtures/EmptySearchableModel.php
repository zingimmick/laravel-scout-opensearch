<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests\Fixtures;

class EmptySearchableModel extends SearchableModel
{
    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [];
    }
}
