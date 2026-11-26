<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests\Fixtures;

class IndexableSearchableModel extends SearchableModel
{
    public function searchableAs(): string
    {
        return 'read-table';
    }

    public function indexableAs(): string
    {
        return 'write-table';
    }
}
