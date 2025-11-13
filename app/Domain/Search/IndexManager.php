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

/**
 * Менеджер для управления индексами поиска.
 *
 * Выполняет реиндексацию: создаёт новый индекс, индексирует все опубликованные записи,
 * переключает алиасы и удаляет старые индексы.
 *
 * @package App\Domain\Search
 */
final class IndexManager
{
    /**
     * @param \App\Domain\Search\SearchClientInterface $client Клиент поискового движка
     * @param \App\Domain\Search\Transformers\EntryToSearchDoc $transformer Трансформер Entry в документ поиска
     * @param bool $enabled Флаг включения поиска
     * @param array<string, mixed> $config Конфигурация индексов
     */
    public function __construct(
        private readonly SearchClientInterface $client,
        private readonly EntryToSearchDoc $transformer,
        private readonly bool $enabled,
        private readonly array $config
    ) {
    }

    /**
     * Выполнить полную реиндексацию всех опубликованных записей.
     *
     * Процесс:
     * 1. Создаёт новый индекс с уникальным именем
     * 2. Индексирует все опубликованные записи батчами
     * 3. Переключает алиасы на новый индекс
     * 4. Удаляет старые индексы
     *
     * @return string Имя созданного индекса
     * @throws \RuntimeException Если поиск отключен или конфигурация отсутствует
     */
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
     * Создать новый индекс с уникальным именем.
     *
     * Имя индекса формируется как: {prefix}_{timestamp}_{random}.
     *
     * @param array<string, mixed> $indexConfig Конфигурация индекса
     * @return string Имя созданного индекса
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

    /**
     * Массовая индексация всех опубликованных записей.
     *
     * Обрабатывает записи батчами для оптимизации производительности.
     * После завершения обновляет индекс (refresh).
     *
     * @param string $indexName Имя индекса для индексации
     * @return int Количество проиндексированных записей
     */
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
     * Переключить алиасы на новый индекс.
     *
     * Атомарно переключает read и write алиасы с старых индексов на новый.
     *
     * @param array<string, mixed> $indexConfig Конфигурация индекса
     * @param string $newIndex Имя нового индекса
     * @return list<string> Список имён старых индексов, которые были отвязаны от алиасов
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
     * Удалить старые индексы.
     *
     * Удаляет все индексы из списка, кроме нового.
     *
     * @param list<string> $previousIndices Список имён старых индексов
     * @param string $newIndex Имя нового индекса (не удаляется)
     * @return void
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


