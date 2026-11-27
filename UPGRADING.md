# Upgrading

This guide covers the operations required to upgrade from 3.5.1 to 4.x and the changes that may affect existing behavior.

## From v3 to v4

### Requirements

4.x requires:

- PHP 8.2 or newer
- Laravel Scout 10.12 or newer
- `opensearch-project/opensearch-php` 2.0 or newer; 2.5 or newer is required for the client factory configuration

### Upgrade dependencies

```bash
composer require "laravel/scout:^10.12 || ^11.0"
composer require "zing/laravel-scout-opensearch:^4.0"
```

Before a stable 4.x release, use `dev-4.x` instead of `^4.0`.

If you plan to use the client factory configuration, verify the OpenSearch SDK:

```bash
composer require "opensearch-project/opensearch-php:^2.5"
```

### Update the OpenSearch client configuration

4.x creates clients through `OpenSearch\ClientFactoryInterface`:

```php
'opensearch' => [
    'factory' => \OpenSearch\GuzzleClientFactory::class,
    'client' => [
        'base_uri' => env('OPENSEARCH_URL', 'http://localhost:9200'),
        'auth' => [
            env('OPENSEARCH_USERNAME', 'admin'),
            env('OPENSEARCH_PASSWORD', 'admin'),
        ],
        'verify' => env('OPENSEARCH_VERIFY_SSL', false),
    ],
    'retries' => env('OPENSEARCH_RETRIES', 2),
],
```

The legacy configuration is still accepted, but only when **neither** `factory` nor `client` is set:

```php
'opensearch' => [
    'hosts' => [env('OPENSEARCH_HOST', 'localhost:9200')],
    'basicAuthentication' => [
        env('OPENSEARCH_USERNAME', 'admin'),
        env('OPENSEARCH_PASSWORD', 'admin'),
    ],
    'retries' => env('OPENSEARCH_RETRYS', 2),
],
```

The legacy path uses the deprecated `ClientBuilder` on `opensearch-project/opensearch-php` 2.4+ and emits deprecation notices. Prefer the factory configuration.

#### Configuration mapping

| 3.x option | 4.x factory option | Notes |
| --- | --- | --- |
| `hosts` | `client.base_uri` | Use a full URI including `http://` or `https://` |
| `basicAuthentication` | `client.auth` | Guzzle option; Symfony uses `auth_basic` |
| `retries` | `retries` | Passed to the factory's `maxRetries` |
| `logger` | `logger` | Passed to the factory's `logger` constructor argument |
| `tracer` | No direct equivalent | Keep the legacy configuration or implement a custom factory |
| `sigV4Region` / `sigV4Service` / `sigV4CredentialProvider` | `client.auth_aws` | See the AWS section below |
| `handler` / `connectionParams` and other ClientBuilder options | No direct equivalent | Keep the legacy configuration or implement a custom factory |

#### Before

```php
'opensearch' => [
    'hosts' => [env('OPENSEARCH_HOST', 'localhost:9200')],
    'basicAuthentication' => [
        env('OPENSEARCH_USERNAME', 'admin'),
        env('OPENSEARCH_PASSWORD', 'admin'),
    ],
    'retries' => env('OPENSEARCH_RETRYS', 2),
],
```

#### After

```php
'opensearch' => [
    'factory' => \OpenSearch\GuzzleClientFactory::class,
    'client' => [
        'base_uri' => env('OPENSEARCH_URL', 'http://localhost:9200'),
        'auth' => [
            env('OPENSEARCH_USERNAME', 'admin'),
            env('OPENSEARCH_PASSWORD', 'admin'),
        ],
        'verify' => env('OPENSEARCH_VERIFY_SSL', false),
    ],
    'retries' => env('OPENSEARCH_RETRIES', 2),
],
```

Notes:

- On the factory path, `retries` defaults to `0` when omitted. Set the previous retry count explicitly.
- The legacy `hosts` option is an array, while `client.base_uri` is a single URI. Multi-host setups or custom handlers require the legacy configuration or a custom factory.
- The factory path does not use the legacy `tracer` option.
- Clear the configuration cache after changing the configuration:

  ```bash
  php artisan config:clear
  ```

### Amazon OpenSearch Service

Before:

```php
'sigV4Region' => env('OPENSEARCH_REGION', 'us-east-1'),
'sigV4Service' => env('OPENSEARCH_SERVICE', 'es'),
'sigV4CredentialProvider' => [
    'key' => env('OPENSEARCH_IAM_KEY'),
    'secret' => env('OPENSEARCH_IAM_SECRET'),
],
```

After:

```php
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
```

Install `aws/aws-sdk-php` before using this configuration.

### Behavior changes

#### Empty search strings

`Model::search('')` now matches all records:

```php
Model::search('')->get();                      // all records
Model::search('')->where('visible', 1)->get(); // filters are preserved
```

If code relies on an empty search returning no results, add an explicit empty-string check or another query condition.

#### `take(0)` and `paginate(0)`

- `take(0)` now sends `size: 0` and returns no records.
- `paginate(0)` now sends `size: 0` and `from: 0`.

If `take(0)` was previously used to mean "use the default count", pass an explicit count instead.

#### `createIndex()`

`createIndex()` is now idempotent for existing indexes and returns an empty array instead of throwing.

If code relies on the "index already exists" exception, query whether the index exists instead.

#### `indexableAs()`

Writes, deletes, and flushes now use `indexableAs()`; searches continue to use `searchableAs()`.

The default `indexableAs()` returns `searchableAs()`, so most applications need no changes. If a model overrides `indexableAs()` or already defines a method with that name, verify the resulting write index.

### Verify the upgrade

```bash
php artisan config:clear
php artisan scout:index "App\\Models\\YourModel"
```

Then verify in the application:

- Searches with a keyword return expected results.
- Searches with an empty string return the expected dataset.
- `where()`, `whereIn()`, and `whereNotIn()` filters work.
- Create, update, delete, and flush operations target the expected index.
- No OpenSearch client deprecation warnings are emitted.
