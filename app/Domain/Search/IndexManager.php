<?php

declare(strict_types=1);

namespace App\Domain\Search;

use App\Domain\Search\Transformers\EntryToSearchDoc;
use App\Models\Entry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

final class IndexManager
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly SearchClientInterface $client,
        private readonly EntryToSearchDoc $transformer,
        private readonly bool $enabled,
        private readonly array $config
    ) {
    }

    public function reindex(): string
    {
        if (! $this->enabled) {
            throw new RuntimeException('Search is disabled.');
        }

        $indexConfig = $this->config['indexes']['entries'] ?? null;

        if (! is_array($indexConfig)) {
            throw new RuntimeException('Search index configuration is missing.');
        }

        $newIndex = $this->createIndex($indexConfig);

        $count = $this->bulkImport($newIndex);

        Log::info('Search reindex completed', [
            'index' => $newIndex,
            'documents' => $count,
        ]);

        $previousIndices = $this->switchAliases($indexConfig, $newIndex);

        $this->cleanupOldIndices($previousIndices, $newIndex);

        return $newIndex;
    }

    /**
     * @param array<string, mixed> $indexConfig
     */
    private function createIndex(array $indexConfig): string
    {
        $prefix = (string) Arr::get($indexConfig, 'name_prefix', 'entries');
        $newIndex = sprintf(
            '%s_%s_%s',
            $prefix,
            now()->format('YmdHis'),
            Str::lower(Str::random(6))
        );

        $settings = Arr::get($indexConfig, 'settings', []);
        $mappings = Arr::get($indexConfig, 'mappings', []);

        $this->client->createIndex($newIndex, $settings, $mappings);

        return $newIndex;
    }

    private function bulkImport(string $indexName): int
    {
        $batchSize = (int) Arr::get($this->config, 'batch.size', 500);
        $processed = 0;

        Entry::query()
            ->published()
            ->with(['postType', 'terms.taxonomy'])
            ->orderBy('id')
            ->chunkById($batchSize, function (Collection $entries) use (&$processed, $indexName): void {
                $operations = [];

                foreach ($entries as $entry) {
                    $document = $this->transformer->transform($entry);

                    $operations[] = [
                        'index' => [
                            '_index' => $indexName,
                            '_id' => $document['id'],
                        ],
                    ];

                    $operations[] = $document;
                    $processed++;
                }

                $this->client->bulk($operations);
            });

        $this->client->refresh($indexName);

        return $processed;
    }

    /**
     * @param array<string, mixed> $indexConfig
     */
    /**
     * @return list<string>
     */
    private function switchAliases(array $indexConfig, string $newIndex): array
    {
        $readAlias = (string) Arr::get($indexConfig, 'read_alias', 'entries_read');
        $writeAlias = (string) Arr::get($indexConfig, 'write_alias', 'entries_write');

        $actions = [];

        $currentRead = $this->client->getIndicesForAlias($readAlias);
        foreach ($currentRead as $index) {
            $actions[] = [
                'remove' => [
                    'alias' => $readAlias,
                    'index' => $index,
                ],
            ];
        }

        $currentWrite = $this->client->getIndicesForAlias($writeAlias);
        foreach ($currentWrite as $index) {
            $actions[] = [
                'remove' => [
                    'alias' => $writeAlias,
                    'index' => $index,
                ],
            ];
        }

        $actions[] = [
            'add' => [
                'alias' => $readAlias,
                'index' => $newIndex,
            ],
        ];

        $actions[] = [
            'add' => [
                'alias' => $writeAlias,
                'index' => $newIndex,
            ],
        ];

        $this->client->updateAliases($actions);

        return array_values(array_unique(array_merge($currentRead, $currentWrite)));
    }

    /**
     * @param list<string> $previousIndices
     */
    private function cleanupOldIndices(array $previousIndices, string $newIndex): void
    {
        foreach ($previousIndices as $index) {
            if ($index !== $newIndex) {
                $this->client->deleteIndex($index);
            }
        }
    }
}


