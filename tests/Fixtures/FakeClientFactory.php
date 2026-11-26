<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests\Fixtures;

use OpenSearch\Client;
use OpenSearch\ClientFactoryInterface;
use Psr\Log\LoggerInterface;

final class FakeClientFactory implements ClientFactoryInterface
{
    /**
     * @var array<string, mixed>
     */
    public static array $options = [];

    public static ?Client $client = null;

    public static int $maxRetries = 0;

    public static ?LoggerInterface $logger = null;

    public function __construct(int $maxRetries = 0, ?LoggerInterface $logger = null)
    {
        self::$maxRetries = $maxRetries;
        self::$logger = $logger;
    }

    public function create(array $options): Client
    {
        self::$options = $options;

        if (! self::$client instanceof Client) {
            throw new \LogicException('The fake OpenSearch client has not been set.');
        }

        return self::$client;
    }
}
