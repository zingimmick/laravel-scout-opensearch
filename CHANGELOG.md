# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

<!-- changelog-linker -->
## 4.0.0 - 2026-11-27

### Highlights

- OpenSearch clients are created through configurable `ClientFactoryInterface` implementations.
- Write operations can target a different index from read operations through `indexableAs()`.
- Scout index settings and mappings are supported through `UpdatesIndexSettings`.
- `deleteAllIndexes()` and `scout:delete-all-indexes` are supported.
- `BackedEnum` values are supported in `where()`, `whereIn()`, and `whereNotIn()`.
- Empty search strings return all records instead of no records.
- `take(0)` and zero-sized pagination now preserve `size: 0` and `from: 0`.
- `createIndex()` is idempotent when the index already exists.

### Compatibility

| Dependency | 3.5.1 | 4.x |
| --- | --- | --- |
| PHP | `^8.0` | `^8.2` |
| `laravel/scout` | `^8.5 \|\| ^9.1 \|\| ^10.0 \|\| ^11.0` | `^10.12 \|\| ^11.0` |
| `opensearch-project/opensearch-php` | `^2.0` | `^2.0` (`^2.5` required for the client factory API) |

PHP 8.6 is covered by the test matrix.

### Breaking changes

#### Requirements

- Minimum PHP version is now 8.2.
- Minimum Laravel Scout version is now 10.12.

#### Client configuration

- The default configuration format is now `factory` + `client`.
- The legacy `hosts` / `basicAuthentication` format remains supported only when neither `factory` nor `client` is set.
- The legacy path uses the deprecated `ClientBuilder` on `opensearch-project/opensearch-php` 2.4+ and emits deprecation notices.
- Factory support requires `opensearch-project/opensearch-php` 2.5+.
- On the factory path, `retries` defaults to `0` when omitted.

#### Write index

- `update()`, `delete()`, and `flush()` now use `indexableAs()`.
- `search()` and `paginate()` continue to use `searchableAs()`.
- The default `indexableAs()` returns `searchableAs()`, so behavior is unchanged unless a model overrides it.

#### Search behavior

- A blank search query now uses `match_all` and returns all records. Previously an empty `query_string` was sent.
- `take(0)` now sends `size: 0` and returns no records.
- Zero-sized pagination now sends `size: 0` and `from: 0`.
- Empty sort clauses are no longer sent.

#### Index management

- `createIndex()` no longer throws when the index already exists.
- `createIndex()` returns an empty array for an existing index and re-throws unrelated errors.

### New features

#### OpenSearch client factories

Clients are resolved through `OpenSearch\ClientFactoryInterface`. The built-in `GuzzleClientFactory` is used by default, and `SymfonyClientFactory` is supported when `symfony/http-client` is installed.

Custom factories are resolved through the Laravel container. Constructor parameters named `maxRetries` and `logger` receive the top-level `retries` and `logger` options.

#### Separate read and write indexes

Models may override `indexableAs()` to send indexing, deleting, and flushing operations to a different index than searches.

#### Scout index settings

`OpenSearchEngine` implements `Laravel\Scout\Contracts\UpdatesIndexSettings`:

- Settings are written with `indices()->putSettings()`.
- Mappings are written with `indices()->putMapping()`.
- `scout:sync-index-settings` synchronizes the configured settings.
- `scout:index` applies matching settings after creating an index.
- An integer `__soft_deleted` mapping is added automatically for soft-deletable models when `scout.soft_delete` is enabled.

#### Delete all indexes

`deleteAllIndexes()` lists indexes through `_cat/indices` and deletes indexes that start with `scout.prefix`. The Scout command is available:

```bash
php artisan scout:delete-all-indexes

```
> This deletes every index under the current Scout prefix, not a single model's index.

#### BackedEnum filters

`where()`, `whereIn()`, and `whereNotIn()` convert `BackedEnum` values to their underlying values.

#### AWS / Symfony clients

- AWS SigV4 authentication is configured through `client.auth_aws`.
- The Symfony HTTP client is supported through `OpenSearch\SymfonyClientFactory`.

### Fixes and improvements

- Empty searches no longer return zero results solely because the query string is blank.
- `size: 0` and `from: 0` are no longer discarded while building search options.
- Existing indexes no longer cause `scout:index` to fail.
- Added PHP 8.6 compatibility testing.
- Added a local Docker OpenSearch test environment.
- Updated test and static analysis tooling.

### Changes by commit

| Commit | Change | Type |
| --- | --- | --- |
| `2c57625` | Minimum PHP version raised to 8.2 | Breaking |
| `1c8c302` | Added PHP 8.6 support | Compatibility |
| `b7d45b0` | Added OpenSearch client factory support | Feature / breaking configuration change |
| `41886e7` | Writes use `indexableAs()` | Feature / behavior change |
| `54aa02e` | Added `deleteAllIndexes()` | Feature |
| `29558ca` | Made `createIndex()` idempotent | Behavior change |
| `a4c6ba0` | Added BackedEnum filter support | Feature |
| `9155d20` | Empty queries use `match_all` | Behavior change |
| `40c560e` | Preserved `size: 0` / `from: 0` | Fix / behavior change |
| `b35a697` | Added Scout index settings support | Feature |
| `bb29700` | Added where value type coverage | Tests only |

### Upgrading

See [UPGRADING.md](../UPGRADING.md) for the required upgrade steps, configuration examples, and behavior changes.

## 3.5.1 - 2026-03-18

<!-- Release notes generated using configuration in .github/release.yml at 3.x -->
### What's Changed

#### Other Changes

* Bump ramsey/composer-install from 3 to 4 by @dependabot[bot] in https://github.com/zingimmick/laravel-scout-opensearch/pull/132
* Bump shivammathur/setup-php from 2.36.0 to 2.37.0 by @dependabot[bot] in https://github.com/zingimmick/laravel-scout-opensearch/pull/133
* Allow Laravel 13 in tests by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/134

**Full Changelog**: https://github.com/zingimmick/laravel-scout-opensearch/compare/3.5.0...3.5.1

## 3.5.0 - 2026-03-14

<!-- Release notes generated using configuration in .github/release.yml at 3.x -->
### What's Changed

#### Other Changes

* Support for PHPUnit 12 by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/111
* Support for PHP 8.5 by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/122
* Update orchestra/testbench version constraint by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/126
* Refactor configuration setup in OpenSearchEngineTest by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/127
* Fix filtering null values in where() by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/129
* Add search engine meta data to results by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/130
* Enhance OpenSearchEngineTest with scoutMetadata check by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/131

**Full Changelog**: https://github.com/zingimmick/laravel-scout-opensearch/compare/3.4.3...3.5.0

## 3.4.3 - 2025-02-24

<!-- Release notes generated using configuration in .github/release.yml at 3.x -->
### What's Changed

#### Other Changes

* Trim the double `hits` result structure when using query callback by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/109
* Support for Laravel 12 by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/110

**Full Changelog**: https://github.com/zingimmick/laravel-scout-opensearch/compare/3.4.2...3.4.3

## 3.4.2 - 2025-02-10

<!-- Release notes generated using configuration in .github/release.yml at 3.x -->
### What's Changed

#### Other Changes

* Allow Laravel 11 in GitHub action tests by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/90
* Fix PHPStan issues by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/101
* Support for PHP 8.4 by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/100
* Allow nesbot/carbon 3.4 in tests action by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/103
* Update license copyright year(s) by @github-actions in https://github.com/zingimmick/laravel-scout-opensearch/pull/105
* Update nunomaduro/larastan requirement from ^1.0 || ^2.0 to ^1.0 || ^2.0 || ^3.0 by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/106
* Use larastan/larastan instead of nunomaduro/larastan by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/107

**Full Changelog**: https://github.com/zingimmick/laravel-scout-opensearch/compare/3.4.1...3.4.2

## 3.4.1 - 2024-04-09

<!-- Release notes generated using configuration in .github/release.yml at 3.x -->
### What's Changed

#### Other Changes

* Allow PHPUnit 11 by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/89

**Full Changelog**: https://github.com/zingimmick/laravel-scout-opensearch/compare/3.4.0...3.4.1

## 3.4.0 - 2024-03-18

<!-- Release notes generated using configuration in .github/release.yml at 3.x -->
### What's Changed

#### Other Changes

* Support for PHP 8.3 by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/76
* Update license copyright year(s) by @github-actions in https://github.com/zingimmick/laravel-scout-opensearch/pull/81
* Support for Laravel 11 by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/86

**Full Changelog**: https://github.com/zingimmick/laravel-scout-opensearch/compare/3.3.0...3.4.0

## 3.3.0 - 2023-09-06

<!-- Release notes generated using configuration in .github/release.yml at 3.x -->
### What's Changed

#### Exciting New Features 🎉

- Add "whereNotIn" native support by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/73

#### Other Changes

- Remove errors from phpstan.neon ignore errors by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/65
- Remove skipped rules on $rectorConfig->skip() are no longer exists by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/67
- Increase sleep time for test by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/69
- Fix badge url by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/70
- Improve test coverage by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/71
- Add AWS configuration examples by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/74

**Full Changelog**: https://github.com/zingimmick/laravel-scout-opensearch/compare/3.2.0...3.3.0

## 3.2.0 - 2023-06-15

<!-- Release notes generated using configuration in .github/release.yml at 3.x -->
### What's Changed

#### Other Changes

- Fix illegal argument exception when use UUIDs as primary keys by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/63

**Full Changelog**: https://github.com/zingimmick/laravel-scout-opensearch/compare/3.1.0...3.2.0

## 3.1.0 - 2023-06-01

<!-- Release notes generated using configuration in .github/release.yml at 3.x -->
### What's Changed

#### Other Changes

- Flush records by deleteByQuery by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/58

**Full Changelog**: https://github.com/zingimmick/laravel-scout-opensearch/compare/3.0.0...3.1.0

## 3.0.0 - 2023-06-01

<!-- Release notes generated using configuration in .github/release.yml at 3.x -->
### What's Changed

#### Other Changes

- Migrate from Alibaba Cloud OpenSearch to OpenSearch by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/57

**Full Changelog**: https://github.com/zingimmick/laravel-scout-opensearch/compare/2.2.0...3.0.0

## 2.2.0 - 2023-03-17

<!-- Release notes generated using configuration in .github/release.yml at 2.x -->
### What's Changed

#### Other Changes

- Update laravel/scout requirement from ^8.5 || ^9.1 to ^8.5 || ^9.1 || ^10.0 by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/50

**Full Changelog**: https://github.com/zingimmick/laravel-scout-opensearch/compare/2.1.0...2.2.0

## 2.1.0 - 2023-02-16

<!-- Release notes generated using configuration in .github/release.yml at 2.x -->
### What's Changed

#### Other Changes

- Update license copyright year(s) by @github-actions in https://github.com/zingimmick/laravel-scout-opensearch/pull/47

**Full Changelog**: https://github.com/zingimmick/laravel-scout-opensearch/compare/2.0.0...2.1.0

## 2.0.0 - 2022-12-12

<!-- Release notes generated using configuration in .github/release.yml at 2.x -->
### What's Changed

#### Other Changes

- Enable parallel run for rector by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/37
- Add homepage for authors by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/39
- Report unmatched ignored errors for PHPStan by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/40
- Add support for new ECSConfig and RectorConfig class by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/41
- Use ESCConfig instead of parameters by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/42
- Fix tests action for PHP 8.2 by @zingimmick in https://github.com/zingimmick/laravel-scout-opensearch/pull/45

**Full Changelog**: https://github.com/zingimmick/laravel-scout-opensearch/compare/1.3.0...2.0.0

## Unreleased

- [#6](https://github.com/zingimmick/laravel-scout-opensearch/pull/6) Add tests for failed response

## [0.1.0](https://github.com/zingimmick/laravel-scout-opensearch/compare/0.0.1...0.1.0)

### Changed

- [#5](https://github.com/zingimmick/laravel-scout-opensearch/pull/5) Continue on error

## [0.0.1] - 2021-08-11

- [#1](https://github.com/zingimmick/laravel-scout-opensearch/pull/1) Laravel Scout custom engine for OpenSearch

### Fixed

- [#4](https://github.com/zingimmick/laravel-scout-opensearch/pull/4) Fix scout key not matched
- [#3](https://github.com/zingimmick/laravel-scout-opensearch/pull/3) Fix capitalization issues
