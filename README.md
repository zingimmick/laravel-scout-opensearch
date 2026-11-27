# Laravel Scout OpenSearch

<p align="center">
<a href="https://github.com/zingimmick/laravel-scout-opensearch/actions"><img src="https://github.com/zingimmick/laravel-scout-opensearch/actions/workflows/tests.yml/badge.svg?branch=4.x" alt="Build Status"></a>
<a href="https://codecov.io/gh/zingimmick/laravel-scout-opensearch"><img src="https://codecov.io/gh/zingimmick/laravel-scout-opensearch/branch/4.x/graph/badge.svg" alt="Code Coverage" /></a>
<a href="https://packagist.org/packages/zing/laravel-scout-opensearch"><img src="https://poser.pugx.org/zing/laravel-scout-opensearch/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/zing/laravel-scout-opensearch"><img src="https://poser.pugx.org/zing/laravel-scout-opensearch/downloads" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/zing/laravel-scout-opensearch"><img src="https://poser.pugx.org/zing/laravel-scout-opensearch/v/unstable.svg" alt="Latest Unstable Version"></a>
<a href="https://packagist.org/packages/zing/laravel-scout-opensearch"><img src="https://poser.pugx.org/zing/laravel-scout-opensearch/license" alt="License"></a>
</p>

> **Requires [PHP 8.2+](https://php.net/releases/)**

> See [UPGRADING.md](UPGRADING.md) for upgrade instructions.

Require Laravel Scout OpenSearch using [Composer](https://getcomposer.org):

```bash
composer require zing/laravel-scout-opensearch
```

## Configuration

```php
return [
    // ...
    'opensearch' => [
        'factory' => \OpenSearch\GuzzleClientFactory::class,
        'client' => [
            'base_uri' => env('OPENSEARCH_URL', 'http://localhost:9200'),
            'auth' => [env('OPENSEARCH_USERNAME', 'admin'), env('OPENSEARCH_PASSWORD', 'admin')],
            'verify' => env('OPENSEARCH_VERIFY_SSL', false),
        ],
        'retries' => env('OPENSEARCH_RETRIES', 2),
    ],
];
```

The `factory` option is optional and defaults to `\OpenSearch\GuzzleClientFactory::class`. It may be any class that implements `\OpenSearch\ClientFactoryInterface` and is resolved through Laravel's service container, so its constructor dependencies are injected automatically. The options in `client` are passed directly to the factory's `create()` method, so they are specific to the chosen HTTP client. The optional `retries` and `logger` options are passed to the factory's `maxRetries` and `logger` constructor arguments, which are supported by the built-in Guzzle and Symfony factories.

If `opensearch-project/opensearch-php` does not provide the factory API (it was introduced in 2.5), the legacy configuration is used automatically. Legacy configuration is also still supported on newer SDK versions through the deprecated `ClientBuilder`; migrating to the factory configuration above removes the upstream deprecation notices.

Set app name and table name for model

```php
class SearchableModel extends Model
{
    use Searchable;

    public function searchableAs(): string
    {
        return 'searchable_models_index';
    }

    /**
     * @return array{id: mixed}
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->getScoutKey(),
        ];
    }
}
```

Indexing operations use `indexableAs` (available since Laravel Scout 10.10), which defaults to `searchableAs`. Override it when writes should go to a different index than searches, for example while swapping indexes:

```php
public function indexableAs(): string
{
    return 'searchable_models_write_index';
}
```

`searchableAs` is still used for search queries, while `indexableAs` is used for indexing, deleting, and flushing records.

### Legacy configuration

When the configured SDK version does not provide client factories, or when the `factory` and `client` options are not set, the previous configuration is used:

```php
return [
    // ...
    'opensearch' => [
        'hosts' => [env('OPENSEARCH_HOST', 'localhost:9200')],
        'basicAuthentication' => [env('OPENSEARCH_USERNAME', 'admin'), env('OPENSEARCH_PASSWORD', 'admin')],
        'retries' => env('OPENSEARCH_RETRIES', 2),
    ],
];
```

On `opensearch-project/opensearch-php` 2.4 and later this path uses the deprecated `ClientBuilder` and emits deprecation notices, so prefer the factory configuration. The factory API used here is available since 2.5.

### Using the Symfony HTTP client

Install `symfony/http-client` and select `\OpenSearch\SymfonyClientFactory`:

```php
return [
    // ...
    'opensearch' => [
        'factory' => \OpenSearch\SymfonyClientFactory::class,
        'client' => [
            'base_uri' => env('OPENSEARCH_URL', 'http://localhost:9200'),
            'auth_basic' => [env('OPENSEARCH_USERNAME', 'admin'), env('OPENSEARCH_PASSWORD', 'admin')],
            'verify_peer' => env('OPENSEARCH_VERIFY_SSL', false),
        ],
        'retries' => env('OPENSEARCH_RETRIES', 2),
    ],
];
```

### Configuration for Amazon OpenSearch Service

Install `aws/aws-sdk-php` and use the AWS authentication options supported by the factory:

```php
return [
    // ...
    'opensearch' => [
        'factory' => \OpenSearch\GuzzleClientFactory::class,
        'client' => [
            'base_uri' => env('OPENSEARCH_URL', 'http://localhost:9200'),
            'auth_aws' => [
                'region' => env('OPENSEARCH_REGION', 'us-east-1'),
                'service' => env('OPENSEARCH_SERVICE', 'es'),
                'credentials' => [
                    'access_key' => env('OPENSEARCH_IAM_KEY'),
                    'secret_key' => env('OPENSEARCH_IAM_SECRET'),
                    'session_token' => env('OPENSEARCH_IAM_SESSION_TOKEN'),
                ],
            ],
        ],
        'retries' => env('OPENSEARCH_RETRIES', 2),
    ],
];
```

### Debug and logging

The built-in factories accept a PSR-3 logger through their constructors. Set the top-level `logger` option to a `\Psr\Log\LoggerInterface` instance:

```php
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

return [
    // ...
    'opensearch' => [
        'factory' => \OpenSearch\GuzzleClientFactory::class,
        'client' => [
            'base_uri' => env('OPENSEARCH_URL', 'http://localhost:9200'),
        ],
        'retries' => env('OPENSEARCH_RETRIES', 2),
        'logger' => (new Logger('opensearch'))->pushHandler(
            new RotatingFileHandler('opensearch.log')
        ),
    ],
];
```

### Custom factory

A custom factory only needs to implement `\OpenSearch\ClientFactoryInterface`. Its constructor parameters are resolved by Laravel, and the top-level `retries` and `logger` options are injected into constructor parameters named `maxRetries` and `logger`:

```php
use OpenSearch\Client;
use OpenSearch\ClientFactoryInterface;
use Psr\Log\LoggerInterface;

final class MyOpenSearchClientFactory implements ClientFactoryInterface
{
    public function __construct(
        protected int $maxRetries = 0,
        protected ?LoggerInterface $logger = null,
    ) {
    }

    public function create(array $options): Client
    {
        return (new \OpenSearch\GuzzleClientFactory($this->maxRetries, $this->logger))->create($options);
    }
}
```

Then select it in the configuration:

```php
'opensearch' => [
    'factory' => \App\Search\MyOpenSearchClientFactory::class,
    'client' => [
        'base_uri' => env('OPENSEARCH_URL', 'http://localhost:9200'),
    ],
    'retries' => env('OPENSEARCH_RETRIES', 2),
],
```

### Index settings

The engine implements Scout's `\Laravel\Scout\Contracts\UpdatesIndexSettings` contract. Configure settings per index under `scout.opensearch.index-settings`:

```php
return [
    // ...
    'opensearch' => [
        'factory' => \OpenSearch\GuzzleClientFactory::class,
        'client' => [
            'base_uri' => env('OPENSEARCH_URL', 'http://localhost:9200'),
        ],
        'index-settings' => [
            'searchable_models' => [
                'settings' => [
                    'number_of_replicas' => 1,
                    'refresh_interval' => '1s',
                ],
                'mappings' => [
                    'properties' => [
                        'name' => [
                            'type' => 'text',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
```

`settings` are sent with `indices()->putSettings()` and `mappings` with `indices()->putMapping()`. Flat keys outside `settings` and `mappings` are also treated as index settings.

Sync the configured settings with:

```bash
php artisan scout:sync-index-settings
```

When Scout soft deletes are enabled, the engine automatically adds an integer mapping for `__soft_deleted`.

## License

Laravel Scout OpenSearch is an open-sourced software licensed under the [MIT license](LICENSE).
