<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\ScoutServiceProvider;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use OpenSearch\ClientBuilder;
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
        Config::set('scout.opensearch', [
            'hosts' => ['localhost:9200'],
            'retries' => 2,
            'handler' => ClientBuilder::multiHandler(),
            'logger' => new Logger('test', [new RotatingFileHandler('test')]),
            'basicAuthentication' => ['admin', 'admin'],
            'sslVerification' => false,
        ]);
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
                    ->default(true);
                $table->timestamps();
                $table->softDeletes();
            }
        );
    }
}
