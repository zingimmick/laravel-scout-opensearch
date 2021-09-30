<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Engines;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use OpenSearch\Client\AppClient;
use OpenSearch\Client\DocumentClient;
use OpenSearch\Client\OpenSearchClient;
use OpenSearch\Client\SearchClient;
use OpenSearch\Util\SearchParamsBuilder;

class OpenSearchEngine extends Engine
{
    /**
     * The Algolia client.
     *
     * @var \OpenSearch\Client\OpenSearchClient
     */
    protected $opensearch;

    /**
     * Determines if soft deletes for Scout are enabled or not.
     *
     * @var bool
     */
    protected $softDelete = false;

    /**
     * @var \OpenSearch\Client\DocumentClient
     */
    protected $document;

    /**
     * @var \OpenSearch\Client\SearchClient
     */
    protected $search;

    /**
     * @var \OpenSearch\Client\AppClient
     */
    protected $app;

    /**
     * Create a new engine instance.
     *
     * @param \OpenSearch\Client\OpenSearchClient $opensearch
     * @param bool $softDelete
     */
    public function __construct(OpenSearchClient $opensearch, $softDelete = false)
    {
        $this->opensearch = $opensearch;
        $this->softDelete = $softDelete;
        $this->document = new DocumentClient($opensearch);
        $this->search = new SearchClient($opensearch);
        $this->app = new AppClient($opensearch);
    }

    public function update($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        if ($this->usesSoftDelete($models->first()) && $this->softDelete) {
            $models->each->pushSoftDeleteMetadata();
        }

        $objects = $models->map(function ($model) {
            $searchableData = $model->toSearchableArray();
            if (empty($searchableData)) {
                return;
            }

            return array_merge([
                'id' => $model->getScoutKey(),
            ], $searchableData, $model->scoutMetadata());
        })->filter()
            ->values()
            ->all();

        if (! empty($objects)) {
            foreach ($objects as $object) {
                $this->document->add($object);
            }

            $this->document->commit(
                $this->getAppName($models->first()->searchableAs()),
                $this->getTableName($models->first()->searchableAs())
            );
        }
    }

    protected function getAppName(string $searchableAs): string
    {
        return Str::before($searchableAs, '.');
    }

    protected function getTableName(string $searchableAs): string
    {
        return Str::after($searchableAs, '.');
    }

    public function delete($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $objects = $models->map(function ($model) {
            return [
                'id' => $model->getScoutKey(),
            ];
        })->values()
            ->all();
        foreach ($objects as $object) {
            $this->document->remove($object);
        }

        $this->document->commit(
            $this->getAppName($models->first()->searchableAs()),
            $this->getTableName($models->first()->searchableAs())
        );
    }

    public function search(Builder $builder)
    {
        return $this->performSearch($builder, array_filter([
            'query' => $builder->query,
            'hits' => $builder->limit,
            'appName' => $this->getAppName($builder->model->searchableAs()),
            'format' => 'fulljson',
            'start' => 0,
        ]));
    }

    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, array_filter([
            'query' => $builder->query,
            'hits' => $perPage,
            'appName' => $this->getAppName($builder->model->searchableAs()),
            'format' => 'fulljson',
            'start' => $perPage * ($page - 1),
        ]));
    }

    /**
     * @param \Laravel\Scout\Builder $builder
     * @param array $options
     *
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        if ($builder->callback !== null) {
            return call_user_func($builder->callback, $this->search, $builder->query, $options);
        }

        $query = $options['query'];
        $options['query'] = is_string($query) ? "'{$query}'" : $query;
        $searchParamsBuilder = new SearchParamsBuilder($options);
        if (empty($builder->orders)) {
            $searchParamsBuilder->addSort('id', SearchParamsBuilder::SORT_DECREASE);
        }

        foreach ($builder->orders as $order) {
            if ($order['direction'] === 'desc') {
                $direction = SearchParamsBuilder::SORT_DECREASE;
                $searchParamsBuilder->addSort($order['column'], $direction);
            } elseif ($order['direction'] === 'asc') {
                $direction = SearchParamsBuilder::SORT_INCREASE;
                $searchParamsBuilder->addSort($order['column'], $direction);
            }
        }

        foreach ($builder->wheres as $key => $value) {
            $searchParamsBuilder->setFilter("{$key}={$value}");
        }

        $result = $this->search->execute($searchParamsBuilder->build());
        $searchResult = json_decode($result->result, true);

        return $searchResult['result'] ?? null;
    }

    public function mapIds($results)
    {
        if ($results === null) {
            return collect();
        }

        return collect($results['items'])->pluck('fields.id')->values();
    }

    public function map(Builder $builder, $results, $model)
    {
        if ($results === null) {
            return $model->newCollection();
        }

        if (
            (is_array($results['items']) || $results['items'] instanceof \Countable ? count(
                $results['items']
            ) : 0) === 0
        ) {
            return $model->newCollection();
        }

        $objectIds = collect($results['items'])->pluck('fields.id')->values()->all();

        $objectIdPositions = array_flip($objectIds);

        return $model->getScoutModelsByIds($builder, $objectIds)
            ->filter(function ($model) use ($objectIds) {
                return in_array($model->getScoutKey(), $objectIds);
            })->sortBy(function ($model) use ($objectIdPositions) {
                return $objectIdPositions[$model->getScoutKey()];
            })->values();
    }

    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param \Laravel\Scout\Builder $builder
     * @param mixed $results
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return \Illuminate\Support\LazyCollection
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        if ($results === null) {
            return LazyCollection::make($model->newCollection());
        }

        if (
            (is_array($results['items']) || $results['items'] instanceof \Countable ? count(
                $results['items']
            ) : 0) === 0
        ) {
            return LazyCollection::make($model->newCollection());
        }

        $objectIds = collect($results['items'])->pluck('fields.id')->values()->all();
        $objectIdPositions = array_flip($objectIds);

        return $model->queryScoutModelsByIds($builder, $objectIds)
            ->cursor()
            ->filter(function ($model) use ($objectIds) {
                return in_array($model->getScoutKey(), $objectIds);
            })->sortBy(function ($model) use ($objectIdPositions) {
                return $objectIdPositions[$model->getScoutKey()];
            })->values();
    }

    public function getTotalCount($results)
    {
        return $results['total'] ?? 0;
    }

    public function flush($model): void
    {
        $model->newQuery()
            ->orderBy($model->getKeyName())
            ->unsearchable();
    }

    /**
     * Create a search index.
     *
     * @param string $name
     * @param array $options
     *
     * @return mixed
     */
    public function createIndex($name, array $options = [])
    {
        return $this->app->save($name);
    }

    /**
     * Delete a search index.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function deleteIndex($name)
    {
        return $this->app->removeById($name);
    }

    /**
     * Determine if the given model uses soft deletes.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return bool
     */
    protected function usesSoftDelete($model)
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model), true);
    }
}
