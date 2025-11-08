<?php

declare(strict_types=1);

namespace App\Domain\Search\Clients;

use App\Domain\Search\SearchClientInterface;

final class NullSearchClient implements SearchClientInterface
{
    public function search(string $indexAlias, array $body): array
    {
        return [
            'took' => 0,
            'hits' => [
                'total' => ['value' => 0, 'relation' => 'eq'],
                'hits' => [],
            ],
        ];
    }

    public function createIndex(string $indexName, array $settings, array $mappings): void
    {
        // no-op
    }

    public function deleteIndex(string $indexName): void
    {
        // no-op
    }

    public function updateAliases(array $actions): void
    {
        // no-op
    }

    public function getIndicesForAlias(string $alias): array
    {
        return [];
    }

    public function bulk(array $operations): void
    {
        // no-op
    }

    public function refresh(string $indexName): void
    {
        // no-op
    }
}


