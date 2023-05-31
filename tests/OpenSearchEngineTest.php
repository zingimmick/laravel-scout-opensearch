<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use Zing\LaravelScout\OpenSearch\Engines\OpenSearchEngine;

/**
 * @internal
 */
final class OpenSearchEngineTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @var \Mockery\MockInterface&\OpenSearch\Client\OpenSearchClient
     */
    private \OpenSearch\Client $client;

    private OpenSearchEngine $openSearchEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = ClientBuilder::fromConfig([
            'hosts' => ['localhost:9200'],
            'retries' => 2,
            'handler' => ClientBuilder::multiHandler(),
            'logger' => new Logger('test', [new RotatingFileHandler('test')]),
            'basicAuthentication' => ['admin', 'admin'],
            'sslVerification' => false,
        ]);
        $this->openSearchEngine = new OpenSearchEngine($this->client);
        resolve(EngineManager::class)->extend('opensearch', fn (): OpenSearchEngine => $this->openSearchEngine);
        $this->openSearchEngine->createIndex((new SearchableModel())->searchableAs());
    }

    protected function tearDown(): void
    {
        $this->openSearchEngine->deleteIndex((new SearchableModel())->searchableAs());

        parent::tearDown();
    }

    public function testUpdate(): void
    {
        $this->openSearchEngine->update((new SearchableModel())->newCollection());
        $this->openSearchEngine->update(Collection::make([new SearchableModel()]));
        self::assertTrue(true);
    }

    public function testUpdateWithSoftDelete(): void
    {
        $openSearchEngine = new OpenSearchEngine($this->client, true);
        $openSearchEngine->update(Collection::make([new SearchableModel()]));
        self::assertTrue(true);
    }

    public function testUpdateWithEmpty(): void
    {
        $searchableModel = new SearchableModel();
        $this->openSearchEngine->update(Collection::make([$searchableModel]));
        self::assertTrue(true);
    }

    public function testDelete(): void
    {
        $model = SearchableModel::query()->create();
        $this->openSearchEngine->delete($model->newCollection());
        $this->openSearchEngine->delete(Collection::make([$model]));
        self::assertTrue(true);
    }

    /**
     * @return never
     */
    public function testSearch(): void
    {
        self::markTestSkipped('Incompatible test case.');
        $builder = new Builder(new SearchableModel(), 'zonda');
        $builder->where('foo', 1);
        $builder->orderBy('id', 'desc');

        $this->openSearchEngine->search($builder);
        self::assertTrue(true);
    }

    /**
     * @return never
     */
    public function testPaginate(): void
    {
        self::markTestSkipped('Incompatible test case.');
        $builder = new Builder(new SearchableModel(), 'zonda');
        $builder->where('foo', 1);
        $builder->orderBy('id', 'desc');
        $builder->orderBy('rank');

        self::assertIsArray($this->openSearchEngine->paginate($builder, 15, 1));
    }

    /**
     * @return never
     */
    public function testSearchFailed(): void
    {
        self::markTestSkipped('Incompatible test case.');
        $builder = new Builder(new SearchableModel(), 'zonda');
        $builder->where('foo', 1);
        $builder->orderBy('id', 'desc');

        self::assertSame([
            'total' => [
                'value' => 0,
                'relation' => 'eq',
            ],
            'max_score' => null,
            'hits' => [],
        ], $this->openSearchEngine->search($builder));
    }

    public function testCallback(): void
    {
        $builder = new Builder(
            new SearchableModel(),
            'huayra',
            function (Client $client, $query, $params): array {
                $this->assertSame([], $params);
                $this->assertSame('huayra', $query);

                return $client->search();
            }
        );
        $this->openSearchEngine->search($builder);
    }

    public function testCreateIndex(): void
    {
        $this->openSearchEngine->createIndex('test');
        self::assertTrue(true);
    }

    public function testDeleteIndex(): void
    {
        $this->openSearchEngine->deleteIndex('test');
        self::assertTrue(true);
    }

    /**
     * @return never
     */
    public function testSearchableFailed(): void
    {
        self::markTestSkipped('Incompatible test case.');
        self::assertCount(0, SearchableModel::search('test')->get());
    }

    public function testSearchEmpty(): void
    {
        SearchableModel::query()->create([
            'name' => 'test',
        ]);

        self::assertCount(0, SearchableModel::search('test')->get());
    }

    public function testCursor(): void
    {
        if (! method_exists(Builder::class, 'cursor')) {
            self::markTestSkipped('Support for cursor available since 9.0.');
        }

        $lazyCollection = SearchableModel::query()->create([
            'name' => 'test',
        ]);

        foreach (SearchableModel::search('test')->cursor() as $lazyCollection) {
            self::assertInstanceOf(SearchableModel::class, $lazyCollection);
        }

        foreach (SearchableModel::search('test')->cursor() as $lazyCollection) {
            self::assertInstanceOf(SearchableModel::class, $lazyCollection);
        }

        self::assertTrue(true);
    }

    public function testSearchable(): void
    {
        SearchableModel::query()->create([
            'name' => 'test',
        ]);

        sleep(1);
        self::assertCount(1, SearchableModel::search('test')->get());
    }

    public function testCursorFailed(): void
    {
        self::markTestSkipped('Incompatible test case.');
        if (! method_exists(Builder::class, 'cursor')) {
            self::markTestSkipped('Support for cursor available since 9.0.');
        }

        self::assertCount(0, SearchableModel::search('test')->cursor());
    }

    public function testPaginate2(): void
    {
        SearchableModel::query()->create([
            'name' => 'test',
        ]);
        sleep(1);
        self::assertSame(1, SearchableModel::search('test')->paginate()->total());

        self::assertSame(1, SearchableModel::search('test')->query(static function (): void {
        })->paginate()
            ->total());
    }

    /**
     * @return never
     */
    public function testPaginateFailed(): void
    {
        self::markTestSkipped('Incompatible test case.');
        self::assertSame(0, SearchableModel::search('nothing')->paginate()->total());
        self::assertSame(0, SearchableModel::search('nothing')->query(static function (): void {
        })->paginate()
            ->total());
    }

    public function testFlush(): void
    {
        SearchableModel::query()->create([
            'name' => 'test',
        ]);
        SearchableModel::removeAllFromSearch();
        self::assertTrue(true);
    }
}
