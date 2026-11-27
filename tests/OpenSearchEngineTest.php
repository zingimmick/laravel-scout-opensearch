<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Jobs\RemoveFromSearch;
use Mockery as m;
use OpenSearch\Client;
use Zing\LaravelScout\OpenSearch\Engines\OpenSearchEngine;
use Zing\LaravelScout\OpenSearch\Tests\Fixtures\CustomKeySearchableModel;
use Zing\LaravelScout\OpenSearch\Tests\Fixtures\EmptySearchableModel;
use Zing\LaravelScout\OpenSearch\Tests\Fixtures\IndexableSearchableModel;
use Zing\LaravelScout\OpenSearch\Tests\Fixtures\SearchableAndSoftDeletesModel;
use Zing\LaravelScout\OpenSearch\Tests\Fixtures\SearchableModel;
use Zing\LaravelScout\OpenSearch\Tests\Fixtures\SoftDeletedEmptySearchableModel;
use Zing\LaravelScout\OpenSearch\Tests\Fixtures\Status;

/**
 * @internal
 */
final class OpenSearchEngineTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        Config::shouldReceive('get')->with('scout.after_commit', m::any())->andReturn(false);
        Config::shouldReceive('get')->with('scout.soft_delete', m::any())->andReturn(false);
        Config::shouldReceive('get')->with('scout.jobs.tries', m::any())->andReturn(null);
        Config::shouldReceive('get')->with('scout.jobs.backoff', m::any())->andReturn(null);
        Config::shouldReceive('get')->with('scout.jobs.max_exceptions', m::any())->andReturn(null);
        Container::getInstance()->bind('config', static fn () => Config::getFacadeRoot());
    }

    public function testUpdateAddsObjectsToIndex(): void
    {
        $client = m::mock(Client::class);
        $searchableModel = new SearchableModel([
            'id' => 1,
        ]);
        $client->shouldReceive('bulk')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    [
                        'index' => [
                            '_index' => 'table',
                            '_id' => 1,
                        ],
                    ],
                    [
                        'id' => 1,
                        $searchableModel->getScoutKeyName() => $searchableModel->getScoutKey(),
                    ],
                ],
            ]);

        $openSearchEngine = new OpenSearchEngine($client);
        $openSearchEngine->update(Collection::make([$searchableModel]));
    }

    public function testUpdateUsesIndexableAs(): void
    {
        $client = m::mock(Client::class);
        $searchableModel = new IndexableSearchableModel([
            'id' => 1,
        ]);
        $client->shouldReceive('bulk')
            ->once()
            ->with([
                'index' => 'write-table',
                'body' => [
                    [
                        'index' => [
                            '_index' => 'write-table',
                            '_id' => 1,
                        ],
                    ],
                    [
                        'id' => 1,
                        $searchableModel->getScoutKeyName() => $searchableModel->getScoutKey(),
                    ],
                ],
            ]);

        $openSearchEngine = new OpenSearchEngine($client);
        $openSearchEngine->update(Collection::make([$searchableModel]));
    }

    public function testUpdateWithSoftDeletes(): void
    {
        $client = m::mock(Client::class);
        $searchableAndSoftDeletesModel = new SearchableAndSoftDeletesModel([
            'id' => 1,
        ]);
        $client->shouldReceive('bulk')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    [
                        'index' => [
                            '_index' => 'table',
                            '_id' => 1,
                        ],
                    ],
                    [
                        'id' => 1,
                        '__soft_deleted' => 0,
                        $searchableAndSoftDeletesModel->getScoutKeyName() => $searchableAndSoftDeletesModel->getScoutKey(),
                    ],
                ],
            ]);

        $openSearchEngine = new OpenSearchEngine($client, true);
        $openSearchEngine->update(Collection::make([$searchableAndSoftDeletesModel]));
    }

    public function testUpdateEmpty(): void
    {
        $client = m::mock(Client::class);
        $client->shouldNotReceive('bulk');

        $openSearchEngine = new OpenSearchEngine($client);
        $openSearchEngine->update(Collection::make([]));
    }

    public function testDeleteEmpty(): void
    {
        $client = m::mock(Client::class);
        $client->shouldNotReceive('bulk');

        $openSearchEngine = new OpenSearchEngine($client);
        $openSearchEngine->delete(Collection::make([]));
    }

    public function testDeleteRemovesObjectsToIndex(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('bulk')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    [
                        'delete' => [
                            '_index' => 'table',
                            '_id' => 1,
                        ],
                    ],
                ],
            ]);

        $openSearchEngine = new OpenSearchEngine($client);
        $openSearchEngine->delete(Collection::make([
            new SearchableModel([
                'id' => 1,
            ]),
        ]));
    }

    public function testDeleteUsesIndexableAs(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('bulk')
            ->once()
            ->with([
                'index' => 'write-table',
                'body' => [
                    [
                        'delete' => [
                            '_index' => 'write-table',
                            '_id' => 1,
                        ],
                    ],
                ],
            ]);

        $openSearchEngine = new OpenSearchEngine($client);
        $openSearchEngine->delete(Collection::make([
            new IndexableSearchableModel([
                'id' => 1,
            ]),
        ]));
    }

    public function testDeleteRemovesObjectsToIndexWithACustomSearchKey(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('bulk')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    [
                        'delete' => [
                            '_index' => 'table',
                            '_id' => 'my-opensearch-key.5',
                        ],
                    ],
                ],
            ]);

        $openSearchEngine = new OpenSearchEngine($client);
        $openSearchEngine->delete(Collection::make([
            new CustomKeySearchableModel([
                'id' => 5,
            ]),
        ]));
    }

    public function testDeleteWithRemoveableScoutCollectionUsingCustomSearchKey(): void
    {
        if (! class_exists(RemoveFromSearch::class)) {
            $this->markTestSkipped('Support for RemoveFromSearch available since 9.0.');
        }

        $job = new RemoveFromSearch(Collection::make([
            new CustomKeySearchableModel([
                'id' => 5,
            ]),
        ]));

        $job = unserialize(serialize($job));

        $client = m::mock(Client::class);
        $client->shouldReceive('bulk')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    [
                        'delete' => [
                            '_index' => 'table',
                            '_id' => 'my-opensearch-key.5',
                        ],
                    ],
                ],
            ]);
        $openSearchEngine = new OpenSearchEngine($client);
        $openSearchEngine->delete($job->models);
    }

    public function testRemoveFromSearchJobUsesCustomSearchKey(): void
    {
        if (! class_exists(RemoveFromSearch::class)) {
            $this->markTestSkipped('Support for RemoveFromSearch available since 9.0.');
        }

        $job = new RemoveFromSearch(Collection::make([
            new CustomKeySearchableModel([
                'id' => 5,
            ]),
        ]));

        $job = unserialize(serialize($job));

        Container::getInstance()->bind(EngineManager::class, static function () {
            $engine = m::mock(OpenSearchEngine::class);
            $engine->shouldReceive('delete')
                ->once()
                ->with(m::on(static function ($collection): bool {
                    $keyName = ($model = $collection->first())
                        ->getScoutKeyName();

                    return $model->getAttributes()[$keyName] === 'my-opensearch-key.5';
                }));
            $manager = m::mock(EngineManager::class);
            $manager->shouldReceive('engine')
                ->once()
                ->andReturn($engine);

            return $manager;
        });

        $job->handle();
    }

    public function testSearchWithEmptyQueryUsesMatchAll(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('search')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'query' => [
                        'match_all' => new \stdClass(),
                    ],
                ],
            ]);

        (new OpenSearchEngine($client))->search(new Builder(new SearchableModel(), ''));
    }

    public function testSearchWithEmptyQueryAndWhereFilterKeepsFilters(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('search')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'term' => [
                                        'is_visible' => 1,
                                    ],
                                ],
                            ],
                            'must_not' => [],
                        ],
                    ],
                ],
            ]);

        $builder = new Builder(new SearchableModel(), '');
        $builder->where('is_visible', 1);

        (new OpenSearchEngine($client))->search($builder);
    }

    public function testSearchPreservesZeroLimit(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('search')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'query_string' => [
                                        'query' => 'zonda',
                                    ],
                                ],
                            ],
                            'must_not' => [],
                        ],
                    ],
                    'size' => 0,
                ],
            ]);

        $builder = new Builder(new SearchableModel(), 'zonda');
        $builder->take(0);

        (new OpenSearchEngine($client))->search($builder);
    }

    public function testPaginatePreservesZeroPerPage(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('search')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'query_string' => [
                                        'query' => 'zonda',
                                    ],
                                ],
                            ],
                            'must_not' => [],
                        ],
                    ],
                    'size' => 0,
                    'from' => 0,
                ],
            ]);

        (new OpenSearchEngine($client))->paginate(new Builder(new SearchableModel(), 'zonda'), 0, 1);
    }

    public function testPaginateIncludesSizeAndFrom(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('search')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'query_string' => [
                                        'query' => 'zonda',
                                    ],
                                ],
                            ],
                            'must_not' => [],
                        ],
                    ],
                    'size' => 10,
                    'from' => 20,
                ],
            ]);

        (new OpenSearchEngine($client))->paginate(new Builder(new SearchableModel(), 'zonda'), 10, 3);
    }

    public function testSearchUsesSearchableAs(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('search')
            ->once()
            ->with([
                'index' => 'read-table',
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'query_string' => [
                                        'query' => 'zonda',
                                    ],
                                ],
                            ],
                            'must_not' => [],
                        ],
                    ],
                ],
            ]);

        $openSearchEngine = new OpenSearchEngine($client);
        $openSearchEngine->search(new Builder(new IndexableSearchableModel(), 'zonda'));
    }

    public function testSearchSupportsBackedEnumWhereFilter(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('search')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'query_string' => [
                                        'query' => 'zonda',
                                    ],
                                ],
                                [
                                    'term' => [
                                        'status' => 'active',
                                    ],
                                ],
                            ],
                            'must_not' => [],
                        ],
                    ],
                ],
            ]);

        $openSearchEngine = new OpenSearchEngine($client);
        $builder = new Builder(new SearchableModel(), 'zonda');
        $builder->where('status', Status::Active);

        $openSearchEngine->search($builder);
    }

    public function testSearchSupportsBackedEnumWhereInFilter(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('search')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'query_string' => [
                                        'query' => 'zonda',
                                    ],
                                ],
                                [
                                    'terms' => [
                                        'status' => [
                                            'active',
                                            'inactive',
                                        ],
                                    ],
                                ],
                            ],
                            'must_not' => [],
                        ],
                    ],
                ],
            ]);

        $openSearchEngine = new OpenSearchEngine($client);
        $builder = new Builder(new SearchableModel(), 'zonda');
        $builder->whereIn('status', [Status::Active, Status::Inactive]);

        $openSearchEngine->search($builder);
    }

    public function testSearchSupportsBackedEnumWhereNotInFilter(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('search')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'query_string' => [
                                        'query' => 'zonda',
                                    ],
                                ],
                            ],
                            'must_not' => [
                                [
                                    'terms' => [
                                        'status' => [
                                            'active',
                                            'inactive',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $openSearchEngine = new OpenSearchEngine($client);
        $builder = new Builder(new SearchableModel(), 'zonda');
        $builder->whereNotIn('status', [Status::Active, Status::Inactive]);

        $openSearchEngine->search($builder);
    }

    public function testWhereSupportsStringValue(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('search')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'query_string' => [
                                        'query' => 'zonda',
                                    ],
                                ],
                                [
                                    'term' => [
                                        'status' => 'active',
                                    ],
                                ],
                            ],
                            'must_not' => [],
                        ],
                    ],
                ],
            ]);

        $builder = new Builder(new SearchableModel(), 'zonda');
        $builder->where('status', 'active');

        (new OpenSearchEngine($client))->search($builder);
    }

    public function testWhereSupportsBooleanValue(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('search')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'query_string' => [
                                        'query' => 'zonda',
                                    ],
                                ],
                                [
                                    'term' => [
                                        'is_visible' => true,
                                    ],
                                ],
                            ],
                            'must_not' => [],
                        ],
                    ],
                ],
            ]);

        $builder = new Builder(new SearchableModel(), 'zonda');
        $builder->where('is_visible', true);

        (new OpenSearchEngine($client))->search($builder);
    }

    public function testWhereSupportsNullValue(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('search')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'query_string' => [
                                        'query' => 'zonda',
                                    ],
                                ],
                                [
                                    'bool' => [
                                        'must_not' => [
                                            'exists' => [
                                                'field' => 'status',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'must_not' => [],
                        ],
                    ],
                ],
            ]);

        $builder = new Builder(new SearchableModel(), 'zonda');
        $builder->where('status', null);

        (new OpenSearchEngine($client))->search($builder);
    }

    public function testWhereSupportsNotNullValue(): void
    {
        if (! $this->supportsWhereOperators()) {
            $this->markTestSkipped('Support for where operators available since Scout 11.0.');
        }

        $client = m::mock(Client::class);
        $client->shouldReceive('search')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'query_string' => [
                                        'query' => 'zonda',
                                    ],
                                ],
                                [
                                    'bool' => [
                                        'must' => [
                                            'exists' => [
                                                'field' => 'status',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'must_not' => [],
                        ],
                    ],
                ],
            ]);

        $builder = new Builder(new SearchableModel(), 'zonda');
        $builder->where('status', '!=', null);

        (new OpenSearchEngine($client))->search($builder);
    }

    public function testWhereSupportsBackedEnumNotEqualFilter(): void
    {
        if (! $this->supportsWhereOperators()) {
            $this->markTestSkipped('Support for where operators available since Scout 11.0.');
        }

        $client = m::mock(Client::class);
        $client->shouldReceive('search')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'query_string' => [
                                        'query' => 'zonda',
                                    ],
                                ],
                                [
                                    'bool' => [
                                        'must_not' => [
                                            'term' => [
                                                'status' => 'active',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'must_not' => [],
                        ],
                    ],
                ],
            ]);

        $builder = new Builder(new SearchableModel(), 'zonda');
        $builder->where('status', '!=', Status::Active);

        (new OpenSearchEngine($client))->search($builder);
    }

    public function testWhereSupportsBackedEnumRangeFilter(): void
    {
        if (! $this->supportsWhereOperators()) {
            $this->markTestSkipped('Support for where operators available since Scout 11.0.');
        }

        $client = m::mock(Client::class);
        $client->shouldReceive('search')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'query_string' => [
                                        'query' => 'zonda',
                                    ],
                                ],
                                [
                                    'range' => [
                                        'status' => [
                                            'gte' => 'inactive',
                                        ],
                                    ],
                                ],
                            ],
                            'must_not' => [],
                        ],
                    ],
                ],
            ]);

        $builder = new Builder(new SearchableModel(), 'zonda');
        $builder->where('status', '>=', Status::Inactive);

        (new OpenSearchEngine($client))->search($builder);
    }

    public function testSearchSendsCorrectParametersToAlgolia(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('search')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'query_string' => [
                                        'query' => 'zonda',
                                    ],
                                ],
                                [
                                    'term' => [
                                        'foo' => 1,
                                    ],
                                ],
                            ],
                            'must_not' => [],
                        ],
                    ],
                    'sort' => [
                        [
                            'id' => [
                                'order' => 'desc',
                            ],
                        ],
                    ],
                ],
            ]);

        $openSearchEngine = new OpenSearchEngine($client);
        $builder = new Builder(new SearchableModel(), 'zonda');
        $builder->where('foo', 1)
            ->orderBy('id', 'desc');
        $openSearchEngine->search($builder);
    }

    public function testSearchSendsCorrectParametersToAlgoliaForWhereInSearch(): void
    {
        if (! method_exists(Builder::class, 'whereIn')) {
            $this->markTestSkipped('Support for whereIn available since 9.0.');
        }

        $client = m::mock(Client::class);
        $client->shouldReceive('search')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'query_string' => [
                                        'query' => 'zonda',
                                    ],
                                ],
                                [
                                    'term' => [
                                        'foo' => 1,
                                    ],
                                ],
                                [
                                    'terms' => [
                                        'bar' => [1, 2],
                                    ],
                                ],
                            ],
                            'must_not' => [],
                        ],
                    ],
                    'sort' => [
                        [
                            'id' => [
                                'order' => 'desc',
                            ],
                        ],
                    ],
                ],
            ]);

        $openSearchEngine = new OpenSearchEngine($client);
        $builder = new Builder(new SearchableModel(), 'zonda');
        $builder->where('foo', 1)
            ->whereIn('bar', [1, 2])
            ->orderBy('id', 'desc');
        $openSearchEngine->search($builder);
    }

    public function testSearchSendsCorrectParametersToAlgoliaForEmptyWhereInSearch(): void
    {
        if (! method_exists(Builder::class, 'whereIn')) {
            $this->markTestSkipped('Support for whereIn available since 9.0.');
        }

        $client = m::mock(Client::class);
        $client->shouldReceive('search')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'query_string' => [
                                        'query' => 'zonda',
                                    ],
                                ],
                                [
                                    'term' => [
                                        'foo' => 1,
                                    ],
                                ],
                                [
                                    'terms' => [
                                        'bar' => [],
                                    ],
                                ],
                            ],
                            'must_not' => [],
                        ],
                    ],
                    'sort' => [
                        [
                            'id' => [
                                'order' => 'desc',
                            ],
                        ],
                    ],
                ],
            ]);

        $openSearchEngine = new OpenSearchEngine($client);
        $builder = new Builder(new SearchableModel(), 'zonda');
        $builder->where('foo', 1)
            ->whereIn('bar', [])
            ->orderBy('id', 'desc');
        $openSearchEngine->search($builder);
    }

    public function testMapCorrectlyMapsResultsToModels(): void
    {
        $client = m::mock(Client::class);
        $openSearchEngine = new OpenSearchEngine($client);

        $model = m::mock(SearchableModel::class);
        $model->shouldReceive('getScoutModelsByIds')
            ->andReturn($models = Collection::make([
                new SearchableModel([
                    'id' => 1,
                ]),
            ]));

        $builder = m::mock(Builder::class);

        $results = $openSearchEngine->map($builder, [
            'nbHits' => 1,
            'hits' => [
                [
                    '_id' => 1,
                    'id' => 1,
                ],
            ],
        ], $model);

        $this->assertCount(1, $results);
        $metadata = $results->first()
            ->scoutMetadata();
        $this->assertSame([
            '_id' => 1,
        ], $metadata);
    }

    public function testMapMethodRespectsOrder(): void
    {
        $client = m::mock(Client::class);
        $openSearchEngine = new OpenSearchEngine($client);

        $model = m::mock(SearchableModel::class);
        $model->shouldReceive('getScoutModelsByIds')
            ->andReturn($models = Collection::make([
                new SearchableModel([
                    'id' => 1,
                ]),
                new SearchableModel([
                    'id' => 2,
                ]),
                new SearchableModel([
                    'id' => 3,
                ]),
                new SearchableModel([
                    'id' => 4,
                ]),
            ]));

        $builder = m::mock(Builder::class);

        $results = $openSearchEngine->map($builder, [
            'nbHits' => 4,
            'hits' => [
                [
                    '_id' => 1,
                    'id' => 1,
                ],
                [
                    '_id' => 2,
                    'id' => 2,
                ],
                [
                    '_id' => 4,
                    'id' => 4,
                ],
                [
                    '_id' => 3,
                    'id' => 3,
                ],
            ],
        ], $model);

        $this->assertCount(4, $results);

        // It's important we assert with array keys to ensure
        // they have been reset after sorting.
        $this->assertSame([
            0 => [
                'id' => 1,
            ],
            1 => [
                'id' => 2,
            ],
            2 => [
                'id' => 4,
            ],
            3 => [
                'id' => 3,
            ],
        ], $results->toArray());
    }

    public function testLazyMapCorrectlyMapsResultsToModels(): void
    {
        if (! method_exists(Builder::class, 'cursor')) {
            $this->markTestSkipped('Support for cursor available since 9.0.');
        }

        $client = m::mock(Client::class);
        $openSearchEngine = new OpenSearchEngine($client);

        $model = m::mock(SearchableModel::class);
        $model->shouldReceive('queryScoutModelsByIds->cursor')
            ->andReturn($models = LazyCollection::make([
                new SearchableModel([
                    'id' => 1,
                ]),
            ]));

        $builder = m::mock(Builder::class);

        $results = $openSearchEngine->lazyMap($builder, [
            'nbHits' => 1,
            'hits' => [
                [
                    '_id' => 1,
                    'id' => 1,
                ],
            ],
        ], $model);

        $this->assertCount(1, $results);
        $metadata = $results->first()
            ->scoutMetadata();
        $this->assertSame([
            '_id' => 1,
        ], $metadata);
    }

    public function testLazyMapMethodRespectsOrder(): void
    {
        if (! method_exists(Builder::class, 'cursor')) {
            $this->markTestSkipped('Support for cursor available since 9.0.');
        }

        $client = m::mock(Client::class);
        $openSearchEngine = new OpenSearchEngine($client);

        $model = m::mock(SearchableModel::class);
        $model->shouldReceive('queryScoutModelsByIds->cursor')
            ->andReturn($models = LazyCollection::make([
                new SearchableModel([
                    'id' => 1,
                ]),
                new SearchableModel([
                    'id' => 2,
                ]),
                new SearchableModel([
                    'id' => 3,
                ]),
                new SearchableModel([
                    'id' => 4,
                ]),
            ]));

        $builder = m::mock(Builder::class);

        $results = $openSearchEngine->lazyMap($builder, [
            'hits' => [
                [
                    '_id' => 1,
                    'id' => 1,
                ],
                [
                    '_id' => 2,
                    'id' => 2,
                ],
                [
                    '_id' => 4,
                    'id' => 4,
                ],
                [
                    '_id' => 3,
                    'id' => 3,
                ],
            ],
        ], $model);

        $this->assertCount(4, $results);

        // It's important we assert with array keys to ensure
        // they have been reset after sorting.
        $this->assertSame([
            0 => [
                'id' => 1,
            ],
            1 => [
                'id' => 2,
            ],
            2 => [
                'id' => 4,
            ],
            3 => [
                'id' => 3,
            ],
        ], $results->toArray());
    }

    public function testAModelIsIndexedWithACustomAlgoliaKey(): void
    {
        $client = m::mock(Client::class);
        $customKeySearchableModel = new CustomKeySearchableModel([
            'id' => 1,
        ]);
        $client->shouldReceive('bulk')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    [
                        'index' => [
                            '_index' => 'table',
                            '_id' => 'my-opensearch-key.1',
                        ],
                    ],
                    [
                        'id' => 1,
                        $customKeySearchableModel->getScoutKeyName() => $customKeySearchableModel->getScoutKey(),
                    ],
                ],
            ]);

        $openSearchEngine = new OpenSearchEngine($client);
        $openSearchEngine->update(Collection::make([$customKeySearchableModel]));
    }

    public function testAModelIsRemovedWithACustomAlgoliaKey(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('bulk')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    [
                        'delete' => [
                            '_index' => 'table',
                            '_id' => 'my-opensearch-key.1',
                        ],
                    ],
                ],
            ]);

        $openSearchEngine = new OpenSearchEngine($client);
        $openSearchEngine->delete(Collection::make([
            new CustomKeySearchableModel([
                'id' => 1,
            ]),
        ]));
    }

    public function testCreateIndexCreatesIndex(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('indices->create')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'settings' => [],
                ],
            ])
            ->andReturn([
                'acknowledged' => true,
                'shards_acknowledged' => true,
                'index' => 'table',
            ]);

        $this->assertSame([
            'acknowledged' => true,
            'shards_acknowledged' => true,
            'index' => 'table',
        ], (new OpenSearchEngine($client))->createIndex('table', [
            'settings' => [],
        ]));
    }

    public function testCreateIndexIsIdempotentWhenIndexAlreadyExists(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('indices->create')
            ->once()
            ->andThrow(new \Exception('resource_already_exists_exception: [table] already exists'));

        $this->assertSame([], (new OpenSearchEngine($client))->createIndex('table'));
    }

    public function testCreateIndexRethrowsOtherExceptions(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('indices->create')
            ->once()
            ->andThrow(new \Exception('mapper_parsing_exception: failed to parse'));

        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('mapper_parsing_exception: failed to parse');

        (new OpenSearchEngine($client))->createIndex('table');
    }

    public function testUpdateIndexSettingsUpdatesSettings(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('indices->putSettings')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'index' => [
                        'number_of_replicas' => 1,
                        'refresh_interval' => '1s',
                    ],
                ],
            ]);

        (new OpenSearchEngine($client))->updateIndexSettings('table', [
            'number_of_replicas' => 1,
            'settings' => [
                'refresh_interval' => '1s',
            ],
        ]);
    }

    public function testUpdateIndexSettingsUpdatesMappings(): void
    {
        $mappings = [
            'properties' => [
                'name' => [
                    'type' => 'keyword',
                ],
            ],
        ];

        $client = m::mock(Client::class);
        $client->shouldReceive('indices->putMapping')
            ->once()
            ->with([
                'index' => 'table',
                'body' => $mappings,
            ]);

        (new OpenSearchEngine($client))->updateIndexSettings('table', [
            'mappings' => $mappings,
        ]);
    }

    public function testUpdateIndexSettingsUpdatesSettingsAndMappings(): void
    {
        $mappings = [
            'properties' => [
                'name' => [
                    'type' => 'keyword',
                ],
            ],
        ];

        $client = m::mock(Client::class);
        $client->shouldReceive('indices->putSettings')
            ->once()
            ->with([
                'index' => 'table',
                'body' => [
                    'index' => [
                        'number_of_replicas' => 1,
                    ],
                ],
            ]);
        $client->shouldReceive('indices->putMapping')
            ->once()
            ->with([
                'index' => 'table',
                'body' => $mappings,
            ]);

        (new OpenSearchEngine($client))->updateIndexSettings('table', [
            'number_of_replicas' => 1,
            'mappings' => $mappings,
        ]);
    }

    public function testConfigureSoftDeleteFilterAddsMapping(): void
    {
        $this->assertSame([
            'mappings' => [
                'properties' => [
                    '__soft_deleted' => [
                        'type' => 'integer',
                    ],
                ],
            ],
        ], (new OpenSearchEngine(m::mock(Client::class)))->configureSoftDeleteFilter());
    }

    public function testConfigureSoftDeleteFilterPreservesExistingMapping(): void
    {
        $settings = [
            'mappings' => [
                'properties' => [
                    '__soft_deleted' => [
                        'type' => 'byte',
                    ],
                ],
            ],
        ];

        $this->assertSame($settings, (new OpenSearchEngine(m::mock(Client::class)))->configureSoftDeleteFilter($settings));
    }

    public function testDeleteAllIndexesDeletesOnlyIndexesWithScoutPrefix(): void
    {
        Config::shouldReceive('get')
            ->with('scout.prefix', m::any())
            ->andReturn('scout_');

        $client = m::mock(Client::class);
        $client->shouldReceive('cat->indices')
            ->once()
            ->with([
                'format' => 'json',
                'h' => 'index',
            ])
            ->andReturn([
                [
                    'index' => 'scout_users',
                ],
                [
                    'index' => 'other_index',
                ],
                [
                    'index' => 'scout_posts',
                ],
            ]);
        $client->shouldReceive('indices->delete')
            ->once()
            ->with([
                'index' => 'scout_users,scout_posts',
            ])
            ->andReturn([
                'acknowledged' => true,
            ]);

        $this->assertSame([
            'acknowledged' => true,
        ], (new OpenSearchEngine($client))->deleteAllIndexes());
    }

    public function testDeleteAllIndexesDoesNothingWhenNoIndexesMatch(): void
    {
        Config::shouldReceive('get')
            ->with('scout.prefix', m::any())
            ->andReturn('scout_');

        $client = m::mock(Client::class);
        $client->shouldReceive('cat->indices')
            ->once()
            ->with([
                'format' => 'json',
                'h' => 'index',
            ])
            ->andReturn([
                [
                    'index' => 'other_index',
                ],
            ]);
        $client->shouldNotReceive('indices->delete');

        $this->assertSame([], (new OpenSearchEngine($client))->deleteAllIndexes());
    }

    public function testFlushUsesIndexableAs(): void
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('deleteByQuery')
            ->once()
            ->with([
                'index' => 'write-table',
                'body' => [
                    'query' => [
                        'match_all' => new \stdClass(),
                    ],
                ],
            ])
            ->andReturn('write-table');

        $openSearchEngine = new OpenSearchEngine($client);
        $openSearchEngine->flush(new IndexableSearchableModel());
    }

    public function testFlushAModelWithACustomAlgoliaKey(): void
    {
        $model = m::mock(CustomKeySearchableModel::class);
        $model->shouldReceive('indexableAs')
            ->once()
            ->withNoArgs()
            ->andReturn('table');
        $client = m::mock(Client::class);
        $client->shouldReceive('deleteByQuery')
            ->with([
                'index' => 'table',
                'body' => [
                    'query' => [
                        'match_all' => new \stdClass(),
                    ],
                ],
            ])
            ->andReturn('table');
        $openSearchEngine = new OpenSearchEngine($client);
        $openSearchEngine->flush($model);
    }

    public function testUpdateEmptySearchableArrayDoesNotAddObjectsToIndex(): void
    {
        $client = m::mock(Client::class);

        $client->shouldNotReceive('bulk');

        $openSearchEngine = new OpenSearchEngine($client);
        $openSearchEngine->update(Collection::make([new EmptySearchableModel()]));
    }

    public function testUpdateEmptySearchableArrayFromSoftDeletedModelDoesNotAddObjectsToIndex(): void
    {
        $client = m::mock(Client::class);
        $client->shouldNotReceive('bulk');

        $openSearchEngine = new OpenSearchEngine($client, true);
        $openSearchEngine->update(Collection::make([new SoftDeletedEmptySearchableModel()]));
    }

    public function testMapWithoutHits(): void
    {
        $client = m::mock(Client::class);
        $openSearchEngine = new OpenSearchEngine($client);

        $model = m::mock(SearchableModel::class);
        $model->shouldReceive('newCollection')
            ->andReturn($models = Collection::make());

        $builder = m::mock(Builder::class);

        $results = $openSearchEngine->map($builder, [
            'nbHits' => 1,
            'hits' => null,
        ], $model);

        $this->assertCount(0, $results);

        $results = $openSearchEngine->map($builder, null, $model);

        $this->assertCount(0, $results);
    }

    public function testLazyMapWithoutHits(): void
    {
        $client = m::mock(Client::class);
        $openSearchEngine = new OpenSearchEngine($client);

        $model = m::mock(SearchableModel::class);
        $model->shouldReceive('newCollection')
            ->andReturn($models = Collection::make());

        $builder = m::mock(Builder::class);

        $results = $openSearchEngine->lazyMap($builder, [
            'nbHits' => 1,
            'hits' => null,
        ], $model);

        $this->assertCount(0, $results);

        $results = $openSearchEngine->lazyMap($builder, null, $model);

        $this->assertCount(0, $results);
    }

    public function testOpenSearchClientMethod(): void
    {
        $client = m::mock(Client::class);
        $openSearchEngine = new OpenSearchEngine($client);
        $client->shouldReceive('nodes')
            ->withNoArgs()
            ->once();
        $openSearchEngine->nodes();
    }

    public function testMapIds(): void
    {
        $client = m::mock(Client::class);
        $openSearchEngine = new OpenSearchEngine($client);

        $results = $openSearchEngine->mapIds(null);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
    }
}
