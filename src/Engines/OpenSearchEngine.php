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
use OpenSearch\Generated\Common\OpenSearchResult;
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

        $objects = $models->map(function ($model): ?array {
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

        $objects = $models->map(function ($model): array {
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

    /**
     * @return mixed
     */
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

    /**
     * @param mixed $perPage
     * @param mixed $page
     *
     * @return mixed
     */
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
     * @param array<string, mixed> $options
     *
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        if ($builder->callback !== null) {
            return call_user_func($builder->callback, $this->search, $builder->query, $options);
        }

        $query = $options['query'];
        $options['query'] = is_string($query) ? sprintf("'%s'", $query) : $query;
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
            $searchParamsBuilder->setFilter(sprintf('%s=%s', $key, $value));
        }

        $result = $this->search->execute($searchParamsBuilder->build());
        /** @var array<string, mixed> $searchResult */
        $searchResult = json_decode($result->result, true);

        return $searchResult['result'] ?? null;
    }

    /**
     * @param array<string, mixed>|null $results
     */
    public function mapIds($results): \Illuminate\Support\Collection
    {
        if ($results === null) {
            return collect();
        }

        return collect($results['items'])->pluck('fields.id')->values();
    }

    /**
     * @param array<string, mixed>|null $results
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return \Illuminate\Database\Eloquent\Collection|mixed
     */
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
            ->filter(function ($model) use ($objectIds): bool {
                return in_array($model->getScoutKey(), $objectIds);
            })->sortBy(function ($model) use ($objectIdPositions) {
                return $objectIdPositions[$model->getScoutKey()];
            })->values();
    }

    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param array<string, mixed>|null $results
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return \Illuminate\Support\LazyCollection|mixed
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
            ->filter(function ($model) use ($objectIds): bool {
                return in_array($model->getScoutKey(), $objectIds);
            })->sortBy(function ($model) use ($objectIdPositions) {
                return $objectIdPositions[$model->getScoutKey()];
            })->values();
    }

    /**
     * @param array<string, mixed>|null $results
     *
     * @return int|mixed
     */
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
     * @param array<string, mixed> $options
     */
    public function createIndex($name, array $options = []): OpenSearchResult
    {
        return $this->app->save($name);
    }

    /**
     * Delete a search index.
     *
     * @param string $name
     */
    public function deleteIndex($name): OpenSearchResult
    {
        return $this->app->removeById($name);
    }

    /**
     * Determine if the given model uses soft deletes.
     */
    protected function usesSoftDelete(\Illuminate\Database\Eloquent\Model $model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model), true);
    }
}
