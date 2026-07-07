#!/usr/bin/env bash

echo "Ensuring Docker is running"

if ! docker info > /dev/null 2>&1; then
  echo "Please start docker first."
  exit 1
fi

echo "Ensuring services are running"

docker-compose up -d

echo "Waiting for OpenSearch to be ready"

until curl -s -u admin:pWGxl2AqRNW0wwQlzqz7 http://localhost:9200/_cluster/health | grep -q '"status":"green"'; do
  echo "Waiting for OpenSearch to be ready..."
  sleep 5
done

echo "Running tests"

if OPENSEARCH_HOST='localhost:9200' OPENSEARCH_USERNAME='admin' OPENSEARCH_PASSWORD='pWGxl2AqRNW0wwQlzqz7' ./vendor/bin/phpunit; then
    exit 0
else
    exit 1
fi
