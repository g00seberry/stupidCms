<?php

declare(strict_types=1);

namespace App\Domain\Search\Clients;

use App\Domain\Search\SearchClientInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;

final class ElasticsearchSearchClient implements SearchClientInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly array $config
    ) {
    }

    public function search(string $indexAlias, array $body): array
    {
        $response = $this->request()
            ->post(sprintf('/%s/_search', trim($indexAlias, '/')), $body);

        return $response->throw()->json();
    }

    public function createIndex(string $indexName, array $settings, array $mappings): void
    {
        $payload = array_filter([
            'settings' => $settings,
            'mappings' => $mappings,
        ]);

        $this->request()
            ->put(sprintf('/%s', trim($indexName, '/')), $payload)
            ->throw();
    }

    public function deleteIndex(string $indexName): void
    {
        $response = $this->request()
            ->delete(sprintf('/%s', trim($indexName, '/')));

        if ($response->status() === 404) {
            return;
        }

        $response->throw();
    }

    public function updateAliases(array $actions): void
    {
        $this->request()
            ->post('/_aliases', ['actions' => $actions])
            ->throw();
    }

    public function getIndicesForAlias(string $alias): array
    {
        $response = $this->request()
            ->get(sprintf('/_alias/%s', trim($alias, '/')));

        if ($response->status() === 404) {
            return [];
        }

        $data = $response->throw()->json();

        return array_keys($data ?? []);
    }

    public function bulk(array $operations): void
    {
        if ($operations === []) {
            return;
        }

        $payload = $this->toNdjson($operations);

        $response = $this->request()
            ->withHeaders(['Content-Type' => 'application/x-ndjson'])
            ->post('/_bulk', $payload);

        $response->throw();

        $body = $response->json();

        if (is_array($body) && Arr::get($body, 'errors') === true) {
            throw new \RuntimeException('Bulk indexing completed with errors.');
        }
    }

    public function refresh(string $indexName): void
    {
        $this->request()
            ->post(sprintf('/%s/_refresh', trim($indexName, '/')))
            ->throw();
    }

    /**
     * @param list<array<string, mixed>> $operations
     */
    private function toNdjson(array $operations): string
    {
        $lines = array_map(
            static fn (array $operation): string => json_encode($operation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $operations
        );

        return implode("\n", $lines) . "\n";
    }

    private function request(): PendingRequest
    {
        $hosts = $this->config['hosts'] ?? ['http://127.0.0.1:9200'];
        $host = is_array($hosts) && $hosts !== [] ? $hosts[0] : 'http://127.0.0.1:9200';

        $request = $this->http->baseUrl(rtrim($host, '/'))
            ->timeout((float) ($this->config['timeout'] ?? 2.5));

        if (($this->config['verify_ssl'] ?? true) === false) {
            $request->withoutVerifying();
        }

        $username = $this->config['username'] ?? null;
        $password = $this->config['password'] ?? null;

        if ($username !== null && $username !== '') {
            $request->withBasicAuth($username, $password ?? '');
        }

        return $request;
    }
}


