<?php

declare(strict_types=1);

namespace App\Services\Entry;

use App\Models\Entry;
use App\Models\Path;

/**
 * Сервис извлечения media-значений из data_json Entry.
 *
 * Анализирует Blueprint Entry и извлекает все ULID связанных Media
 * из media-полей в data_json.
 *
 * @package App\Services\Entry
 */
class EntryMediaExtractor
{
    /**
     * Извлечь все ULID связанных Media из media-полей.
     *
     * Анализирует Blueprint Entry, находит все Path с data_type='media',
     * извлекает значения из data_json и возвращает уникальный список ULID.
     *
     * @param Entry $entry Entry для анализа
     * @return array<string> Массив уникальных ULID связанных Media
     */
    public function extractMediaIds(Entry $entry): array
    {
        $blueprint = $entry->postType?->blueprint;

        // Если нет Blueprint, возвращаем пустой массив
        if (!$blueprint) {
            return [];
        }

        // Получить все media-пути из Blueprint
        $mediaPaths = $blueprint->paths()
            ->where('data_type', 'media')
            ->get();

        if ($mediaPaths->isEmpty()) {
            return [];
        }

        $mediaIds = [];

        // Извлечь значения из каждого media-пути
        foreach ($mediaPaths as $path) {
            $ids = $this->extractMediaFromPath($entry->data_json ?? [], $path);
            $mediaIds = array_merge($mediaIds, $ids);
        }

        // Вернуть уникальные ULID
        return array_values(array_unique(array_filter($mediaIds)));
    }

    /**
     * Извлечь ULID из конкретного media-пути.
     *
     * @param array<string, mixed> $data Данные data_json
     * @param Path $path Path с data_type='media'
     * @return array<string> Массив ULID (может быть пустым)
     */
    private function extractMediaFromPath(array $data, Path $path): array
    {
        // Извлечь значение из data_json по full_path
        $value = data_get($data, $path->full_path);

        if ($value === null) {
            return [];
        }

        // Нормализовать значение в массив ULID
        return $this->normalizeMediaValue($value);
    }

    /**
     * Нормализовать media-значение в массив ULID.
     *
     * Обрабатывает:
     * - string → [string]
     * - array<string> → array<string>
     * - array с mixed значениями → фильтрует только непустые строки
     *
     * @param mixed $value Значение из data_json
     * @return array<string> Массив ULID
     */
    private function normalizeMediaValue(mixed $value): array
    {
        // Если это непустая строка
        if (is_string($value) && $value !== '') {
            return [$value];
        }

        // Если это массив
        if (is_array($value)) {
            $ids = [];
            foreach ($value as $item) {
                if (is_string($item) && $item !== '') {
                    $ids[] = $item;
                }
            }
            return $ids;
        }

        // Иначе - пустой массив
        return [];
    }
}

