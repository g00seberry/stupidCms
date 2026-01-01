<?php

declare(strict_types=1);

namespace App\Services\Entry\Providers;

use App\Models\Entry;
use App\Services\Entry\RelatedDataProviderInterface;

/**
 * Провайдер связанных данных для Entry.
 *
 * Загружает и форматирует данные о связанных Entry для включения
 * в структуру `related.entryData` в API ответах.
 *
 * @package App\Services\Entry\Providers
 */
class EntryRelatedDataProvider implements RelatedDataProviderInterface
{
    /**
     * Получить ключ для данных в структуре `related`.
     *
     * @return string
     */
    public function getKey(): string
    {
        return 'entryData';
    }

    /**
     * Загрузить данные Entry по списку ID.
     *
     * Загружает Entry с eager loading для postType.
     * Исключает удаленные записи (deleted_at IS NULL).
     *
     * @param array<int> $ids Массив ID Entry для загрузки
     * @return array<int, array<string, mixed>> Массив данных в формате [entryId => data]
     */
    public function loadData(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        // Загрузить Entry с postType, исключая удаленные
        $entries = Entry::query()
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->with('postType')
            ->get();

        $result = [];

        foreach ($entries as $entry) {
            $result[$entry->id] = $this->formatData($entry);
        }

        return $result;
    }

    /**
     * Форматировать данные одного Entry.
     *
     * @param Entry $entry Entry для форматирования
     * @return array<string, mixed> Отформатированные данные
     */
    public function formatData(mixed $entry): array
    {
        if (!($entry instanceof Entry)) {
            throw new \InvalidArgumentException(
                'EntryRelatedDataProvider::formatData() expects Entry instance'
            );
        }

        return [
            'entryTitle' => $entry->title,
            'entryPostType' => $entry->postType?->name ?? null,
        ];
    }
}

