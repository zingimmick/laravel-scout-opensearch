<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Engines;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use OpenSearch\Client;

class OpenSearchEngine extends Engine
{
    protected Client $app;

    /**
     * Create a new engine instance.
     *
     * @param bool $softDelete
     */
    public function __construct(
        /**
         * The Algolia client.
         */
        protected Client $openSearchClient,
        protected bool $softDelete = false
    ) {
        $this->app = $openSearchClient;
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

            $this->app->bulk([
                'index' => $model->searchableAs(),
                'body' => $data,
            ]);
        }
    }

    public function delete($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = $models->first();
        $objects = $models->map(static fn ($model): array => [
            'id' => $model->getScoutKey(),
        ])->values()
            ->all();
        $data = [];
        foreach ($objects as $object) {
            $data[] = [
                'delete' => [
                    '_index' => $model->searchableAs(),
                    '_id' => $object['id'],
                ],
            ];
        }

        $this->app->bulk([
            'index' => $model->searchableAs(),
            'body' => $data,
        ]);
    }

    public function search(Builder $builder): mixed
    {
        return $this->performSearch($builder, array_filter([
            'size' => $builder->limit,
        ]));
    }

    /**
     * @param mixed $perPage
     * @param mixed $page
     */
    public function paginate(Builder $builder, $perPage, $page): mixed
    {
        return $this->performSearch($builder, array_filter([
            'size' => $perPage,
            'from' => $perPage * ($page - 1),
        ]));
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function performSearch(Builder $builder, array $options = []): mixed
    {
        $index = $builder->index ?: $builder->model->searchableAs();
        if (property_exists($builder, 'options')) {
            $options = array_merge($builder->options, $options);
        }

        if ($builder->callback instanceof \Closure) {
            return \call_user_func($builder->callback, $this->app, $builder->query, $options);
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
        $result = $this->app->search([
            'index' => $index,
            'body' => $options,
        ]);

        return $result['hits'] ?? null;
    }

    /**
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
     * @param array{hits: mixed[]|null}|null $results
     * @param \Illuminate\Database\Eloquent\Model $model
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

        return $model->getScoutModelsByIds($builder, $objectIds)
            ->filter(static fn ($model): bool => \in_array($model->getScoutKey(), $objectIds, false))
            ->sortBy(static fn ($model): int => $objectIdPositions[$model->getScoutKey()])->values();
    }

    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param array{hits: mixed[]|null}|null $results
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    public function lazyMap(Builder $builder, $results, $model): mixed
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
     * @param array<string, mixed>|null $results
     */
    public function getTotalCount($results): mixed
    {
        return $results['total']['value'] ?? 0;
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
    public function createIndex($name, array $options = []): array
    {
        return $this->openSearchClient->indices()
            ->create([
                'index' => $name,
                'body' => $options,
            ]);
    }

    /**
     * Delete a search index.
     *
     * @param string $name
     */
    public function deleteIndex($name): array
    {
        return $this->app->indices()
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
}
