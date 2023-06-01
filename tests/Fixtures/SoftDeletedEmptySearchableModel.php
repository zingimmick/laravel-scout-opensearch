<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests\Fixtures;

class SoftDeletedEmptySearchableModel extends SearchableModel
{
    public function toSearchableArray(): array
    {
        return [];
    }

    public function pushSoftDeleteMetadata(): void
    {
    }

    /**
     * @return array{__soft_deleted: int}
     */
    public function scoutMetadata(): array
    {
        return [
            '__soft_deleted' => 1,
        ];
    }
}
