<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Engines;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Laravel\Scout\Jobs\RemoveableScoutCollection;
use OpenSearch\Client;

class OpenSearchEngine extends Engine
{
    /**
     * Create a new engine instance.
     */
    public function __construct(
        protected Client $client,
        protected bool    $softDelete = false
    ) {
    }

    /**
     * Update the given model in the index.
     *
     * @param \Illuminate\Database\Eloquent\Collection $models
     */
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

            return array_merge($searchableData, $model->scoutMetadata(), [
                    'id' => $model->getScoutKey(),
                ],);
        })->filter()
            ->values()
            ->all();

        if ($objects !== []) {
            $data = [];
            foreach ($objects as $object) {
                $data[] = [
                    'index' => [
                        '_index' => $model->searchableAs(),
                        '_id' => $object['id'],
                    ],
                ];
                $data[] = $model;
            }

            $this->client->bulk([
                'index' => $model->searchableAs(),
                'body' => $data,
            ]);
        }
    }

    /**
     * Remove the given model from the index.
     *
     * @param \Illuminate\Database\Eloquent\Collection $models
     */
    public function delete($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = $models->first();

        $keys = $models instanceof RemoveableScoutCollection
            ? $models->pluck($models->first()->getUnqualifiedScoutKeyName())
            : $models->map->getScoutKey();

        $data = $keys->map(static fn($object): array => [
            'delete' => [
                '_index' => $model->searchableAs(),
                '_id' => $object,
            ],
        ])->all();

        $this->client->bulk([
            'index' => $model->searchableAs(),
            'body' => $data,
        ]);
    }

    /**
     * Perform the given search on the engine.
     */
    public function search(Builder $builder): mixed
    {
        return $this->performSearch($builder, array_filter([
            'size' => $builder->limit,
        ]));
    }

    /**
     * Perform the given search on the engine.
     *
     * @param int $perPage
     * @param int $page
     */
    public function paginate(Builder $builder, $perPage, $page): mixed
    {
        return $this->performSearch($builder, [
            'size' => $perPage,
            'from' => $perPage * ($page - 1),
        ]);
    }

    /**
     * Perform the given search on the engine.
     */
    protected function performSearch(Builder $builder, array $options = []): mixed
    {
        $index = $builder->index ?: $builder->model->searchableAs();
        if (property_exists($builder, 'options')) {
            $options = array_merge($builder->options, $options);
        }

        if ($builder->callback instanceof \Closure) {
            return \call_user_func($builder->callback, $this->client, $builder->query, $options);
        }

        $query = $builder->query;
        $options['query'] = [
            'query_string' => [
                'query' => $query,
            ],
        ];
        $options['sort'] = collect($builder->orders)->map(static fn ($order): array => [
            $order['column'] => [
                'order' => $order['direction'],
            ],
        ])->whenEmpty(static fn (): \Illuminate\Support\Collection => collect([
            [
                'id' => [
                    'order' => 'desc',
                ],
            ],
        ]));
        $result = $this->client->search([
            'index' => $index,
            'body' => $options,
        ]);

        return $result['hits'] ?? null;
    }


    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param array{hits: mixed[]|null}|null $results
     */
    public function mapIds($results): Collection
    {
        if ($results === null) {
            return collect();
        }

        return collect($results['hits'])->pluck('_id')->values();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param array{hits: mixed[]|null}|null $results
     * @param Model $model
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function map(Builder $builder, $results, $model): mixed
    {
        if ($results === null) {
            return $model->newCollection();
        }

        if (! isset($results['hits'])) {
            return $model->newCollection();
        }

        if ($results['hits'] === []) {
            return $model->newCollection();
        }

        $objectIds = collect($results['hits'])->pluck('_id')->values()->all();

        $objectIdPositions = array_flip($objectIds);

        return $model->getScoutModelsByIds(
            $builder,
            $objectIds
        )
            ->filter(static fn ($model): bool => \in_array($model->getScoutKey(), $objectIds, false))
            ->sortBy(static fn ($model): int => $objectIdPositions[$model->getScoutKey()])->values();
    }

    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param array{hits: mixed[]|null}|null $results
     * @param Model $model
     *
     * @return LazyCollection
     */
    public function lazyMap(Builder $builder, $results, $model): LazyCollection
    {
        if ($results === null) {
            return LazyCollection::make($model->newCollection());
        }

        if (! isset($results['hits'])) {
            return LazyCollection::make($model->newCollection());
        }

        if ($results['hits'] === []) {
            return LazyCollection::make($model->newCollection());
        }

        $objectIds = collect($results['hits'])->pluck('_id')->values()->all();
        $objectIdPositions = array_flip($objectIds);

        return $model->queryScoutModelsByIds($builder, $objectIds)
            ->cursor()
            ->filter(static fn ($model): bool => \in_array($model->getScoutKey(), $objectIds, false))
            ->sortBy(static fn ($model): int => $objectIdPositions[$model->getScoutKey()])->values();
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param mixed $results
     *
     * @return int
     */
    public function getTotalCount($results):int
    {
        return $results['total']['value'] ?? 0;
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param Model $model
     */
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
     *
     * @return mixed
     */
    public function createIndex($name, array $options = []): array
    {
        return $this->client->indices()
            ->create([
                'index' => $name,
                'body' => $options,
            ]);
    }

    /**
     * Delete a search index.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function deleteIndex($name): array
    {
        return $this->client->indices()
            ->delete([
                'index' => $name,
            ]);
    }

    /**
     * Determine if the given model uses soft deletes.
     */
    protected function usesSoftDelete(Model $model): bool
    {
        return \in_array(SoftDeletes::class, class_uses_recursive($model), true);
    }

    /**
     * Dynamically call the OpenSearch client instance.
     *
     * @param string $method
     * @param array $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->client->{$method}(...$parameters);
    }
}
