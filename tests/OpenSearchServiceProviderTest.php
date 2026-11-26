<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests;

use Illuminate\Support\Facades\Config;
use Mockery as m;
use OpenSearch\Client;
use OpenSearch\ClientFactoryInterface;
use OpenSearch\GuzzleClientFactory;
use Psr\Log\NullLogger;
use Zing\LaravelScout\OpenSearch\Tests\Fixtures\FakeClientFactory;
use Zing\LaravelScout\OpenSearch\Tests\Fixtures\InvalidClientFactory;

/**
 * @internal
 */
final class OpenSearchServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        if (interface_exists(ClientFactoryInterface::class)) {
            FakeClientFactory::$client = null;
            FakeClientFactory::$options = [];
            FakeClientFactory::$maxRetries = 0;
            FakeClientFactory::$logger = null;
        }

        parent::tearDown();
    }

    public function testClientIsCreatedByConfiguredFactory(): void
    {
        if (! class_exists(GuzzleClientFactory::class)) {
            $this->markTestSkipped('The OpenSearch client factory is not available.');
        }

        $client = m::mock(Client::class);
        FakeClientFactory::$client = $client;

        Config::set('scout.opensearch', [
            'factory' => FakeClientFactory::class,
            'client' => [
                'base_uri' => 'http://localhost:9200',
            ],
        ]);

        $this->assertSame($client, $this->makeClient());
        $this->assertSame([
            'base_uri' => 'http://localhost:9200',
        ], FakeClientFactory::$options);
    }

    public function testClientIsCreatedByGuzzleFactoryByDefault(): void
    {
        if (! class_exists(GuzzleClientFactory::class)) {
            $this->markTestSkipped('The OpenSearch client factory is not available.');
        }

        Config::set('scout.opensearch', [
            'client' => [
                'base_uri' => 'http://localhost:9200',
            ],
        ]);

        $this->assertInstanceOf(Client::class, $this->makeClient());
    }

    public function testRetriesArePassedToFactoryConstructor(): void
    {
        if (! class_exists(GuzzleClientFactory::class)) {
            $this->markTestSkipped('The OpenSearch client factory is not available.');
        }

        $client = m::mock(Client::class);
        FakeClientFactory::$client = $client;

        Config::set('scout.opensearch', [
            'factory' => FakeClientFactory::class,
            'client' => [
                'base_uri' => 'http://localhost:9200',
            ],
            'retries' => 3,
        ]);

        $this->assertSame($client, $this->makeClient());
        $this->assertSame(3, FakeClientFactory::$maxRetries);
    }

    public function testLoggerIsPassedToFactoryConstructor(): void
    {
        if (! class_exists(GuzzleClientFactory::class)) {
            $this->markTestSkipped('The OpenSearch client factory is not available.');
        }

        $client = m::mock(Client::class);
        $logger = new NullLogger();
        FakeClientFactory::$client = $client;

        Config::set('scout.opensearch', [
            'factory' => FakeClientFactory::class,
            'client' => [
                'base_uri' => 'http://localhost:9200',
            ],
            'logger' => $logger,
        ]);

        $this->assertSame($client, $this->makeClient());
        $this->assertSame($logger, FakeClientFactory::$logger);
    }

    public function testClientIsCreatedFromLegacyConfigurationWhenFactoryIsUnavailable(): void
    {
        if (class_exists(GuzzleClientFactory::class)) {
            $this->markTestSkipped('The OpenSearch client factory is available.');
        }

        Config::set('scout.opensearch', [
            'hosts' => ['localhost:9200'],
        ]);

        $this->assertInstanceOf(Client::class, $this->makeClient());
    }

    public function testInvalidFactoryIsRejected(): void
    {
        if (! interface_exists(ClientFactoryInterface::class)) {
            $this->markTestSkipped('The OpenSearch client factory is not available.');
        }

        Config::set('scout.opensearch', [
            'factory' => InvalidClientFactory::class,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf(
            'The OpenSearch client factory [%s] must implement [%s].',
            InvalidClientFactory::class,
            ClientFactoryInterface::class
        ));

        $this->makeClient();
    }

    private function makeClient(): Client
    {
        $this->assertInstanceOf(\Illuminate\Foundation\Application::class, $this->app);

        return $this->app->make(Client::class);
    }
}
