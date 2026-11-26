<?php

use OpenSearch\ClientBuilder;
use OpenSearch\GuzzleClientFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$retries = 0;
$maxRetries = 10;

while (true) {
    try {
        $client = class_exists(GuzzleClientFactory::class)
            ? (new GuzzleClientFactory())->create([
                'base_uri' => 'http://localhost:9200',
            ])
            : ClientBuilder::create()->build();
        $client->ping();
        echo 'Is up and running' . PHP_EOL;
        exit(0);
    } catch (Throwable $e) {
        if ($retries === $maxRetries) {
            echo 'Cannot reach search server' . PHP_EOL;
            exit(1);
        }

        sleep(5);
        $retries++;
    }
}
