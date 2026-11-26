<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use OpenSearch\ClientFactoryInterface;
use OpenSearch\GuzzleClientFactory;
use Zing\LaravelScout\OpenSearch\Engines\OpenSearchEngine;

class OpenSearchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        resolve(EngineManager::class)->extend(
            'opensearch',
            fn (): OpenSearchEngine => new OpenSearchEngine(resolve(Client::class), config(
                'scout.soft_delete',
                false
            ))
        );
    }

    public function register(): void
    {
        $this->app->singleton(Client::class, static function (Application $app): Client {
            /** @var array{factory?: class-string<\OpenSearch\ClientFactoryInterface>|null, client?: array<string, mixed>, retries?: int|string, logger?: \Psr\Log\LoggerInterface|null} $config */
            $config = (array) $app['config']->get('scout.opensearch', []);

            if (! isset($config['factory']) && ! isset($config['client']) && class_exists(ClientBuilder::class)) {
                return ClientBuilder::fromConfig($config);
            }

            if (! interface_exists(ClientFactoryInterface::class)) {
                throw new \InvalidArgumentException(
                    'The OpenSearch client factory is not available. Require opensearch-project/opensearch-php ^2.5 or use the legacy configuration.'
                );
            }

            $factoryClass = $config['factory'] ?? GuzzleClientFactory::class;

            if (! \is_string($factoryClass) || ! class_exists($factoryClass)) {
                throw new \InvalidArgumentException(\sprintf(
                    'The OpenSearch client factory [%s] is not available.',
                    \is_string($factoryClass) ? $factoryClass : get_debug_type($factoryClass)
                ));
            }

            $parameters = [];

            if (isset($config['retries'])) {
                $parameters['maxRetries'] = (int) $config['retries'];
            }

            if (isset($config['logger'])) {
                $parameters['logger'] = $config['logger'];
            }

            $factory = $parameters === []
                ? $app->make($factoryClass)
                : $app->makeWith($factoryClass, $parameters);

            if (! $factory instanceof ClientFactoryInterface) {
                throw new \InvalidArgumentException(\sprintf(
                    'The OpenSearch client factory [%s] must implement [%s].',
                    $factoryClass,
                    ClientFactoryInterface::class
                ));
            }

            return $factory->create($config['client'] ?? []);
        });
    }
}
