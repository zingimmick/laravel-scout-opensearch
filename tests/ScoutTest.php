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
        $this->assertCount(6, SearchableModel::search('test')->get());
        SearchableModel::query()->firstOrFail()->delete();
        sleep(1);
        $this->assertCount(5, SearchableModel::search('test')->get());
        $this->assertCount(1, SearchableModel::search('test')->paginate(2, 'page', 3)->items());
        if (method_exists(Builder::class, 'cursor')) {
            $this->assertCount(5, SearchableModel::search('test')->cursor());
        }

        $this->assertCount(5, SearchableModel::search('test')->keys());
        SearchableModel::removeAllFromSearch();
        sleep(1);
        $this->assertCount(0, SearchableModel::search('test')->get());
        $this->assertCount(0, SearchableModel::search('test')->paginate(2, 'page', 3)->items());
        if (method_exists(Builder::class, 'cursor')) {
            $this->assertCount(0, SearchableModel::search('test')->cursor());
        }

        $this->assertCount(0, SearchableModel::search('test')->keys());
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
        $this->assertSame(3, SearchableModel::search('test')->orderBy('id', 'desc')->first()->getKey());
        $this->assertSame(1, SearchableModel::search('test')->orderBy('id')->first()->getKey());
        $this->assertSame(3, SearchableModel::search('test')->orderBy('id', 'desc')->first()->getKey());
    }

    public function testWhere(): void
    {
        SearchableModel::query()->create([
            'name' => 'test',
            'is_visible' => 1,
        ]);
        SearchableModel::query()->create([
            'name' => 'test',
            'is_visible' => 1,
        ]);
        SearchableModel::query()->create([
            'name' => 'test',
            'is_visible' => 0,
        ]);
        SearchableModel::query()->create([
            'name' => 'nothing',
        ]);
        sleep(1);
        $this->assertCount(3, SearchableModel::search('test')->get());
        $this->assertCount(2, SearchableModel::search('test')->where('is_visible', 1)->get());
        if (method_exists(Builder::class, 'whereIn')) {
            $this->assertCount(3, SearchableModel::search('test')->whereIn('is_visible', [0, 1])->get());
            $this->assertCount(0, SearchableModel::search('test')->whereIn('is_visible', [])->get());
        }
    }
}
