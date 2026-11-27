<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Scout;
use Laravel\Scout\ScoutServiceProvider;
use OpenSearch\ClientBuilder;
use OpenSearch\GuzzleClientFactory;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Zing\LaravelScout\OpenSearch\OpenSearchServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [ScoutServiceProvider::class, OpenSearchServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        Config::set(
            'database',
            [
                'default' => 'testing',
                'connections' => [
                    'testing' => [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                        'foreign_key_constraints' => false,
                    ],
                    'mongodb' => [
                        'driver' => 'mongodb',
                        'host' => 'localhost',
                        'database' => 'testing',
                    ],
                ],
            ]
        );
        Config::set('scout.driver', 'opensearch');
        Config::set('scout.opensearch', class_exists(GuzzleClientFactory::class) ? [
            'factory' => GuzzleClientFactory::class,
            'client' => [
                'base_uri' => 'http://localhost:9200',
                'auth' => ['admin', 'admin'],
                'verify' => false,
            ],
        ] : [
            'hosts' => ['localhost:9200'],
            'retries' => 2,
            'handler' => ClientBuilder::multiHandler(),
            'basicAuthentication' => ['admin', 'admin'],
            'sslVerification' => false,
        ]);
    }

    protected function supportsWhereOperators(): bool
    {
        return version_compare(Scout::VERSION, '11.0.0', '>=');
    }

    protected function setUpDatabase(): void
    {
        DB::connection()->getSchemaBuilder()->create(
            'searchable_models',
            static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('name')
                    ->default('');
                $table->boolean('is_visible')
                    ->nullable()
                    ->default(true);
                $table->timestamps();
                $table->softDeletes();
            }
        );
        DB::connection()->getSchemaBuilder()->create(
            'searchable_model_has_uuids',
            static function (Blueprint $table): void {
                $table->uuid('uuid');
                $table->string('name')
                    ->default('');
                $table->boolean('is_visible')
                    ->nullable()
                    ->default(true);
                $table->timestamps();
                $table->softDeletes();
            }
        );
    }
}
