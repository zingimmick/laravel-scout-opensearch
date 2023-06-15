<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Scout\Builder;
use Laravel\Scout\Jobs\RemoveFromSearch;
use Zing\LaravelScout\OpenSearch\Tests\Fixtures\SearchableModelHasUuids;

/**
 * @internal
 */
final class ScoutHasUuidsTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        if (! class_exists(HasUuids::class)) {
            self::markTestSkipped('Support for HasUuids available since 9.0.');
        }
        parent::setUp();

        $searchableModelHasUuids = new SearchableModelHasUuids();
        $searchableModelHasUuids->searchableUsing()
            ->createIndex($searchableModelHasUuids->searchableAs());
    }

    protected function tearDown(): void
    {
        $searchableModelHasUuids = new SearchableModelHasUuids();
        $searchableModelHasUuids->searchableUsing()
            ->deleteIndex($searchableModelHasUuids->searchableAs());

        parent::tearDown();
    }

    public function testSearch(): void
    {
        SearchableModelHasUuids::query()->create([
            'name' => 'test search 1',
        ]);
        SearchableModelHasUuids::query()->create([
            'name' => 'test search 2',
        ]);
        SearchableModelHasUuids::query()->create([
            'name' => 'test search 3',
        ]);
        SearchableModelHasUuids::query()->create([
            'name' => 'test search 4',
        ]);
        SearchableModelHasUuids::query()->create([
            'name' => 'test search 5',
        ]);
        SearchableModelHasUuids::query()->create([
            'name' => 'test search 6',
        ]);
        SearchableModelHasUuids::query()->create([
            'name' => 'not matched',
        ]);
        sleep(1);
        self::assertCount(6, SearchableModelHasUuids::search('test')->get());
        SearchableModelHasUuids::query()->firstOrFail()->delete();
        sleep(1);
        self::assertCount(5, SearchableModelHasUuids::search('test')->get());
        self::assertCount(1, SearchableModelHasUuids::search('test')->paginate(2, 'page', 3)->items());
        if (method_exists(Builder::class, 'cursor')) {
            self::assertCount(5, SearchableModelHasUuids::search('test')->cursor());
        }

        self::assertCount(5, SearchableModelHasUuids::search('test')->keys());
        SearchableModelHasUuids::removeAllFromSearch();
        sleep(1);
        self::assertCount(0, SearchableModelHasUuids::search('test')->get());
        self::assertCount(0, SearchableModelHasUuids::search('test')->paginate(2, 'page', 3)->items());
        if (method_exists(Builder::class, 'cursor')) {
            self::assertCount(0, SearchableModelHasUuids::search('test')->cursor());
        }

        self::assertCount(0, SearchableModelHasUuids::search('test')->keys());
    }

    public function testOrderBy(): void
    {
        SearchableModelHasUuids::query()->create([
            'name' => 'test search 1',
        ]);
        SearchableModelHasUuids::query()->create([
            'name' => 'test search 2',
        ]);
        SearchableModelHasUuids::query()->create([
            'name' => 'test search 3',
        ]);
        sleep(1);
        self::assertSame(3, SearchableModelHasUuids::search('test')->first()->getKey());
        self::assertSame(1, SearchableModelHasUuids::search('test')->orderBy('id')->first()->getKey());
        self::assertSame(3, SearchableModelHasUuids::search('test')->orderBy('id', 'desc')->first()->getKey());
    }

    public function testWhere(): void
    {
        SearchableModelHasUuids::query()->create([
            'name' => 'test',
            'is_visible' => 1,
        ]);
        SearchableModelHasUuids::query()->create([
            'name' => 'test',
            'is_visible' => 1,
        ]);
        SearchableModelHasUuids::query()->create([
            'name' => 'test',
            'is_visible' => 0,
        ]);
        SearchableModelHasUuids::query()->create([
            'name' => 'nothing',
        ]);
        sleep(1);
        self::assertCount(3, SearchableModelHasUuids::search('test')->get());
        self::assertCount(2, SearchableModelHasUuids::search('test')->where('is_visible', 1)->get());
        if (method_exists(Builder::class, 'whereIn')) {
            self::assertCount(3, SearchableModelHasUuids::search('test')->whereIn('is_visible', [0, 1])->get());
            self::assertCount(0, SearchableModelHasUuids::search('test')->whereIn('is_visible', [])->get());
        }
    }
}
