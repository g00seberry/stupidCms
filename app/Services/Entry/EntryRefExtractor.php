<?php

declare(strict_types=1);

namespace App\Services\Entry;

use App\Models\Entry;
use App\Models\Path;

/**
 * Сервис извлечения ref-значений из data_json Entry.
 *
 * Анализирует Blueprint Entry и извлекает все ID связанных Entry
 * из ref-полей в data_json.
 *
 * @package App\Services\Entry
 */
class EntryRefExtractor
{
    /**
     * Извлечь все ID связанных Entry из ref-полей.
     *
     * Анализирует Blueprint Entry, находит все Path с data_type='ref',
     * извлекает значения из data_json и возвращает уникальный список ID.
     *
     * @param Entry $entry Entry для анализа
     * @return array<int> Массив уникальных ID связанных Entry
     */
    public function extractRefEntryIds(Entry $entry): array
    {
        $blueprint = $entry->postType?->blueprint;

        // Если нет Blueprint, возвращаем пустой массив
        if (!$blueprint) {
            return [];
        }

        // Получить все ref-пути из Blueprint
        $refPaths = $blueprint->paths()
            ->where('data_type', 'ref')
            ->get();

        if ($refPaths->isEmpty()) {
            return [];
        }

        $entryIds = [];

        // Извлечь значения из каждого ref-пути
        foreach ($refPaths as $path) {
            $ids = $this->extractRefsFromPath($entry->data_json ?? [], $path);
            $entryIds = array_merge($entryIds, $ids);
        }

        // Вернуть уникальные ID
        return array_values(array_unique(array_filter($entryIds)));
    }

    /**
     * Извлечь ID из конкретного ref-пути.
     *
     * @param array<string, mixed> $data Данные data_json
     * @param Path $path Path с data_type='ref'
     * @return array<int> Массив ID (может быть пустым)
     */
    private function extractRefsFromPath(array $data, Path $path): array
    {
        // Извлечь значение из data_json по full_path
        $value = data_get($data, $path->full_path);

        if ($value === null) {
            return [];
        }

        // Нормализовать значение в массив ID
        return $this->normalizeRefValue($value);
    }

    /**
     * Нормализовать ref-значение в массив ID.
     *
     * Обрабатывает:
     * - int → [int]
     * - array<int> → array<int>
     * - array с mixed значениями → фильтрует только числовые
     *
     * @param mixed $value Значение из data_json
     * @return array<int> Массив ID
     */
    private function normalizeRefValue(mixed $value): array
    {
        // Если это число (int или numeric string)
        if (is_numeric($value)) {
            return [(int) $value];
        }

        // Если это массив
        if (is_array($value)) {
            $ids = [];
            foreach ($value as $item) {
                if (is_numeric($item)) {
                    $ids[] = (int) $item;
                }
            }
            return $ids;
        }

        // Иначе - пустой массив
        return [];
    }
}

