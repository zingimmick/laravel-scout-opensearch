<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Engines;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
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
    protected DocumentClient $document;

    protected SearchClient $search;

    protected AppClient $app;

    /**
     * Create a new engine instance.
     *
     * @param bool $softDelete
     */
    public function __construct(
        /**
         * The Algolia client.
         */
        protected OpenSearchClient $openSearchClient,
        protected $softDelete = false
    ) {
        $this->document = new DocumentClient($openSearchClient);
        $this->search = new SearchClient($openSearchClient);
        $this->app = new AppClient($openSearchClient);
    }

    public function update($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = $models->first();
        if ($this->usesSoftDelete($model) && $this->softDelete) {
            $models->each->pushSoftDeleteMetadata();
        }

        $objects = $models->map(static function ($model): ?array {
            $searchableData = $model->toSearchableArray();
            if (empty($searchableData)) {
                return null;
            }

            return array_merge([
                'id' => $model->getScoutKey(),
            ], $searchableData, $model->scoutMetadata());
        })->filter()
            ->values()
            ->all();

        if ($objects !== []) {
            foreach ($objects as $object) {
                $this->document->add($object);
            }

            $searchableAs = $model->searchableAs();
            $this->document->commit($this->getAppName($searchableAs), $this->getTableName($searchableAs));
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

        $objects = $models->map(static fn ($model): array => [
            'id' => $model->getScoutKey(),
        ])->values()
            ->all();
        foreach ($objects as $object) {
            $this->document->remove($object);
        }

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = $models->first();
        $searchableAs = $model->searchableAs();
        $this->document->commit($this->getAppName($searchableAs), $this->getTableName($searchableAs));
    }

    public function search(Builder $builder): mixed
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
     */
    public function paginate(Builder $builder, $perPage, $page): mixed
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
     */
    protected function performSearch(Builder $builder, array $options = []): mixed
    {
        if ($builder->callback instanceof \Closure) {
            return \call_user_func($builder->callback, $this->search, $builder->query, $options);
        }

        $query = $options['query'];
        $options['query'] = \is_string($query) ? sprintf("'%s'", $query) : $query;
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
        $searchResult = json_decode($result->result, true, 512, JSON_THROW_ON_ERROR);

        return $searchResult['result'] ?? null;
    }

    /**
     * @param array{items: mixed[]|null}|null $results
     */
    public function mapIds($results): Collection
    {
        if ($results === null) {
            return collect();
        }

        return collect($results['items'])->pluck('fields.id')->values();
    }

    /**
     * @param array{items: mixed[]|null}|null $results
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    public function map(Builder $builder, $results, $model): mixed
    {
        if ($results === null) {
            return $model->newCollection();
        }

        if (! isset($results['items'])) {
            return $model->newCollection();
        }

        if ($results['items'] === []) {
            return $model->newCollection();
        }

        $objectIds = collect($results['items'])->pluck('fields.id')->values()->all();

        $objectIdPositions = array_flip($objectIds);

        return $model->getScoutModelsByIds($builder, $objectIds)
            ->filter(static fn ($model): bool => \in_array($model->getScoutKey(), $objectIds, false))
            ->sortBy(static fn ($model): int => $objectIdPositions[$model->getScoutKey()])->values();
    }

    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param array{items: mixed[]|null}|null $results
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    public function lazyMap(Builder $builder, $results, $model): mixed
    {
        if ($results === null) {
            return LazyCollection::make($model->newCollection());
        }

        if (! isset($results['items'])) {
            return LazyCollection::make($model->newCollection());
        }

        if ($results['items'] === []) {
            return LazyCollection::make($model->newCollection());
        }

        $objectIds = collect($results['items'])->pluck('fields.id')->values()->all();
        $objectIdPositions = array_flip($objectIds);

        return $model->queryScoutModelsByIds($builder, $objectIds)
            ->cursor()
            ->filter(static fn ($model): bool => \in_array($model->getScoutKey(), $objectIds, false))
            ->sortBy(static fn ($model): int => $objectIdPositions[$model->getScoutKey()])->values();
    }

    /**
     * @param array<string, mixed>|null $results
     */
    public function getTotalCount($results): mixed
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
    protected function usesSoftDelete(Model $model): bool
    {
        return \in_array(SoftDeletes::class, class_uses_recursive($model), true);
    }
}
