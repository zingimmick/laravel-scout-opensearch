<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Scout\Builder;

/**
 * @internal
 */
final class ScoutTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        $searchableModel = new SearchableModel();
        $searchableModel->searchableUsing()
            ->createIndex($searchableModel->searchableAs());
    }

    protected function tearDown(): void
    {
        $searchableModel = new SearchableModel();
        $searchableModel->searchableUsing()
            ->deleteIndex($searchableModel->searchableAs());

        parent::tearDown();
    }

    public function testSearch(): void
    {
        SearchableModel::query()->create([
            'name' => 'test search 1',
        ]);
        SearchableModel::query()->create([
            'name' => 'test search 2',
        ]);
        SearchableModel::query()->create([
            'name' => 'test search 3',
        ]);
        SearchableModel::query()->create([
            'name' => 'test search 4',
        ]);
        SearchableModel::query()->create([
            'name' => 'test search 5',
        ]);
        SearchableModel::query()->create([
            'name' => 'test search 6',
        ]);
        SearchableModel::query()->create([
            'name' => 'not matched',
        ]);
        sleep(1);
        self::assertCount(6, SearchableModel::search('test')->get());
        SearchableModel::query()->first()->delete();
        sleep(1);
        self::assertCount(5, SearchableModel::search('test')->get());
        self::assertCount(1, SearchableModel::search('test')->paginate(2, 'page', 3));
        if (method_exists(Builder::class, 'cursor')) {
            self::assertCount(5, SearchableModel::search('test')->cursor());
        }
        self::assertCount(5, SearchableModel::search('test')->keys());
        SearchableModel::removeAllFromSearch();
        sleep(1);
        self::assertCount(0, SearchableModel::search('test')->get());
        self::assertCount(0, SearchableModel::search('test')->paginate(2, 'page', 3));
        if (method_exists(Builder::class, 'cursor')) {
            self::assertCount(0, SearchableModel::search('test')->cursor());
        }
        self::assertCount(0, SearchableModel::search('test')->keys());
    }

    public function testOrderBy(): void
    {
        SearchableModel::query()->create([
            'name' => 'test search 1',
        ]);
        SearchableModel::query()->create([
            'name' => 'test search 2',
        ]);
        SearchableModel::query()->create([
            'name' => 'test search 3',
        ]);
        sleep(1);
        self::assertSame(3, SearchableModel::search('test')->first()->getKey());
        self::assertSame(1, SearchableModel::search('test')->orderBy('id')->first()->getKey());
        self::assertSame(3, SearchableModel::search('test')->orderBy('id', 'desc')->first()->getKey());
    }
}
