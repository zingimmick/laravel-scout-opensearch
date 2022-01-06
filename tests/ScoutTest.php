<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests;

/**
 * @internal
 */
final class ScoutTest extends TestCase
{
    public function testSearch(): void
    {
        $this->expectException(\Throwable::class);
        SearchableModel::search('test')->get();
    }
}
