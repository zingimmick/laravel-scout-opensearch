<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch;

use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use Zing\LaravelScout\OpenSearch\Engines\OpenSearchEngine;

class OpenSearchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        resolve(EngineManager::class)->extend(
            'opensearch',
            static fn (): OpenSearchEngine => new OpenSearchEngine(resolve(Client::class), config(
                'scout.soft_delete',
                false
            ))
        );
    }

    public function register(): void
    {
        $this->app->singleton(
            Client::class,
            static fn ($app): Client => ClientBuilder::fromConfig($app['config']->get('scout.opensearch'))
        );
    }
}
