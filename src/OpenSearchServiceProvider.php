<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch;

use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use OpenSearch\Client\OpenSearchClient;
use Zing\LaravelScout\OpenSearch\Engines\OpenSearchEngine;

class OpenSearchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        resolve(EngineManager::class)->extend('opensearch', function () {
            return new OpenSearchEngine(resolve(OpenSearchClient::class), config('scout.soft_delete', false));
        });
    }

    public function register(): void
    {
        $this->app->singleton(OpenSearchClient::class, function ($app) {
            $config = $app['config']->get('scout.opensearch');

            return new OpenSearchClient(
                $config['access_key'],
                $config['secret'],
                $config['host'],
                $config['options'] ?? []
            );
        });
    }
}
