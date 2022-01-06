<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Mockery;
use OpenSearch\Client\OpenSearchClient;
use OpenSearch\Client\SearchClient;
use OpenSearch\Generated\Common\OpenSearchResult;
use OpenSearch\Util\SearchParamsBuilder;
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
    private $client;

    /**
     * @var \Zing\LaravelScout\OpenSearch\Engines\OpenSearchEngine
     */
    private $openSearchEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Mockery::mock(OpenSearchClient::class);
        $this->openSearchEngine = new OpenSearchEngine($this->client);
        resolve(EngineManager::class)->extend('opensearch', function (): OpenSearchEngine {
            return $this->openSearchEngine;
        });
    }

    public function testUpdate(): void
    {
        $this->client->shouldReceive('post')
            ->withArgs(['/apps/app/table/actions/bulk', new Mockery\Matcher\AnyArgs()])
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => '{
    "errors": [],
    "request_id": "150116724719940316170289",
    "status": "OK",
    "result": true
}',
            ]));
        $this->openSearchEngine->update(Collection::make());
        $this->openSearchEngine->update(Collection::make([new SearchableModel()]));
    }

    public function testUpdateWithSoftDelete(): void
    {
        $this->client->shouldReceive('post')
            ->withArgs(['/apps/app/table/actions/bulk', new Mockery\Matcher\AnyArgs()])
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => '{
    "errors": [],
    "request_id": "150116724719940316170289",
    "status": "OK",
    "result": true
}',
            ]));
        $openSearchEngine = new OpenSearchEngine($this->client, true);
        $openSearchEngine->update(Collection::make([new SearchableModel()]));
    }

    public function testUpdateWithEmpty(): void
    {
        $model = Mockery::mock(SearchableModel::class);
        $model->shouldReceive('toSearchableArray')
            ->andReturn([]);
        $this->openSearchEngine->update(Collection::make([$model]));
    }

    public function testDelete(): void
    {
        $this->client->shouldReceive('post')
            ->withArgs(['/apps/app/table/actions/bulk', new Mockery\Matcher\AnyArgs()])
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => '{
    "errors": [],
    "request_id": "150116724719940316170289",
    "status": "OK",
    "result": true
}',
            ]));
        $this->openSearchEngine->delete(Collection::make([]));
        $this->openSearchEngine->delete(Collection::make([new SearchableModel()]));
    }

    public function testSearch(): void
    {
        $this->client->shouldReceive('get')
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => '{
    "status": "OK",
    "request_id": "155310917017444091100003",
    "result": {
        "searchtime": 0.031081,
        "total": 1,
        "num": 1,
        "viewtotal": 1,
        "compute_cost": [
            {
                "index_name": "84922",
                "value": 0.292
            }
        ],
        "items": [
            {
                "fields": {
                    "id": "10",
                    "name": "我是一条新<em>文档</em>的标题",
                    "phone": "18312345678",
                    "index_name": "app_schema_demo"
                },
                "property": {},
                "attribute": {},
                "variableValue": {},
               "sortExprValues": [
                    "10",
                    "10000.1354238242"
                ]
            }
        ],
        "facet": []
    },
    "qp": [
        {
            "app_name": "84922",
            "query_correction_info": [
                {
                    "index": "default",
                    "original_query": "平果手机充电器",
                    "corrected_query": "苹果手机充电器",
                    "correction_level": 1,
                    "processor_name": "spell_check"
                }
            ]
        }
    ],
    "errors": [],
    "tracer": "",
    "ops_request_misc": "%7B%22request%5Fid%22%3A%22155310917017444091100003%22%7D"
}',
            ]));
        $builder = new Builder(new SearchableModel(), 'zonda');
        $builder->where('foo', 1);
        $builder->orderBy('id', 'desc');

        $this->openSearchEngine->search($builder);
    }

    public function testPaginate(): void
    {
        $this->client->shouldReceive('get')
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => '{
    "status": "OK",
    "request_id": "155310917017444091100003",
    "result": {
        "searchtime": 0.031081,
        "total": 1,
        "num": 1,
        "viewtotal": 1,
        "compute_cost": [
            {
                "index_name": "84922",
                "value": 0.292
            }
        ],
        "items": [
            {
                "fields": {
                    "id": "10",
                    "name": "我是一条新<em>文档</em>的标题",
                    "phone": "18312345678",
                    "index_name": "app_schema_demo"
                },
                "property": {},
                "attribute": {},
                "variableValue": {},
               "sortExprValues": [
                    "10",
                    "10000.1354238242"
                ]
            }
        ],
        "facet": []
    },
    "qp": [
        {
            "app_name": "84922",
            "query_correction_info": [
                {
                    "index": "default",
                    "original_query": "平果手机充电器",
                    "corrected_query": "苹果手机充电器",
                    "correction_level": 1,
                    "processor_name": "spell_check"
                }
            ]
        }
    ],
    "errors": [],
    "tracer": "",
    "ops_request_misc": "%7B%22request%5Fid%22%3A%22155310917017444091100003%22%7D"
}',
            ]));
        $builder = new Builder(new SearchableModel(), 'zonda');
        $builder->where('foo', 1);
        $builder->orderBy('id', 'desc');
        $builder->orderBy('rank');

        self::assertIsArray($this->openSearchEngine->paginate($builder, 15, 1));
    }

    public function testSearchFailed(): void
    {
        $this->client->shouldReceive('get')
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => '{
    "errors": [
        {
            "code": 2001,
            "message": "待查应用不存在.待查应用不存在。",
            "params": {
                "friendly_message": "待查应用不存在。"
            }
        }
    ],
    "request_id": "150116732819940316116461",
    "status": "FAIL"
}',
            ]));
        $builder = new Builder(new SearchableModel(), 'zonda');
        $builder->where('foo', 1);
        $builder->orderBy('id', 'desc');

        self::assertNull($this->openSearchEngine->search($builder));
    }

    public function testCallback(): void
    {
        $this->client->shouldReceive('get')
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => '{
    "status": "OK",
    "request_id": "155310917017444091100003",
    "result": {
        "searchtime": 0.031081,
        "total": 1,
        "num": 1,
        "viewtotal": 1,
        "compute_cost": [
            {
                "index_name": "84922",
                "value": 0.292
            }
        ],
        "items": [
            {
                "fields": {
                    "id": "10",
                    "name": "我是一条新<em>文档</em>的标题",
                    "phone": "18312345678",
                    "index_name": "app_schema_demo"
                },
                "property": {},
                "attribute": {},
                "variableValue": {},
               "sortExprValues": [
                    "10",
                    "10000.1354238242"
                ]
            }
        ],
        "facet": []
    },
    "qp": [
        {
            "app_name": "84922",
            "query_correction_info": [
                {
                    "index": "default",
                    "original_query": "平果手机充电器",
                    "corrected_query": "苹果手机充电器",
                    "correction_level": 1,
                    "processor_name": "spell_check"
                }
            ]
        }
    ],
    "errors": [],
    "tracer": "",
    "ops_request_misc": "%7B%22request%5Fid%22%3A%22155310917017444091100003%22%7D"
}',
            ]));
        $builder = new Builder(
            new SearchableModel(),
            'huayra',
            function (SearchClient $client, $query, $params): OpenSearchResult {
                $this->assertNotEmpty($params);
                $this->assertSame('huayra', $query);

                return $client->execute((new SearchParamsBuilder())->build());
            }
        );
        $this->openSearchEngine->search($builder);
    }

    public function testCreateIndex(): void
    {
        $this->client->shouldReceive('post')
            ->withArgs(['/apps', 'test'])
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => '{
    "errors": [],
    "request_id": "150116724719940316170289",
    "status": "OK",
    "result": true
}',
            ]));
        $this->openSearchEngine->createIndex('test');
    }

    public function testDeleteIndex(): void
    {
        $this->client->shouldReceive('delete')
            ->withArgs(['/apps/test'])
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => '{
    "errors": [],
    "request_id": "150116724719940316170289",
    "status": "OK",
    "result": true
}',
            ]));
        $this->openSearchEngine->deleteIndex('test');
    }

    public function testSeachable(): void
    {
        $this->client->shouldReceive('post')
            ->andReturn(new OpenSearchResult([
                'result' => '{
    "errors": [],
    "request_id": "150116724719940316170289",
    "status": "OK",
    "result": true
}',
            ]));
        $model = SearchableModel::query()->create([
            'name' => 'test',
        ]);
        $result = <<<CODE_SAMPLE
{
    "status": "OK",
    "request_id": "155310917017444091100003",
    "result": {
        "searchtime": 0.031081,
        "total": 1,
        "num": 1,
        "viewtotal": 1,
        "compute_cost": [
            {
                "index_name": "84922",
                "value": 0.292
            }
        ],
        "items": [
            {
                "fields": {
                    "id": "{$model->getKey()}",
                    "name": "我是一条新<em>文档</em>的标题",
                    "phone": "18312345678",
                    "index_name": "app_schema_demo"
                },
                "property": {},
                "attribute": {},
                "variableValue": {},
               "sortExprValues": [
                    "10",
                    "10000.1354238242"
                ]
            }
        ],
        "facet": []
    },
    "qp": [
        {
            "app_name": "84922",
            "query_correction_info": [
                {
                    "index": "default",
                    "original_query": "平果手机充电器",
                    "corrected_query": "苹果手机充电器",
                    "correction_level": 1,
                    "processor_name": "spell_check"
                }
            ]
        }
    ],
    "errors": [],
    "tracer": "",
    "ops_request_misc": "%7B%22request%5Fid%22%3A%22155310917017444091100003%22%7D"
}
CODE_SAMPLE;

        $this->client->shouldReceive('get')
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => $result,
            ]));

        self::assertCount(1, SearchableModel::search('test')->get());
    }

    public function testSeachableFailed(): void
    {
        $this->client->shouldReceive('post')
            ->andReturn(new OpenSearchResult([
                'result' => '{
    "errors": [],
    "request_id": "150116724719940316170289",
    "status": "OK",
    "result": true
}',
            ]));
        SearchableModel::query()->create([
            'name' => 'test',
        ]);
        $jsonData = [
            'errors' => [[
                'code' => 2001,
                'message' => '待查应用不存在.待查应用不存在。',
                'params' => [
                    'friendly_message' => '待查应用不存在。',
                ],
            ],
            ],
            'request_id' => '150116732819940316116461',
            'status' => 'FAIL',
        ];
        $result = json_encode($jsonData);

        $this->client->shouldReceive('get')
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => $result,
            ]));

        self::assertCount(0, SearchableModel::search('test')->get());
    }

    public function testSearchEmpty(): void
    {
        $this->client->shouldReceive('post')
            ->andReturn(new OpenSearchResult([
                'result' => '{
    "errors": [],
    "request_id": "150116724719940316170289",
    "status": "OK",
    "result": true
}',
            ]));
        SearchableModel::query()->create([
            'name' => 'test',
        ]);
        $jsonData = [
            'status' => 'OK',
            'request_id' => '155310917017444091100003',
            'result' => [
                'searchtime' => 0.031081,
                'total' => 1,
                'num' => 1,
                'viewtotal' => 1,
                'compute_cost' => [[
                    'index_name' => '84922',
                    'value' => 0.292,
                ],
                ],
                'items' => [],
                'facet' => [],
            ],
            'qp' => [[
                'app_name' => '84922',
                'query_correction_info' => [[
                    'index' => 'default',
                    'original_query' => '平果手机充电器',
                    'corrected_query' => '苹果手机充电器',
                    'correction_level' => 1,
                    'processor_name' => 'spell_check',
                ],
                ],
            ],
            ],
            'errors' => [],
            'tracer' => '',

            'ops_request_misc' => '%7B%22request%5Fid%22%3A%22155310917017444091100003%22%7D',
        ];
        $result = json_encode($jsonData);

        $this->client->shouldReceive('get')
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => $result,
            ]));
        self::assertCount(0, SearchableModel::search('test')->get());
    }

    public function testCursor(): void
    {
        if (! method_exists(Builder::class, 'cursor')) {
            self::markTestSkipped('Support for cursor available since 9.0.');
        }

        $this->client->shouldReceive('post')
            ->andReturn(new OpenSearchResult([
                'result' => '{
    "errors": [],
    "request_id": "150116724719940316170289",
    "status": "OK",
    "result": true
}',
            ]));
        $lazyCollection = SearchableModel::query()->create([
            'name' => 'test',
        ]);
        $jsonData = [
            'status' => 'OK',
            'request_id' => '155310917017444091100003',
            'result' => [
                'searchtime' => 0.031081,
                'total' => 1,
                'num' => 1,
                'viewtotal' => 1,
                'compute_cost' => [[
                    'index_name' => '84922',
                    'value' => 0.292,
                ],
                ],
                'items' => [],
                'facet' => [],
            ],
            'qp' => [[
                'app_name' => '84922',
                'query_correction_info' => [[
                    'index' => 'default',
                    'original_query' => '平果手机充电器',
                    'corrected_query' => '苹果手机充电器',
                    'correction_level' => 1,
                    'processor_name' => 'spell_check',
                ],
                ],
            ],
            ],
            'errors' => [],
            'tracer' => '',

            'ops_request_misc' => '%7B%22request%5Fid%22%3A%22155310917017444091100003%22%7D',
        ];
        $result = json_encode($jsonData);

        $this->client->shouldReceive('get')
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => $result,
            ]));
        foreach (SearchableModel::search('test')->cursor() as $lazyCollection) {
            self::assertInstanceOf(SearchableModel::class, $lazyCollection);
        }

        $result = <<<CODE_SAMPLE
{
    "status": "OK",
    "request_id": "155310917017444091100003",
    "result": {
        "searchtime": 0.031081,
        "total": 1,
        "num": 1,
        "viewtotal": 1,
        "compute_cost": [
            {
                "index_name": "84922",
                "value": 0.292
            }
        ],
        "items": [
         {
                "fields": {
                    "id": {$lazyCollection->getKey()},
                    "name": "我是一条新<em>文档</em>的标题",
                    "phone": "18312345678",
                    "index_name": "app_schema_demo"
                },
                "property": {},
                "attribute": {},
                "variableValue": {},
               "sortExprValues": [
                    "10",
                    "10000.1354238242"
                ]
            }
        ],
        "facet": []
    },
    "qp": [
        {
            "app_name": "84922",
            "query_correction_info": [
                {
                    "index": "default",
                    "original_query": "平果手机充电器",
                    "corrected_query": "苹果手机充电器",
                    "correction_level": 1,
                    "processor_name": "spell_check"
                }
            ]
        }
    ],
    "errors": [],
    "tracer": "",
    "ops_request_misc": "%7B%22request%5Fid%22%3A%22155310917017444091100003%22%7D"
}
CODE_SAMPLE;

        $this->client->shouldReceive('get')
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => $result,
            ]));
        foreach (SearchableModel::search('test')->cursor() as $lazyCollection) {
            self::assertInstanceOf(SearchableModel::class, $lazyCollection);
        }
    }

    public function testCursorFailed(): void
    {
        if (! method_exists(Builder::class, 'cursor')) {
            self::markTestSkipped('Support for cursor available since 9.0.');
        }

        $this->client->shouldReceive('post')
            ->andReturn(new OpenSearchResult([
                'result' => '{
    "errors": [],
    "request_id": "150116724719940316170289",
    "status": "OK",
    "result": true
}',
            ]));
        $lazyCollection = SearchableModel::query()->create([
            'name' => 'test',
        ]);
        $jsonData = [
            'status' => 'OK',
            'request_id' => '155310917017444091100003',
            'result' => [
                'searchtime' => 0.031081,
                'total' => 1,
                'num' => 1,
                'viewtotal' => 1,
                'compute_cost' => [[
                    'index_name' => '84922',
                    'value' => 0.292,
                ],
                ],
                'items' => [],
                'facet' => [],
            ],
            'qp' => [[
                'app_name' => '84922',
                'query_correction_info' => [[
                    'index' => 'default',
                    'original_query' => '平果手机充电器',
                    'corrected_query' => '苹果手机充电器',
                    'correction_level' => 1,
                    'processor_name' => 'spell_check',
                ],
                ],
            ],
            ],
            'errors' => [],
            'tracer' => '',

            'ops_request_misc' => '%7B%22request%5Fid%22%3A%22155310917017444091100003%22%7D',
        ];
        $result = json_encode($jsonData);

        $this->client->shouldReceive('get')
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => $result,
            ]));
        foreach (SearchableModel::search('test')->cursor() as $lazyCollection) {
            self::assertInstanceOf(SearchableModel::class, $lazyCollection);
        }

        $jsonData = [
            'errors' => [[
                'code' => 2001,
                'message' => '待查应用不存在.待查应用不存在。',
                'params' => [
                    'friendly_message' => '待查应用不存在。',
                ],
            ],
            ],
            'request_id' => '150116732819940316116461',
            'status' => 'FAIL',
        ];
        $result = json_encode($jsonData);

        $this->client->shouldReceive('get')
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => $result,
            ]));
        self::assertCount(0, SearchableModel::search('test')->cursor());
    }

    public function testPaginate2(): void
    {
        $this->client->shouldReceive('post')
            ->andReturn(new OpenSearchResult([
                'result' => '{
    "errors": [],
    "request_id": "150116724719940316170289",
    "status": "OK",
    "result": true
}',
            ]));
        $lazyCollection = SearchableModel::query()->create([
            'name' => 'test',
        ]);
        $result = <<<CODE_SAMPLE
{
    "status": "OK",
    "request_id": "155310917017444091100003",
    "result": {
        "searchtime": 0.031081,
        "total": 1,
        "num": 1,
        "viewtotal": 1,
        "compute_cost": [
            {
                "index_name": "84922",
                "value": 0.292
            }
        ],
        "items": [
         {
                "fields": {
                    "id": {$lazyCollection->getKey()},
                    "name": "我是一条新<em>文档</em>的标题",
                    "phone": "18312345678",
                    "index_name": "app_schema_demo"
                },
                "property": {},
                "attribute": {},
                "variableValue": {},
               "sortExprValues": [
                    "10",
                    "10000.1354238242"
                ]
            }
        ],
        "facet": []
    },
    "qp": [
        {
            "app_name": "84922",
            "query_correction_info": [
                {
                    "index": "default",
                    "original_query": "平果手机充电器",
                    "corrected_query": "苹果手机充电器",
                    "correction_level": 1,
                    "processor_name": "spell_check"
                }
            ]
        }
    ],
    "errors": [],
    "tracer": "",
    "ops_request_misc": "%7B%22request%5Fid%22%3A%22155310917017444091100003%22%7D"
}
CODE_SAMPLE;

        $this->client->shouldReceive('get')
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => $result,
            ]));
        self::assertSame(1, SearchableModel::search('test')->paginate()->total());
        $this->client->shouldReceive('get')
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => $result,
            ]));
        self::assertSame(1, SearchableModel::search('test')->query(function (): void {
        })->paginate()->total());
    }

    public function testPaginateFailed(): void
    {
        $this->client->shouldReceive('post')
            ->andReturn(new OpenSearchResult([
                'result' => '{
    "errors": [],
    "request_id": "150116724719940316170289",
    "status": "OK",
    "result": true
}',
            ]));
        SearchableModel::query()->create([
            'name' => 'test',
        ]);
        $jsonData = [
            'errors' => [[
                'code' => 2001,
                'message' => '待查应用不存在.待查应用不存在。',
                'params' => [
                    'friendly_message' => '待查应用不存在。',
                ],
            ],
            ],
            'request_id' => '150116732819940316116461',
            'status' => 'FAIL',
        ];
        $result = json_encode($jsonData);

        $this->client->shouldReceive('get')
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => $result,
            ]));
        self::assertSame(0, SearchableModel::search('test')->paginate()->total());
        $this->client->shouldReceive('get')
            ->times(1)
            ->andReturn(new OpenSearchResult([
                'result' => $result,
            ]));
        self::assertSame(0, SearchableModel::search('test')->query(function (): void {
        })->paginate()->total());
    }

    public function testFlush(): void
    {
        $this->client->shouldReceive('post')
            ->andReturn(new OpenSearchResult([
                'result' => '{
    "errors": [],
    "request_id": "150116724719940316170289",
    "status": "OK",
    "result": true
}',
            ]));
        SearchableModel::query()->create([
            'name' => 'test',
        ]);
        SearchableModel::removeAllFromSearch();
    }
}
