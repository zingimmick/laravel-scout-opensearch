# Laravel Scout OpenSearch

<p align="center">
<a href="https://github.com/zingimmick/laravel-scout-opensearch/actions"><img src="https://github.com/zingimmick/laravel-scout-opensearch/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
<a href="https://codecov.io/gh/zingimmick/laravel-scout-opensearch"><img src="https://codecov.io/gh/zingimmick/laravel-scout-opensearch/branch/3.x/graph/badge.svg" alt="Code Coverage" /></a>
<a href="https://packagist.org/packages/zing/laravel-scout-opensearch"><img src="https://poser.pugx.org/zing/laravel-scout-opensearch/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/zing/laravel-scout-opensearch"><img src="https://poser.pugx.org/zing/laravel-scout-opensearch/downloads" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/zing/laravel-scout-opensearch"><img src="https://poser.pugx.org/zing/laravel-scout-opensearch/v/unstable.svg" alt="Latest Unstable Version"></a>
<a href="https://packagist.org/packages/zing/laravel-scout-opensearch"><img src="https://poser.pugx.org/zing/laravel-scout-opensearch/license" alt="License"></a>
</p>

> **Requires [PHP 8.0+](https://php.net/releases/)**

Require Laravel Scout OpenSearch using [Composer](https://getcomposer.org):

```bash
composer require zing/laravel-scout-opensearch
```

## Configuration

```php
return [
    // ...
    'opensearch' => [
        'hosts' => [env('OPENSEARCH_HOST', 'localhost:9200')],
        'basicAuthentication' => [env('OPENSEARCH_USERNAME', 'admin'), env('OPENSEARCH_PASSWORD', 'admin')],
        'retries' => env('OPENSEARCH_RETRYS', 2),
    ],
];
```

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

### Configuration for Amazon OpenSearch Service

```php
return [
    // ...
    'opensearch' => [
        'hosts' => [env('OPENSEARCH_HOST', 'localhost:9200')],
        'basicAuthentication' => [env('OPENSEARCH_USERNAME', 'admin'), env('OPENSEARCH_PASSWORD', 'admin')],
        'retries' => env('OPENSEARCH_RETRYS', 2),
        'sigV4Region' => env('OPENSEARCH_REGION', 'us-east-1') ,
        'sigV4Service' => env('OPENSEARCH_SERVICE', 'es') ,
        'sigV4CredentialProvider' => [
            'key' => env('OPENSEARCH_IAM_KEY'),
            'secret' => env('OPENSEARCH_IAM_SECRET'),
        ],
    ],
];
```

### Debug and logging

```php
return [
    // ...
    'opensearch' => [
        'hosts' => [env('OPENSEARCH_HOST', 'localhost:9200')],
        'basicAuthentication' => [env('OPENSEARCH_USERNAME', 'admin'), env('OPENSEARCH_PASSWORD', 'admin')],
        'retries' => env('OPENSEARCH_RETRYS', 2),
        'logger' => (new \Monolog\Logger('opensearch'))->pushHandler(new \Monolog\Handler\RotatingFileHandler('opensearch.log')),
        'tracer' => (new \Monolog\Logger('opensearch'))->pushHandler(new \Monolog\Handler\RotatingFileHandler('opensearch.log')),
    ],
];
```

## License

Laravel Scout OpenSearch is an open-sourced software licensed under the [MIT license](LICENSE).
