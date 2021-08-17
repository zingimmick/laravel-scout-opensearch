# Laravel Scout OpenSearch
<p align="center">
<a href="https://github.com/zingimmick/laravel-scout-opensearch/actions"><img src="https://github.com/zingimmick/laravel-scout-opensearch/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://codecov.io/gh/zingimmick/laravel-scout-opensearch"><img src="https://codecov.io/gh/zingimmick/laravel-scout-opensearch/branch/master/graph/badge.svg" alt="Code Coverage" /></a>
<a href="https://packagist.org/packages/zing/laravel-scout-opensearch"><img src="https://poser.pugx.org/zing/laravel-scout-opensearch/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/zing/laravel-scout-opensearch"><img src="https://poser.pugx.org/zing/laravel-scout-opensearch/downloads" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/zing/laravel-scout-opensearch"><img src="https://poser.pugx.org/zing/laravel-scout-opensearch/v/unstable.svg" alt="Latest Unstable Version"></a>
<a href="https://packagist.org/packages/zing/laravel-scout-opensearch"><img src="https://poser.pugx.org/zing/laravel-scout-opensearch/license" alt="License"></a>
</p>

> **Requires [PHP 7.2.0+](https://php.net/releases/)**

Require Laravel Scout OpenSearch using [Composer](https://getcomposer.org):

```bash
composer require zing/laravel-scout-opensearch
```

## Configuration

```php
return [
    // ...
    'opensearch' => [
        "access_key" => env('OPENSEARCH_ACCESS_KEY', 'your-opensearch-access-key'),
        "secret" => env('OPENSEARCH_SECRET', 'your-opensearch-secret'),
        "host" => env('OPENSEARCH_HOST', 'your-opensearch-host'),
        "options" => [
            "debug" => env('OPENSEARCH_DEBUG', false),
        ],
    ],
];
```

## License

Laravel Scout OpenSearch is an open-sourced software licensed under the [MIT license](LICENSE).
